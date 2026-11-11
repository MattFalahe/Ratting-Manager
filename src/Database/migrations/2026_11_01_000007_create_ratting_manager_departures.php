<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a character left a tracked corporation, so a past month can say who has
 * gone and when. SeAT only holds corporation history for characters with a
 * token, and many ratters are only ever seen in the corporation journal, so
 * the plugin keeps its own record of the moves it has looked up.
 *
 * Filled in the background by ratting-manager:track-departures.
 */
class CreateRattingManagerDepartures extends Migration
{
    private const TABLE = 'ratting_manager_departures';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('corporation_id');
            $table->dateTime('left_at');
            $table->unsignedBigInteger('next_corporation_id')->nullable();
            // seat, esi or evewho: read from a corporation history, so exact.
            // seen: no history answered yet, so this is when the move was noticed.
            $table->string('source', 12);
            $table->timestamps();

            $table->unique(['character_id', 'corporation_id', 'left_at'], 'rm_departures_unique');
            $table->index(['corporation_id', 'left_at'], 'rm_departures_corp_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(self::TABLE);
    }
}
