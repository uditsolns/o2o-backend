<?php

namespace App\Services;

use App\Enums\SealStatus;
use App\Enums\TripStatus;
use App\Exceptions\SepioException;
use App\Models\CustomerConsignee;
use App\Models\CustomerConsignor;
use App\Models\Seal;
use App\Models\TripSegment;
use App\Models\Trip;
use App\Models\User;
use App\Services\Sepio\SepioSealService;
use Illuminate\Support\Facades\DB;

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
            unset($data['segments'], $data['customer_id'], $data['seal_id']);

            $trip = Trip::create([
                ...$data,
                'customer_id' => $customerId,
                'created_by_id' => $createdBy->id,
                'trip_ref' => $this->generateTripRef(),
                'status' => TripStatus::Draft,
            ]);

            $this->eventService->log($trip, 'trip_created', ['trip_ref' => $trip->trip_ref],
                newStatus: TripStatus::Draft->value, actorId: $createdBy->id);

            // Assign seal if provided
            if ($sealId) {
                $seal = Seal::findOrFail($sealId);
                $this->sealService->assignToTrip($seal, $trip->id);
                $trip->update(['seal_id' => $seal->id]);
                $this->eventService->log($trip->fresh(), 'seal_assigned',
                    ['seal_number' => $seal->seal_number], actorId: $createdBy->id);
            }

            // Auto-create consignor & consignee
            $this->upsertConsignor($customerId, $trip);
            $this->upsertConsignee($customerId, $trip);

            // Auto-create route
            $this->routeService->findOrCreateFromTripData($customerId, $data);

            // Store segments
            foreach ($segments as $segmentData) {
                TripSegment::create([
                    ...$segmentData,
                    'trip_id' => $trip->id,
                    'customer_id' => $customerId,
                ]);
            }

            return $trip->fresh();
        });
    }

    public function update(Trip $trip, array $data, User $updatedBy): Trip
    {
        return DB::transaction(function () use ($trip, $data, $updatedBy) {
            $previousStatus = $trip->status->value;
            $newStatus = $data['status'] ?? null;

            unset($data['seal_id']); // seal changes go through changeSeal endpoint

            $trip->update($data);

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
        abort_if(!$trip->seal_id, 422, 'A seal must be assigned before starting the trip.');

        return DB::transaction(function () use ($trip, $data, $by) {
            $updates = ['status' => TripStatus::InTransit, 'trip_start_time' => now()];
            if (!empty($data['dispatch_date'])) {
                $updates['dispatch_date'] = $data['dispatch_date'];
            }

            $trip->update($updates);

            if ($trip->customer->sepio_company_id) {
                try {
                    $this->sepioSealService->installSeal($trip->customer, $trip->fresh());
                } catch (SepioException $e) {
                    $trip->update(['status' => TripStatus::Draft, 'trip_start_time' => null]);
                    throw $e;
                }
            }

            $this->eventService->log($trip->fresh(), 'trip_started', [],
                TripStatus::Draft->value, TripStatus::InTransit->value, actorId: $by->id);

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
            $this->sealService->assignToTrip($seal, $trip->id);
            $trip->update(['seal_id' => $seal->id]);

            $this->eventService->log($trip->fresh(), 'seal_assigned',
                ['seal_number' => $seal->seal_number], actorId: $by->id);

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

    private function generateTripRef(): string
    {
        $last = Trip::lockForUpdate()->latest('id')->value('trip_ref');
        $next = $last ? (int)substr($last, 2) + 1 : 1;
        return 'TR' . str_pad($next, 7, '0', STR_PAD_LEFT);
    }
}
