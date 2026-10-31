<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per character per corporation per day, holding that day's ticks in
 * a compact form: when each one paid, where, for how much and which kill group
 * it belongs to, with each group's kill list.
 *
 * Time and rats cannot be added up from totals. A player's alts ratting
 * together are one stretch of time and a fleet's shared payout is one set of
 * rats, and who is whose alt can change later, so both are worked out when a
 * page is read. They need the ticks themselves, not a sum of them.
 *
 * Filled by the import, the first run after this table arrives included.
 */
class CreateRattingManagerCharacterDays extends Migration
{
    private const TABLE = 'ratting_manager_character_days';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('date');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('corporation_id');
            $table->unsignedInteger('tick_count')->default(0);
            // JSON, read and written by CharacterDays and CharacterDayBuilder.
            // Kept apart so a page that only wants time does not decode kills.
            $table->mediumText('ticks');
            $table->mediumText('kills');
            $table->timestamps();

            $table->unique(['date', 'character_id', 'corporation_id'], 'rm_char_days_unique');
            $table->index(['corporation_id', 'date'], 'rm_char_days_corp_date');
            $table->index(['character_id', 'date'], 'rm_char_days_character_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
}
