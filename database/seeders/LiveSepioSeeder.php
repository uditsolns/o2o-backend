<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Loads the testing-environment dataset (database/o2o.sql) into a freshly
 * migrated database.
 *
 * SECURITY
 * --------
 * The SQL file lives outside the repo — the operator drops it into the
 * project root (database/o2o.sql or database/o2o.local.sql) and removes
 * it after seeding. It contains real Sepio JWTs and encrypted
 * credentials, which is why we do not commit it.
 *
 * SCHEMA DRIFT HANDLING
 * ---------------------
 * database/o2o.sql was generated against an EARLIER schema, before the
 * recent migration squash. So:
 *   - Some columns referenced in the SQL may have been renamed or
 *     dropped. We strip unknown columns before insert.
 *   - Some enum values used in the SQL may no longer be valid
 *     (e.g. the old `mfg_rejected` onboarding_status, or the old
 *     `job` actor_type). We coerce these to known-safe defaults
 *     listed in $coercions below.
 *   - Some tables in the SQL may not exist in the current schema.
 *     We skip their inserts cleanly and log it.
 *
 * We do NOT try to be too clever — if a column is missing we just
 * drop it from the row, and if a value won't fit we coerce it. Any
 * rows that fail FK constraints or unique key violations are skipped
 * and reported.
 *
 * IDEMPOTENCY
 * -----------
 * The seeder TRUNCATEs the data-bearing tables before inserting, with
 * foreign key checks disabled. This makes a re-run produce the same
 * dataset (caller should still prefer running on a fresh database).
 *
 * PARSING
 * -------
 * The file uses phpMyAdmin format. We split on `;` at line ends and
 * pick out lines starting with `INSERT INTO`. The values block uses
 * tuples separated by `,` — we split on the same delimiter, taking
 * care of nested parens. This handles multi-line inserts and embedded
 * escaped quotes in string values without needing a full SQL parser.
 *
 * USAGE
 * -----
 *   php artisan migrate:fresh --seed --seeder=Database\\Seeders\\LiveSepioSeeder
 *
 * Or after a fresh DB is in place, run just this seeder:
 *   php artisan db:seed --class=Database\\Seeders\\LiveSepioSeeder
 */
class LiveSepioSeeder extends Seeder
{
    /** Tables in the SQL dump that hold business data — only these get truncated. */
    private const BUSINESS_TABLES = [
        'authorized_signatories',
        'customer_consignees',
        'customer_consignors',
        'customer_documents',
        'customer_locations',
        'customer_onboarding_history',
        'customer_ports',
        'customer_routes',
        'customer_sepio_history',
        'customer_wallet_transactions',
        'customer_wallets',
        'customers',
        'permissions',
        'ports',
        'role_has_permissions',
        'roles',
        'seal_order_history',
        'seal_orders',
        'seal_pricing_tiers',
        'seals',
        'trip_container_tracking',
        'trip_events',
        'trip_shipment_milestones',
        'trip_tracking_points',
        'trips',
        'users',
    ];

    /**
     * Map of column → coerced-value. Used to map legacy enum values that
     * the SQL still emits but our current schema no longer accepts.
     *
     * Format: ['table_name' => ['column_name' => ['legacy_value' => 'new_value']]]
     */
    private const COERCIONS = [
        'customers' => [
            'onboarding_status' => [
                'mfg_rejected' => 'il_rejected', // collapsed by migration 2026_05_25
            ],
        ],
        'customer_sepio_history' => [
            // SQL predates the actor_type collapse — old value 'job' -> 'system'
            'actor_type' => [
                'job' => 'system',
            ],
        ],
        'seal_order_history' => [
            'actor_type' => [
                'job' => 'system',
            ],
        ],
    ];

    public function run(): void
    {
        $path = $this->locateSqlFile();

        if (!$path) {
            $this->command->error('');
            $this->command->error('  ❌  No SQL data file found.');
            $this->command->error('     Drop database/o2o.sql (or database/o2o.local.sql) into the project root and re-run.');
            $this->command->error('');
            return;
        }

        $this->command->info('');
        $this->command->info("  📄  Loading live data from {$path}…");

        $sql = File::get($path);

        DB::transaction(function () use ($sql) {
            // Truncate business data tables in FK-safe order (children first)
            // with FK checks disabled so we can wipe regardless of order.
            DB::statement('SET FOREIGN_KEY_CHECKS = 0');
            foreach (self::BUSINESS_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');

            $statements = $this->parseInserts($sql);

            $stats = ['inserted' => 0, 'skipped_unknown_table' => 0, 'skipped_unknown_col' => 0, 'skipped_fk' => 0];

            foreach ($statements as $stmt) {
                $table = $this->extractTableName($stmt);
                if ($table === null) {
                    continue;
                }
                if (!Schema::hasTable($table)) {
                    $stats['skipped_unknown_table']++;
                    continue;
                }
                if (!in_array($table, self::BUSINESS_TABLES, true)) {
                    // Framework tables (migrations, failed_jobs, sessions,
                    // personal_access_tokens) — leave them alone.
                    continue;
                }

                [$columns, $rows] = $this->parseStatement($stmt);
                if (empty($rows)) {
                    continue;
                }

                // Filter and coerce each row to the table's current columns.
                $currentColumns = Schema::getColumnListing($table);
                $columnSet = array_flip($currentColumns);
                $tableCoercions = self::COERCIONS[$table] ?? [];

                $preparedRows = [];
                $skippedThisStatement = 0;

                foreach ($rows as $row) {
                    $prepared = [];
                    foreach ($columns as $i => $col) {
                        if (!isset($columnSet[$col])) {
                            // Column was dropped in a later migration — skip it.
                            continue;
                        }
                        $value = $row[$i] ?? null;
                        if ($value !== null && isset($tableCoercions[$col][$value])) {
                            $value = $tableCoercions[$col][$value];
                        }
                        $prepared[$col] = $value;
                    }
                    $preparedRows[] = $prepared;
                }

                if (empty($preparedRows)) {
                    $stats['skipped_unknown_col']++;
                    continue;
                }

                // Insert one at a time so a single bad row doesn't sink the
                // whole batch. FK violations are skipped and reported; other
                // errors halt the seeder so the operator can investigate.
                foreach ($preparedRows as $row) {
                    try {
                        DB::table($table)->insert($row);
                        $stats['inserted']++;
                    } catch (\Illuminate\Database\QueryException $e) {
                        if ($this->isForeignKeyOrUniqueError($e)) {
                            $skippedThisStatement++;
                            continue;
                        }
                        throw $e;
                    }
                }

                if ($skippedThisStatement > 0) {
                    $stats['skipped_fk'] += $skippedThisStatement;
                    $this->command->warn("  ⚠️  {$table}: skipped {$skippedThisStatement} row(s) due to FK or unique key violations");
                }
            }

            $this->command->info("  LiveSepioSeeder: inserted={$stats['inserted']} rows");
            if ($stats['skipped_unknown_table'] > 0) {
                $this->command->warn("                   skipped (unknown table): {$stats['skipped_unknown_table']} statements");
            }
            if ($stats['skipped_unknown_col'] > 0) {
                $this->command->warn("                   skipped (no surviving columns): {$stats['skipped_unknown_col']} statements");
            }
            if ($stats['skipped_fk'] > 0) {
                $this->command->warn("                   skipped (FK or unique key): {$stats['skipped_fk']} rows");
            }
        });
    }

    /**
     * Resolve which SQL file to load. Accepts both o2o.sql and o2o.local.sql
     * so the operator can use whichever name fits their deployment process.
     */
    private function locateSqlFile(): ?string
    {
        foreach (['database/o2o.sql', 'database/o2o.local.sql'] as $candidate) {
            if (File::exists($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    /**
     * Parse the file into individual INSERT statements.
     *
     * @return string[]
     */
    private function parseInserts(string $sql): array
    {
        $statements = [];
        $chunks = preg_split('/;\s*\n/', $sql);

        foreach ($chunks as $chunk) {
            $trimmed = trim($chunk);
            if (!preg_match('/^INSERT\s+INTO\s+/i', $trimmed)) {
                continue;
            }
            $statements[] = $trimmed . ';';
        }

        return $statements;
    }

    /**
     * Extract the target table name from an INSERT INTO `table_name` (...) statement.
     */
    private function extractTableName(string $stmt): ?string
    {
        if (!preg_match('/^INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i', $stmt, $m)) {
            return null;
        }
        return $m[1];
    }

    /**
     * Parse a single INSERT statement into [column_names, rows].
     *
     * Handles multi-line inserts, escaped quotes inside string values, and
     * `NULL` (unquoted) as well as `null` (lowercase). Numeric values are
     * returned as-is (string or int); the database casts them on insert.
     *
     * @return array{0: string[], 1: array<int, array<int, string|null>>}
     */
    private function parseStatement(string $stmt): array
    {
        if (!preg_match('/^INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?\s*\(([^)]+)\)\s*VALUES\s*(.+);?$/is', $stmt, $m)) {
            return [[], []];
        }

        // Parse column list (backticked identifiers separated by commas)
        $columnList = $m[2];
        preg_match_all('/`?([a-zA-Z0-9_]+)`?/', $columnList, $colMatches);
        $columns = $colMatches[1];

        // Parse VALUES — split on top-level commas only (respect nested parens).
        $valuesBlock = $m[3];
        $rows = $this->splitTopLevelTuples($valuesBlock);

        return [$columns, $rows];
    }

    /**
     * Split a VALUES block like "(1, 'a'), (2, 'b')" into per-row string arrays.
     *
     * @return array<int, array<int, string|null>>
     */
    private function splitTopLevelTuples(string $valuesBlock): array
    {
        $rows = [];
        $row = [];
        $value = '';
        $inString = false;
        $escape = false;
        $depth = 0;
        $len = strlen($valuesBlock);

        for ($i = 0; $i < $len; $i++) {
            $c = $valuesBlock[$i];

            if ($inString) {
                if ($escape) {
                    $value .= $c;
                    $escape = false;
                } elseif ($c === '\\') {
                    $value .= $c;
                    $escape = true;
                } elseif ($c === "'") {
                    $value .= $c;
                    $inString = false;
                } else {
                    $value .= $c;
                }
                continue;
            }

            if ($c === "'") {
                $value .= $c;
                $inString = true;
                continue;
            }
            if ($c === '(') {
                $depth++;
                $value .= $c;
                continue;
            }
            if ($c === ')') {
                $depth--;
                $value .= $c;
                if ($depth === 0) {
                    $row[] = $this->decodeValue($value);
                    $rows[] = $row;
                    $row = [];
                    $value = '';
                }
                continue;
            }
            if ($c === ',' && $depth === 0) {
                continue;
            }
            if ($c === ',' && $depth === 1) {
                $row[] = $this->decodeValue($value);
                $value = '';
                continue;
            }
            $value .= $c;
        }

        return $rows;
    }

    /**
     * Decode a single SQL value: NULL, integer, decimal, or quoted string.
     * Returns null for NULL (both 'NULL' and 'null' forms), the integer as
     * a string, and quoted strings as their raw contents (without the
     * surrounding quotes — Laravel will cast to JSON/etc on insert).
     */
    private function decodeValue(string $raw): ?string
    {
        $value = trim($raw);

        if ($value === 'NULL' || $value === 'null') {
            return null;
        }

        // Quoted string: strip surrounding quotes; unescape \' and \\.
        if (strlen($value) >= 2 && $value[0] === "'" && $value[strlen($value) - 1] === "'") {
            $inner = substr($value, 1, -1);
            $inner = str_replace(["\\'", "\\\\"], ["'", "\\"], $inner);
            return $inner;
        }

        return $value;
    }

    /**
     * Identify QueryExceptions caused by foreign key or unique key violations,
     * which we want to skip silently. Other errors halt the seeder so the
     * operator can investigate.
     */
    private function isForeignKeyOrUniqueError(\Illuminate\Database\QueryException $e): bool
    {
        // MySQL error codes: 1451 (cannot delete parent), 1452 (cannot add child),
        // 1062 (duplicate key for unique index), 1216 (FK violation), 1217 (FK violation).
        $code = (int) $e->errorInfo[1] ?? 0;
        return in_array($code, [1062, 1216, 1217, 1451, 1452], true);
    }
}
