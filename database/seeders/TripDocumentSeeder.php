<?php

namespace Database\Seeders;

use App\Enums\TripDocType;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\TripDocument;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Attaches a few sample trip documents to every seeded trip:
 *   - e_way_bill + supporting for active/in-transit trips
 *   - e_pod + supporting for completed trips
 *
 * The seed data uses synthetic PDF URLs that point into the local
 * `public/storage/...` namespace — the paths won't resolve in local dev
 * because no actual file is uploaded, but the API response shape and
 * field semantics are identical to what a real upload produces.
 *
 * Idempotent: skips trips that already have any document.
 */
class TripDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // Trip has a TenantScope global; seeder runs as platform admin so
        // we bypass it to see trips across all customers. TripDocument
        // (used by the exists() check) carries the same scope.
        $trips = Trip::withoutGlobalScopes()->get();

        foreach ($trips as $trip) {
            if (TripDocument::withoutGlobalScopes()->where('trip_id', $trip->id)->exists()) {
                continue;
            }

            $actorId = User::where('customer_id', $trip->customer_id)
                ->whereHas('role', fn($q) => $q->where('name', 'customer_admin'))
                ->value('id')
                ?: User::where('email', 'admin@admin.com')->value('id');

            $docs = [
                [
                    'doc_type' => TripDocType::EWayBill->value,
                    'file_name' => $trip->trip_ref . '_e_way_bill.pdf',
                    'created_at' => $trip->created_at ?: $now,
                ],
                [
                    'doc_type' => TripDocType::Supporting->value,
                    'file_name' => $trip->trip_ref . '_invoice.pdf',
                    'created_at' => $trip->created_at?->copy()->addHour() ?: $now,
                ],
            ];

            // $trip->status is a cast TripStatus enum; compare ->value.
            if ($trip->status->value === TripStatus::Completed->value) {
                $docs[] = [
                    'doc_type' => TripDocType::EPod->value,
                    'file_name' => $trip->trip_ref . '_e_pod.pdf',
                    'created_at' => $trip->trip_end_time ?: $now,
                ];
            }

            $records = array_map(fn(array $d) => [
                'trip_id' => $trip->id,
                'customer_id' => $trip->customer_id,
                'uploaded_by_id' => $actorId,
                'doc_type' => $d['doc_type'],
                'file_name' => $d['file_name'],
                // Synthesize a plausible storage path. The path won't
                // resolve because the file isn't actually uploaded,
                // but the API contract is unchanged.
                'url' => 'trips/' . $trip->id . '/documents/' . Str::random(40) . '.pdf',
                'created_at' => $d['created_at'],
            ], $docs);

            TripDocument::insert($records);
        }

        // Count from the DB — by-reference counter in foreach() doesn't
        // reliably propagate in this seeder context.
        $finalCount = TripDocument::withoutGlobalScopes()->count();
        $this->command?->info("  TripDocumentSeeder: {$finalCount} total documents in DB after seed.");
    }
}
