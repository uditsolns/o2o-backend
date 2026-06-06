<?php

namespace Database\Seeders;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripEvent;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Generates a realistic event timeline for every seeded trip.
 *
 * Events emitted per trip, in order:
 *   1. trip_created         (user / customer_admin who created the trip)
 *   2. trip_started         (user / customer_admin, draft -> active) — skipped for drafts
 *   3. status_changed       (system, when the trip's current status != active)
 *   4. epod_confirmed       (user, only when status == completed)
 *
 * `actor_type` follows the same convention as the production app:
 *   - 'user'    for actions triggered by a customer user
 *   - 'system'  for status transitions driven by background jobs / system rules
 *
 * Idempotent: skips trips that already have any event.
 */
class TripEventSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Trip has a TenantScope global; seeder runs as platform admin so
        // we bypass it to see trips across all customers. TripEvent (used
        // by the exists() check below) carries the same scope.
        $trips = Trip::withoutGlobalScopes()->get();

        foreach ($trips as $trip) {
            if (TripEvent::withoutGlobalScopes()->where('trip_id', $trip->id)->exists()) {
                continue;
            }

            $actorId = User::where('customer_id', $trip->customer_id)
                ->whereHas('role', fn($q) => $q->where('name', 'customer_admin'))
                ->value('id');

            if (!$actorId) {
                $actorId = User::where('email', 'admin@admin.com')->value('id');
            }

            $baseTime = $trip->created_at ?? $now;

            $events = [];

            // 1. trip_created
            $events[] = [
                'customer_id' => $trip->customer_id,
                'trip_id' => $trip->id,
                'event_type' => 'trip_created',
                'event_data' => json_encode(['trip_ref' => $trip->trip_ref]),
                'previous_status' => null,
                'new_status' => TripStatus::Draft->value,
                'actor_type' => 'user',
                'actor_id' => $trip->created_by_id ?: $actorId,
                'created_at' => $baseTime,
            ];

            // 2. trip_started — every non-draft trip has been started
            if ($trip->status->value !== TripStatus::Draft->value) {
                $events[] = [
                    'customer_id' => $trip->customer_id,
                    'trip_id' => $trip->id,
                    'event_type' => 'trip_started',
                    'event_data' => json_encode(['auto_started' => true]),
                    'previous_status' => TripStatus::Draft->value,
                    'new_status' => TripStatus::Active->value,
                    'actor_type' => 'user',
                    'actor_id' => $trip->created_by_id ?: $actorId,
                    'created_at' => $trip->trip_start_time ?: $baseTime->copy()->addSeconds(30),
                ];
            }

            // 3. status_changed for trips that advanced past Active.
            // $trip->status is a cast TripStatus enum; compare ->value.
            if (in_array($trip->status->value, [
                TripStatus::OutForDelivery->value,
                TripStatus::Delivered->value,
                TripStatus::Completed->value,
            ], true)) {
                $events[] = [
                    'customer_id' => $trip->customer_id,
                    'trip_id' => $trip->id,
                    'event_type' => 'status_changed',
                    'event_data' => json_encode(['to' => TripStatus::OutForDelivery->value]),
                    'previous_status' => TripStatus::Active->value,
                    'new_status' => TripStatus::OutForDelivery->value,
                    'actor_type' => 'system',
                    'actor_id' => null,
                    'created_at' => $baseTime->copy()->addHours(2),
                ];
            }

            if (in_array($trip->status->value, [
                TripStatus::Delivered->value,
                TripStatus::Completed->value,
            ], true)) {
                $events[] = [
                    'customer_id' => $trip->customer_id,
                    'trip_id' => $trip->id,
                    'event_type' => 'status_changed',
                    'event_data' => json_encode(['to' => TripStatus::Delivered->value]),
                    'previous_status' => TripStatus::OutForDelivery->value,
                    'new_status' => TripStatus::Delivered->value,
                    'actor_type' => 'system',
                    'actor_id' => null,
                    'created_at' => $baseTime->copy()->addHours(6),
                ];
            }

            // 4. epod_confirmed when status == completed
            if ($trip->status->value === TripStatus::Completed->value) {
                $events[] = [
                    'customer_id' => $trip->customer_id,
                    'trip_id' => $trip->id,
                    'event_type' => 'epod_confirmed',
                    'event_data' => json_encode([
                        'notes' => $trip->epod_confirmation_notes,
                    ]),
                    'previous_status' => TripStatus::Delivered->value,
                    'new_status' => TripStatus::Completed->value,
                    'actor_type' => 'user',
                    'actor_id' => $trip->epod_confirmed_by_id ?: $actorId,
                    'created_at' => $trip->trip_end_time ?: $baseTime->copy()->addHours(8),
                ];
            }

            TripEvent::insert($events);
        }

        // Count from the DB instead of the by-reference closure counter —
        // chunk()/foreach() with use(&) doesn't always propagate in this
        // seeder context, but the data we wrote is what we count.
        $finalCount = TripEvent::withoutGlobalScopes()->count();
        $this->command?->info("  TripEventSeeder: {$finalCount} total events in DB after seed.");
    }
}
