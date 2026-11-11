<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for what the queries really ask of the two tables that grow with
 * every import. The tax rates read paired ESS payouts per corporation, as they
 * do ticks, and the nightly clear-out reads sessions by when they ended.
 *
 * The pair keys go: pairing looks a character's rows up by time, which another
 * index answers, and nothing else asks for them. On a table this busy every
 * index is paid for on each write.
 */
class TuneRattingManagerIndexes extends Migration
{
    private const ADD = [
        'ratting_manager_ess_payouts' => ['rm_ess_corp_kind_time' => ['corporation_id', 'kind', 'paid_at']],
        'ratting_manager_sessions' => ['rm_sessions_ended' => ['ended_at']],
    ];

    private const DROP = [
        'ratting_manager_ticks' => 'rm_ticks_pair_key',
        'ratting_manager_ess_payouts' => 'rm_ess_pair_key',
    ];

    public function up(): void
    {
        foreach (self::ADD as $tableName => $indexes) {
            foreach ($indexes as $index => $columns) {
                if (Schema::hasTable($tableName) && !$this->exists($tableName, $index)) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->index($columns, $index));
                }
            }
        }

        foreach (self::DROP as $tableName => $index) {
            if (Schema::hasTable($tableName) && $this->exists($tableName, $index)) {
                Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($index));
            }
        }
    }

    public function down(): void
    {
        foreach (self::ADD as $tableName => $indexes) {
            foreach (array_keys($indexes) as $index) {
                if (Schema::hasTable($tableName) && $this->exists($tableName, $index)) {
                    Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($index));
                }
            }
        }
    }

    /** Schema::hasIndex() only arrived in Laravel 11; SeAT 5 runs on 10. */
    private function exists(string $tableName, string $index): bool
    {
        return DB::select("SHOW INDEX FROM {$tableName} WHERE Key_name = ?", [$index]) !== [];
    }
}
