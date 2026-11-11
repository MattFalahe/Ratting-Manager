<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * These went into the first migration after installs had already run it, and
 * that migration skips a table that exists, so those installs never got them.
 * Without them the import reads the whole tick table for each day it rebuilds.
 * rm_ticks_corp_time had two columns then and takes its third here. An install
 * that has them all has nothing to do.
 */
class AddRattingManagerMissingIndexes extends Migration
{
    private const INDEXES = [
        'ratting_manager_ticks' => [
            'rm_ticks_corp_time' => ['corporation_id', 'ticked_at', 'character_id'],
            'rm_ticks_corp_kind_time' => ['corporation_id', 'kind', 'ticked_at'],
            'rm_ticks_time' => ['ticked_at', 'character_id'],
        ],
        'ratting_manager_ess_payouts' => [
            'rm_ess_time' => ['paid_at'],
        ],
        'ratting_manager_special_spawns' => [
            'rm_spawns_character_time' => ['character_id', 'ticked_at'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            foreach ($indexes as $index => $columns) {
                $current = $this->columnsOf($tableName, $index);

                if ($current === $columns) {
                    continue;
                }

                Schema::table($tableName, function (Blueprint $table) use ($index, $columns, $current) {
                    if ($current !== []) {
                        $table->dropIndex($index);
                    }

                    $table->index($columns, $index);
                });
            }
        }
    }

    public function down(): void
    {
        // Forward-only. The first migration makes these on any newer install,
        // so taking them away here would leave that install without them.
    }

    /** Schema::hasIndex() only arrived in Laravel 11; SeAT 5 runs on 10. */
    private function columnsOf(string $tableName, string $index): array
    {
        return array_column(DB::select("SHOW INDEX FROM {$tableName} WHERE Key_name = ?", [$index]), 'Column_name');
    }
}
