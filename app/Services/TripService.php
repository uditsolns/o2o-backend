<?php

namespace App\Services;

use App\Enums\SealStatus;
use App\Enums\TripStatus;
use App\Enums\TripTransportationMode;
use App\Enums\UserStatus;
use App\Exceptions\SepioException;
use App\Jobs\RegisterContainerTrackingJob;
use App\Models\CustomerConsignee;
use App\Models\CustomerConsignor;
use App\Models\Role;
use App\Models\Seal;
use App\Models\TripSegment;
use App\Models\Trip;
use App\Models\User;
use App\Services\Sepio\SepioSealService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

readonly class TripService
{
    public function __construct(
        private SealService      $sealService,
        private TripEventService $eventService,
        private SepioSealService $sepioSealService,
        private RouteService     $routeService,
    )
    {
    }

    public function store(array $data, User $createdBy): Trip
    {
        $customerId = $createdBy->isPlatformUser()
            ? ($data['customer_id'] ?? null)
            : $createdBy->customer_id;

        abort_if(!$customerId, 400, 'customer_id is required for platform users.');

        return DB::transaction(function () use ($data, $createdBy, $customerId) {
            $segments = $data['segments'] ?? [];
            $sealId = $data['seal_id'] ?? null;
            $usesSepioPSeal = (bool)($data['uses_sepio_seal'] ?? false);

            unset($data['segments'], $data['customer_id'], $data['seal_id']);

            // Default dispatch_date to today if not supplied
            $data['dispatch_date'] = $data['dispatch_date'] ?? today()->toDateString();

            $trip = Trip::create([
                ...$data,
                'customer_id' => $customerId,
                'created_by_id' => $createdBy->id,
                'trip_ref' => $this->generateTripRef(),
                'status' => TripStatus::Draft,
                'tracking_token' => Str::random(32),
            ]);

            $this->eventService->log(
                $trip,
                'trip_created',
                ['trip_ref' => $trip->trip_ref],
                newStatus: TripStatus::Draft->value,
                actorId: $createdBy->id
            );

            // Seal assignment
            if ($sealId) {
                $seal = Seal::findOrFail($sealId);

                if ($usesSepioPSeal) {
                    // Full Sepio availability check
                    $this->sealService->assignToTrip($seal, $trip->id);
                } else {
                    // Non-Sepio seal: simple local assignment, no external check
                    $this->assignSealLocally($seal, $trip->id);
                }

                $trip->update(['seal_id' => $seal->id]);

                $this->eventService->log(
                    $trip->fresh(),
                    'seal_assigned',
                    ['seal_number' => $seal->seal_number],
                    actorId: $createdBy->id
                );
            }

            // Auto-create driver user, consignor, consignee, route
            $this->upsertDriverUser($customerId, $trip);
            $this->upsertConsignor($customerId, $trip);
            $this->upsertConsignee($customerId, $trip);
            $this->routeService->findOrCreateFromTripData($customerId, $data);

            // Store segments
            foreach ($segments as $segmentData) {
                TripSegment::create([
                    ...$segmentData,
                    'trip_id' => $trip->id,
                    'customer_id' => $customerId,
                ]);
            }

            // ── Auto-start: transition from Draft → InTransit immediately ────────
            $trip = $this->autoStartTrip($trip->fresh(), $createdBy);

            return $trip->fresh();
        });
    }

    private function autoStartTrip(Trip $trip, User $by): Trip
    {
        $trip->update([
            'status' => TripStatus::InTransit,
            'trip_start_time' => now(),
        ]);

        // Sepio seal installation — only when user opted in
        if ($trip->uses_sepio_seal && $trip->seal_id && $trip->customer->sepio_company_id) {
            try {
                $this->sepioSealService->installSeal($trip->customer, $trip->fresh());
            } catch (SepioException $e) {
                // Roll back to Draft so the trip is not left in a broken state
                $trip->update(['status' => TripStatus::Draft, 'trip_start_time' => null]);
                throw $e;
            }
        }

        // Container tracking registration for sea/multimodal
        if (
            in_array($trip->transport_mode, [TripTransportationMode::Sea, TripTransportationMode::Multimodal], true)
            && !empty($trip->container_number)
            && !empty($trip->carrier_scac)
        ) {
            RegisterContainerTrackingJob::dispatch($trip->fresh());
        }

        $this->eventService->log(
            $trip->fresh(),
            'trip_started',
            ['auto_started' => true],
            TripStatus::Draft->value,
            TripStatus::InTransit->value,
            actorId: $by->id
        );

        return $trip->fresh();
    }

    public function update(Trip $trip, array $data, User $updatedBy): Trip
    {
        return DB::transaction(function () use ($trip, $data, $updatedBy) {
            $previousStatus = $trip->status->value;
            $newStatus = $data['status'] ?? null;

            unset($data['seal_id']); // seal changes go through changeSeal endpoint

            $trip->update($data);

            // Re-sync driver user if phone number changed
            if (isset($data['driver_phone']) && $data['driver_phone'] !== $trip->driver_phone) {
                $this->upsertDriverUser($trip->customer_id, $trip->fresh());
            }

            if ($newStatus && $newStatus !== $previousStatus) {
                $this->eventService->log($trip->fresh(), 'status_changed', [],
                    $previousStatus, $newStatus, actorId: $updatedBy->id);
            }

            return $trip->fresh();
        });
    }

    /** POST /trips/{trip}/start → in_transit */
    public function startTrip(Trip $trip, array $data, User $by): Trip
    {
        abort_if($trip->status !== TripStatus::Draft, 422, 'Only Draft trips can be started.');

        // Seal is required only when the trip uses Sepio seals
        abort_if(
            $trip->uses_sepio_seal && !$trip->seal_id,
            422,
            'A Sepio seal must be assigned before starting the trip.'
        );

        return DB::transaction(function () use ($trip, $data, $by) {
            $updates = ['status' => TripStatus::InTransit, 'trip_start_time' => now()];

            if (!empty($data['dispatch_date'])) {
                $updates['dispatch_date'] = $data['dispatch_date'];
            }

            $trip->update($updates);

            if ($trip->uses_sepio_seal && $trip->customer->sepio_company_id) {
                try {
                    $this->sepioSealService->installSeal($trip->customer, $trip->fresh());
                } catch (SepioException $e) {
                    $trip->update(['status' => TripStatus::Draft, 'trip_start_time' => null]);
                    throw $e;
                }
            }

            if (
                in_array($trip->transport_mode, [TripTransportationMode::Sea, TripTransportationMode::Multimodal], true)
                && !empty($trip->container_number)
                && !empty($trip->carrier_scac)
            ) {
                RegisterContainerTrackingJob::dispatch($trip->fresh());
            }

            $this->eventService->log(
                $trip->fresh(),
                'trip_started',
                [],
                TripStatus::Draft->value,
                TripStatus::InTransit->value,
                actorId: $by->id
            );

            return $trip->fresh();
        });
    }

    /** PATCH /trips/{trip}/seal — only in Draft */
    public function changeSeal(Trip $trip, int $newSealId, User $by): Trip
    {
        return DB::transaction(function () use ($trip, $newSealId, $by) {
            if ($trip->seal) {
                $this->sealService->releaseFromTrip($trip->seal);
            }

            $seal = Seal::findOrFail($newSealId);

            if ($trip->uses_sepio_seal) {
                $this->sealService->assignToTrip($seal, $trip->id);
            } else {
                $this->assignSealLocally($seal, $trip->id);
            }

            $trip->update(['seal_id' => $seal->id]);

            $this->eventService->log(
                $trip->fresh(),
                'seal_assigned',
                ['seal_number' => $seal->seal_number],
                actorId: $by->id
            );

            return $trip->fresh();
        });
    }

    /** POST /trips/{trip}/confirm-epod → Completed */
    public function confirmEpod(Trip $trip, array $data, User $by): Trip
    {
        return DB::transaction(function () use ($trip, $data, $by) {
            $previousStatus = $trip->status->value;

            $trip->update([
                'status' => TripStatus::Completed,
                'epod_status' => 'completed',
                'epod_confirmed_at' => now(),
                'epod_confirmed_by_id' => $by->id,
                'epod_confirmation_notes' => $data['notes'] ?? null,
                'actual_delivery_date' => $data['actual_delivery_date'] ?? today(),
                'trip_end_time' => now(),
            ]);

            if ($trip->seal) {
                $trip->seal->update(['status' => SealStatus::Used]);
            }

            $this->eventService->log($trip->fresh(), 'epod_confirmed', [],
                $previousStatus, TripStatus::Completed->value, actorId: $by->id);

            return $trip->fresh();
        });
    }

    public function addVesselInfo(Trip $trip, array $data, User $by): Trip
    {
        $trip->update([
            'vessel_name' => $data['vessel_name'],
            'vessel_imo_number' => $data['vessel_imo_number'] ?? null,
            'voyage_number' => $data['voyage_number'] ?? null,
            'bill_of_lading' => $data['bill_of_lading'] ?? null,
            'eta' => $data['eta'] ?? null,
            'etd' => $data['etd'] ?? null,
        ]);

        $this->eventService->log($trip->fresh(), 'vessel_info_added',
            ['vessel_name' => $data['vessel_name']], actorId: $by->id);

        return $trip->fresh();
    }

    private function upsertDriverUser(int $customerId, Trip $trip): void
    {
        $mode = $trip->transport_mode instanceof TripTransportationMode
            ? $trip->transport_mode
            : TripTransportationMode::from($trip->transport_mode);

        if (!in_array($mode, [TripTransportationMode::Road, TripTransportationMode::Multimodal], true)) {
            return;
        }

        if (empty($trip->driver_phone)) {
            return;
        }

        $driverRole = Role::where('name', 'driver')->first();

        if (!$driverRole) {
            return;
        }

        $user = User::where('customer_id', $customerId)
            ->where('mobile', $trip->driver_phone)
            ->where('role_id', $driverRole->id)
            ->first();

        if ($user) {
            // Update name if trip has a more specific one
            if (!empty($trip->driver_name) && $user->name !== $trip->driver_name) {
                $user->update(['name' => $trip->driver_name]);
            }
        } else {
            $user = User::create([
                'role_id' => $driverRole->id,
                'customer_id' => $customerId,
                'name' => $trip->driver_name ?? 'Driver ' . $trip->driver_phone,
                // Internal synthetic email — drivers log in via mobile + password ideally,
                // but we need a unique email for the users table
                'email' => 'driver.' . $trip->driver_phone . '@customer-' . $customerId . '.internal',
                'mobile' => $trip->driver_phone,
                'password' => bcrypt($trip->driver_phone),
                'status' => UserStatus::Active,
            ]);
        }

        $trip->updateQuietly(['driver_user_id' => $user->id]);
    }

    private function upsertConsignor(int $customerId, Trip $trip): void
    {
        if (empty($trip->dispatch_contact_person)) return;

        CustomerConsignor::firstOrCreate(
            ['customer_id' => $customerId, 'name' => $trip->dispatch_contact_person],
            [
                'contact_number' => $trip->dispatch_contact_number,
                'contact_email' => $trip->dispatch_contact_email,
            ]
        );
    }

    private function upsertConsignee(int $customerId, Trip $trip): void
    {
        if (empty($trip->delivery_contact_person)) return;

        CustomerConsignee::firstOrCreate(
            ['customer_id' => $customerId, 'name' => $trip->delivery_contact_person],
            [
                'contact_number' => $trip->delivery_contact_number,
                'contact_email' => $trip->delivery_contact_email,
            ]
        );
    }

    private function assignSealLocally(Seal $seal, int $tripId): void
    {
        abort_if(
            !$seal->isAvailable(),
            422,
            "Seal {$seal->seal_number} is not available (current status: {$seal->status->value})."
        );

        $seal->update([
            'trip_id' => $tripId,
            'status' => SealStatus::Assigned,
        ]);
    }

    private function generateTripRef(): string
    {
        $last = Trip::lockForUpdate()->latest('id')->value('trip_ref');
        $next = $last ? (int)substr($last, 2) + 1 : 1;
        return 'TR' . str_pad($next, 7, '0', STR_PAD_LEFT);
    }
}
