<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Webhook deliveries are pruned, and counted for Diagnostics, by
 * attempted_at alone. The only index started with webhook_id, so both read
 * the whole table; this gives them an index of their own.
 */
class AddRattingManagerDeliveryTimeIndex extends Migration
{
    private const TABLE = 'ratting_manager_webhook_deliveries';
    private const INDEX = 'rm_deliveries_time';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE) || $this->hasIndex()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->index('attempted_at', self::INDEX);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE) || !$this->hasIndex()) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropIndex(self::INDEX);
        });
    }

    /** Schema::hasIndex() only arrived in Laravel 11; SeAT 5 runs on 10. */
    private function hasIndex(): bool
    {
        return DB::select('SHOW INDEX FROM ' . self::TABLE . ' WHERE Key_name = ?', [self::INDEX]) !== [];
    }
}
