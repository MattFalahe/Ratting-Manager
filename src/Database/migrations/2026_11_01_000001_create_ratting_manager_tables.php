<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every Ratting Manager table.
 *
 * A tick is one bounty payout. The corporation journal records the corp's
 * cut of it and the character journal what the character received, so the
 * same tick can arrive from either side, in either order. Ticks and ESS
 * payouts keep both journal ids, and a row is paired once both have been
 * seen.
 *
 * Index names are given by hand: Laravel's own names for the composite ones
 * run past MySQL's 64 characters.
 */
class CreateRattingManagerTables extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ratting_manager_settings')) {
            Schema::create('ratting_manager_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('key', 100);
                $table->unsignedBigInteger('corporation_id')->nullable();
                $table->text('value')->nullable();
                $table->timestamps();

                $table->unique(['key', 'corporation_id'], 'rm_settings_key_corp');
            });
        }

        if (!Schema::hasTable('ratting_manager_ticks')) {
            Schema::create('ratting_manager_ticks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('character_id');
                $table->unsignedBigInteger('corporation_id');
                $table->unsignedInteger('solar_system_id');
                $table->dateTime('ticked_at');
                // paired, character (no corp row yet, or a 0% corp) or
                // corporation (the character holds no wallet token).
                $table->string('kind', 12);
                $table->unsignedBigInteger('character_journal_id')->nullable();
                $table->unsignedBigInteger('corporation_journal_id')->nullable();
                $table->decimal('net_amount', 20, 2)->default(0);
                $table->decimal('corp_tax_amount', 20, 2)->nullable();
                $table->boolean('net_is_estimated')->default(false);
                // The corporation's rate the net was worked out from, kept so a
                // trace can show the sum.
                $table->decimal('tax_rate', 6, 4)->nullable();
                // Same time, system and kill list: fleet members who shared a
                // payout. Kills are counted once per group.
                $table->char('kill_group', 40)->nullable();
                $table->unsignedInteger('npc_kills')->default(0);
                $table->unsignedBigInteger('session_id')->nullable();
                $table->timestamps();

                $table->unique('character_journal_id', 'rm_ticks_char_journal');
                $table->unique('corporation_journal_id', 'rm_ticks_corp_journal');
                $table->index(['character_id', 'ticked_at'], 'rm_ticks_character_time');
                // character_id rides along so who ratted when, for gone quiet
                // and the like, is read from the index without the rows.
                $table->index(['corporation_id', 'ticked_at', 'character_id'], 'rm_ticks_corp_time');
                $table->index(['corporation_id', 'kind', 'ticked_at'], 'rm_ticks_corp_kind_time');
                // The pipeline rebuilds days and sessions for every
                // corporation at once, by time alone.
                $table->index(['ticked_at', 'character_id'], 'rm_ticks_time');
                $table->index(['solar_system_id', 'ticked_at'], 'rm_ticks_system_time');
                $table->index(['character_id', 'solar_system_id', 'ticked_at'], 'rm_ticks_pair_key');
                $table->index('kill_group', 'rm_ticks_kill_group');
                $table->index('session_id', 'rm_ticks_session');
            });
        }

        if (!Schema::hasTable('ratting_manager_tick_kills')) {
            Schema::create('ratting_manager_tick_kills', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tick_id');
                $table->unsignedInteger('npc_type_id');
                $table->unsignedInteger('quantity');

                $table->unique(['tick_id', 'npc_type_id'], 'rm_tick_kills_tick_type');
                $table->index('npc_type_id', 'rm_tick_kills_type');
            });
        }

        if (!Schema::hasTable('ratting_manager_ess_payouts')) {
            Schema::create('ratting_manager_ess_payouts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('character_id');
                $table->unsignedBigInteger('corporation_id');
                $table->unsignedInteger('solar_system_id')->nullable();
                $table->dateTime('paid_at');
                $table->string('kind', 12);
                $table->unsignedBigInteger('character_journal_id')->nullable();
                $table->unsignedBigInteger('corporation_journal_id')->nullable();
                $table->decimal('net_amount', 20, 2)->default(0);
                $table->decimal('corp_tax_amount', 20, 2)->nullable();
                $table->boolean('net_is_estimated')->default(false);
                $table->decimal('tax_rate', 6, 4)->nullable();
                $table->timestamps();

                $table->unique('character_journal_id', 'rm_ess_char_journal');
                $table->unique('corporation_journal_id', 'rm_ess_corp_journal');
                $table->index(['character_id', 'paid_at'], 'rm_ess_character_time');
                $table->index(['corporation_id', 'paid_at'], 'rm_ess_corp_time');
                $table->index('paid_at', 'rm_ess_time');
                $table->index(['character_id', 'solar_system_id', 'paid_at'], 'rm_ess_pair_key');
            });
        }

        if (!Schema::hasTable('ratting_manager_sessions')) {
            Schema::create('ratting_manager_sessions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('character_id');
                $table->unsignedBigInteger('corporation_id');
                // 20 minutes before the first tick, since a tick pays for the
                // kills of the 20 minutes before it.
                $table->dateTime('started_at');
                $table->dateTime('ended_at');
                $table->unsignedInteger('minutes');
                $table->unsignedInteger('tick_count');
                $table->unsignedInteger('primary_system_id');
                $table->unsignedSmallInteger('system_count');
                $table->decimal('net_amount', 20, 2)->default(0);
                $table->decimal('estimated_amount', 20, 2)->default(0);
                $table->unsignedInteger('npc_kills')->default(0);
                $table->decimal('isk_per_hour', 20, 2)->default(0);
                $table->timestamps();

                $table->index(['character_id', 'started_at'], 'rm_sessions_character_start');
                $table->index(['corporation_id', 'started_at'], 'rm_sessions_corp_start');
            });
        }

        if (!Schema::hasTable('ratting_manager_daily_summaries')) {
            Schema::create('ratting_manager_daily_summaries', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->date('date');
                $table->unsignedBigInteger('character_id');
                $table->unsignedBigInteger('corporation_id');
                $table->unsignedInteger('solar_system_id');
                $table->unsignedInteger('ticks')->default(0);
                $table->unsignedInteger('minutes')->default(0);
                // *_net includes the part worked out from the corporation's
                // cut; *_estimated is that part on its own.
                $table->decimal('bounty_net', 20, 2)->default(0);
                $table->decimal('bounty_estimated', 20, 2)->default(0);
                $table->decimal('bounty_corp_tax', 20, 2)->default(0);
                $table->decimal('ess_net', 20, 2)->default(0);
                $table->decimal('ess_estimated', 20, 2)->default(0);
                $table->decimal('ess_corp_tax', 20, 2)->default(0);
                $table->unsignedInteger('npc_kills')->default(0);
                $table->timestamps();

                $table->unique(['date', 'character_id', 'corporation_id', 'solar_system_id'], 'rm_daily_unique');
                $table->index(['corporation_id', 'date'], 'rm_daily_corp_date');
                $table->index(['character_id', 'date'], 'rm_daily_character_date');
            });
        }

        if (!Schema::hasTable('ratting_manager_daily_kills')) {
            Schema::create('ratting_manager_daily_kills', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->date('date');
                $table->unsignedBigInteger('corporation_id');
                $table->unsignedInteger('solar_system_id');
                $table->unsignedInteger('npc_type_id');
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('characters');
                $table->timestamps();

                $table->unique(['date', 'corporation_id', 'solar_system_id', 'npc_type_id'], 'rm_daily_kills_unique');
                $table->index(['corporation_id', 'date'], 'rm_daily_kills_corp_date');
            });
        }

        if (!Schema::hasTable('ratting_manager_npc_types')) {
            Schema::create('ratting_manager_npc_types', function (Blueprint $table) {
                $table->unsignedInteger('type_id')->primary();
                $table->string('name', 128)->nullable();
                $table->unsignedInteger('group_id')->nullable();
                $table->string('group_name', 128)->nullable();
                $table->string('faction', 24);
                $table->string('size', 12);
                $table->string('rank', 12);
                $table->double('mass')->nullable();
                // registry, group, mass or unknown: how the size was decided.
                $table->string('classified_by', 12);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ratting_manager_special_spawns')) {
            Schema::create('ratting_manager_special_spawns', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('kill_group', 40);
                $table->unsignedInteger('npc_type_id');
                $table->unsignedInteger('quantity');
                $table->unsignedBigInteger('tick_id');
                $table->unsignedBigInteger('character_id');
                $table->unsignedBigInteger('corporation_id');
                $table->unsignedInteger('solar_system_id');
                $table->dateTime('ticked_at');
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();

                $table->unique(['kill_group', 'npc_type_id'], 'rm_spawns_group_type');
                $table->index(['corporation_id', 'ticked_at'], 'rm_spawns_corp_time');
                $table->index(['character_id', 'ticked_at'], 'rm_spawns_character_time');
            });
        }

        if (!Schema::hasTable('ratting_manager_corp_tax_rates')) {
            Schema::create('ratting_manager_corp_tax_rates', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('corporation_id');
                $table->date('date');
                $table->decimal('tax_rate', 6, 4);
                // paired: read back from that day's paired rows; snapshot: the
                // corporation's rate as SeAT had it that day.
                $table->string('source', 10);
                $table->unsignedInteger('paired_rows')->default(0);
                $table->timestamps();

                $table->unique(['corporation_id', 'date'], 'rm_tax_rates_corp_date');
            });
        }

        // Names and corporations for ratters SeAT does not know: members who
        // never added a token, seen only in the corporation journal. Filled
        // from journal descriptions and the background lookup, and only ever
        // read by pages.
        if (!Schema::hasTable('ratting_manager_character_affiliations')) {
            Schema::create('ratting_manager_character_affiliations', function (Blueprint $table) {
                $table->unsignedBigInteger('character_id')->primary();
                $table->string('character_name', 64)->nullable();
                $table->unsignedBigInteger('corporation_id')->nullable();
                $table->string('corporation_name', 128)->nullable();
                $table->unsignedBigInteger('alliance_id')->nullable();
                // journal, esi, evewho or zkillboard; pending while a page
                // waits for a first answer; invalid for an id ESI rejects.
                $table->string('source', 16);
                $table->timestamp('requested_at')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('expires_at')->nullable();

                $table->index(['source', 'expires_at'], 'rm_char_affil_source_expires');
                $table->index('corporation_id', 'rm_char_affil_corporation');
            });
        }

        // Notifications, laid out as Structure Manager's: webhooks, the
        // notification types with a master switch and a default role, and the
        // bindings between them, each able to override the role. Nothing is
        // bound on install; an admin binds what each webhook should carry.
        if (!Schema::hasTable('ratting_manager_webhooks')) {
            Schema::create('ratting_manager_webhooks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('description', 255)->nullable();
                $table->string('webhook_url', 500);
                // Null sends for every configured corporation.
                $table->unsignedBigInteger('corporation_id')->nullable();
                $table->boolean('enabled')->default(true);
                $table->timestamps();

                $table->index('corporation_id', 'rm_webhooks_corporation');
            });
        }

        if (!Schema::hasTable('ratting_manager_notification_types')) {
            Schema::create('ratting_manager_notification_types', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('type_key', 64);
                $table->boolean('enabled')->default(true);
                $table->string('role_mention', 100)->nullable();
                // Where the role was picked from: manual, discord-roles-table,
                // seat-connector or warlof-discord.
                $table->string('role_source', 32)->nullable();
                $table->string('role_id', 32)->nullable();
                $table->timestamps();

                $table->unique('type_key', 'rm_notification_types_key');
            });

            // Names and descriptions live in the language files; the rows only
            // hold what an admin can change.
            $now = now();
            DB::table('ratting_manager_notification_types')->insertOrIgnore(array_map(fn ($key) => [
                'type_key' => $key, 'enabled' => true, 'created_at' => $now, 'updated_at' => $now,
            ], [
                'special_spawn', 'data_stalled', 'gone_quiet', 'untracked_ratter', 'outsiders', 'ship_lost',
                'director_report_weekly', 'director_report_monthly', 'rankings_weekly', 'rankings_monthly',
            ]));
        }

        if (!Schema::hasTable('ratting_manager_type_webhook')) {
            Schema::create('ratting_manager_type_webhook', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('type_id');
                $table->unsignedBigInteger('webhook_id');
                $table->boolean('enabled')->default(true);
                // Null takes the type's default; "none" pings nobody here.
                $table->string('role_mention', 100)->nullable();
                $table->string('role_source', 32)->nullable();
                $table->string('role_id', 32)->nullable();
                $table->timestamps();

                $table->unique(['type_id', 'webhook_id'], 'rm_type_webhook_unique');
                $table->index('webhook_id', 'rm_type_webhook_webhook');
            });
        }

        // Every send, kept so Diagnostics can show each webhook's delivery
        // health. Not tied to the webhook by a foreign key, so deleting a
        // webhook keeps its history.
        if (!Schema::hasTable('ratting_manager_webhook_deliveries')) {
            Schema::create('ratting_manager_webhook_deliveries', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('webhook_id');
                $table->timestamp('attempted_at')->nullable();
                // 0 when no response came back at all.
                $table->smallInteger('status_code')->default(0);
                $table->boolean('success');
                $table->unsignedInteger('duration_ms')->nullable();
                $table->string('type_key', 64)->nullable();
                $table->string('payload_summary', 255)->nullable();
                $table->string('error_message', 500)->nullable();

                $table->index(['webhook_id', 'attempted_at'], 'rm_deliveries_webhook_time');
            });
        }

        // One row per thing an alert has been sent for, so the same event is
        // never sent twice. A new event is a new key, so nothing is held back.
        if (!Schema::hasTable('ratting_manager_alert_state')) {
            Schema::create('ratting_manager_alert_state', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('alert_key', 191);
                // open while the condition holds, closed once it has passed.
                $table->string('state', 16);
                $table->text('payload')->nullable();
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_sent_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->unique('alert_key', 'rm_alert_state_key');
            });
        }

        // NPC kills per system per hour from public ESI, for the systems the
        // corporation rats in, so outsiders ratting there can be spotted.
        if (!Schema::hasTable('ratting_manager_system_kills')) {
            Schema::create('ratting_manager_system_kills', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('solar_system_id');
                $table->dateTime('hour');
                $table->unsignedInteger('npc_kills')->default(0);
                $table->unsignedInteger('ship_kills')->default(0);
                $table->unsignedInteger('pod_kills')->default(0);
                $table->timestamps();

                $table->unique(['solar_system_id', 'hour'], 'rm_system_kills_unique');
                $table->index('hour', 'rm_system_kills_hour');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ratting_manager_system_kills');
        Schema::dropIfExists('ratting_manager_alert_state');
        Schema::dropIfExists('ratting_manager_webhook_deliveries');
        Schema::dropIfExists('ratting_manager_type_webhook');
        Schema::dropIfExists('ratting_manager_notification_types');
        Schema::dropIfExists('ratting_manager_webhooks');
        Schema::dropIfExists('ratting_manager_character_affiliations');
        Schema::dropIfExists('ratting_manager_corp_tax_rates');
        Schema::dropIfExists('ratting_manager_special_spawns');
        Schema::dropIfExists('ratting_manager_npc_types');
        Schema::dropIfExists('ratting_manager_daily_kills');
        Schema::dropIfExists('ratting_manager_daily_summaries');
        Schema::dropIfExists('ratting_manager_sessions');
        Schema::dropIfExists('ratting_manager_ess_payouts');
        Schema::dropIfExists('ratting_manager_tick_kills');
        Schema::dropIfExists('ratting_manager_ticks');
        Schema::dropIfExists('ratting_manager_settings');
    }
}
