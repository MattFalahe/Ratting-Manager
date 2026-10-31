<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The daily summaries added up per month, so a long period reads one row per
 * month for each character and system instead of one per day.
 *
 * Only what the pages read from the daily rows is carried over. The current
 * month is kept up to date with the days it is made of, and a month the
 * import changes again later is added up again.
 *
 * Filled by the import, the first run after this table arrives included.
 */
class CreateRattingManagerMonthlySummaries extends Migration
{
    private const TABLE = 'ratting_manager_monthly_summaries';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->bigIncrements('id');
            // YYYY-MM.
            $table->char('month', 7);
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('corporation_id');
            $table->unsignedInteger('solar_system_id');
            $table->unsignedInteger('ticks')->default(0);
            $table->decimal('bounty_net', 20, 2)->default(0);
            $table->decimal('bounty_estimated', 20, 2)->default(0);
            $table->decimal('bounty_corp_tax', 20, 2)->default(0);
            $table->decimal('ess_net', 20, 2)->default(0);
            $table->decimal('ess_estimated', 20, 2)->default(0);
            $table->decimal('ess_corp_tax', 20, 2)->default(0);
            $table->timestamps();

            $table->unique(['month', 'character_id', 'corporation_id', 'solar_system_id'], 'rm_monthly_unique');
            $table->index(['corporation_id', 'month'], 'rm_monthly_corp_month');
            $table->index(['character_id', 'month'], 'rm_monthly_character_month');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
}
