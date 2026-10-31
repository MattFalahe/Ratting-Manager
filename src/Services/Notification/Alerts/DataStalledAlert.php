<?php

namespace RattingManager\Services\Notification\Alerts;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Notification\Message;
use RattingManager\Services\Ratting\WalletGaps;

/**
 * The data stopped: a corporation's wallet journal has no new rows, or the
 * plugin's own import has stopped running. Posts once when it stops and
 * once when it is back.
 */
class DataStalledAlert extends Alert
{
    /** The import runs twice an hour, so three hours without one is not a missed run. */
    private const IMPORT_STALLED_HOURS = 3;

    /** A corporation that has not ratted in this long is not expected to have journal rows. */
    private const RATTED_DAYS = 7;

    public function type(): string
    {
        return 'data_stalled';
    }

    public function run(): int
    {
        if (!$this->notifier->isEnabled($this->type()) || $this->corporations() === []) {
            return 0;
        }

        $sent = 0;
        $hours = max(1, (int) $this->settings->get('notifications.stalled_hours'));

        foreach ($this->corporations() as $corporationId) {
            // Not max(): SeAT has no index on both columns, so that reads every
            // row the corporation has. This walks back from the newest day.
            $newest = DB::table('corporation_wallet_journals')->where('corporation_id', $corporationId)->orderByDesc('date')->limit(1)->value('date');
            $stalled = $newest === null || Carbon::parse($newest)->lt(Carbon::now()->subHours($hours));
            $key = 'data_stalled:corporation:' . $corporationId;
            $name = $this->corporationName($corporationId);

            if ($stalled && !$this->state->isOpen($key)) {
                $ratted = DB::table('ratting_manager_ticks')->where('corporation_id', $corporationId)->where('ticked_at', '>=', Carbon::now()->subDays(self::RATTED_DAYS))->exists();
                if (!$ratted && $newest !== null) {
                    continue;
                }
                $sent += $this->post($key, $corporationId, $this->stalledMessage($name, $newest), true);
            } elseif (!$stalled && $this->state->isOpen($key)) {
                $sent += $this->post($key, $corporationId, $this->resumedMessage($name, $newest), false);
            }
        }

        $last = $this->settings->get('state.last_process_at');
        $importStalled = $last === null || Carbon::parse($last)->lt(Carbon::now()->subHours(self::IMPORT_STALLED_HOURS));
        $key = 'data_stalled:import';
        if ($importStalled && !$this->state->isOpen($key)) {
            $sent += $this->post($key, null, $this->importMessage($last), true);
        } elseif (!$importStalled && $this->state->isOpen($key)) {
            $sent += $this->post($key, null, Message::forType($this->type(), trans('ratting-manager::notifications.import_resumed_title'))
                ->description(trans('ratting-manager::notifications.import_resumed_body', ['time' => self::eveTime($last)])), false);
        }

        return $sent;
    }

    public function sample(): Message
    {
        return $this->stalledMessage('Test Corp', Carbon::now()->subHours(14)->toDateTimeString());
    }

    private function post(string $key, ?int $corporationId, Message $message, bool $open): int
    {
        $result = $this->notifier->send($this->type(), $corporationId, $message);
        if (!$this->done($result)) {
            return 0;
        }

        $open ? $this->state->opened($key) : $this->state->close($key);

        return $result['delivered'] > 0 ? 1 : 0;
    }

    private function stalledMessage(string $name, ?string $newest): Message
    {
        $body = $newest === null
            ? trans('ratting-manager::notifications.stalled_never', ['corporation' => $name])
            : trans('ratting-manager::notifications.stalled_body', [
                'corporation' => $name,
                'time' => self::eveTime($newest),
                'hours' => (int) Carbon::parse($newest)->diffInHours(Carbon::now()),
                'by' => Carbon::parse($newest)->addDays(WalletGaps::ESI_DAYS)->format('Y-m-d'),
            ]);

        return Message::forType($this->type(), trans('ratting-manager::notifications.stalled_title', ['corporation' => $name]))->description($body);
    }

    private function resumedMessage(string $name, string $newest): Message
    {
        return Message::make(trans('ratting-manager::notifications.resumed_title', ['corporation' => $name]), 0x199E70)
            ->description(trans('ratting-manager::notifications.resumed_body', ['time' => self::eveTime($newest)]));
    }

    private function importMessage(?string $last): Message
    {
        return Message::forType($this->type(), trans('ratting-manager::notifications.import_stalled_title', [
            'hours' => $last === null ? self::IMPORT_STALLED_HOURS : (int) Carbon::parse($last)->diffInHours(Carbon::now()),
        ]))->description($last === null
            ? trans('ratting-manager::notifications.import_never_body')
            : trans('ratting-manager::notifications.import_stalled_body', ['time' => self::eveTime($last)]));
    }
}
