<?php

namespace RattingManager\Services\Notification\Alerts;

use Carbon\Carbon;
use RattingManager\Services\Notification\Message;
use RattingManager\Services\Ratting\WalletGaps;

/**
 * A ratter's wallet token has gone. One post per corporation lists the
 * characters new to the list, and once more when a character has a week left
 * before ESI starts to lose their oldest days for good. Closed when the
 * token is back, so losing it again is news again.
 */
class WalletLostAlert extends Alert
{
    /** When a character gets their second post. */
    private const REMINDER_DAYS = 7;

    public function type(): string
    {
        return 'wallet_lost';
    }

    public function run(): int
    {
        if (!$this->notifier->isEnabled($this->type())) {
            return 0;
        }

        $gaps = app(WalletGaps::class);
        $sent = 0;

        foreach ($this->corporations() as $corporationId) {
            $prefix = 'wallet_lost:' . $corporationId . ':';
            $reminder = 'wallet_lost_soon:' . $corporationId . ':';
            $open = $this->state->open($prefix);
            $reminded = $this->state->open($reminder);

            $current = [];
            foreach ($gaps->forCorporations([$corporationId]) as $gap) {
                $current[$gap['character_id']] = $gap;
            }

            foreach ($open + $reminded as $key => $payload) {
                $id = (int) substr($key, strrpos($key, ':') + 1);
                if (!isset($current[$id])) {
                    $this->state->close($key);
                    unset($open[$key], $reminded[$key]);
                }
            }

            $post = [];
            $keys = [];
            foreach ($current as $id => $gap) {
                if (!isset($open[$prefix . $id])) {
                    $post[$id] = $gap;
                    $keys[] = $prefix . $id;
                }
                if ($gap['days_left'] <= self::REMINDER_DAYS && !isset($reminded[$reminder . $id])) {
                    $post[$id] = $gap;
                    $keys[] = $reminder . $id;
                }
            }

            if ($post === []) {
                continue;
            }

            $result = $this->notifier->send($this->type(), $corporationId, $this->message($this->corporationName($corporationId), array_values($post)));
            if ($this->done($result)) {
                foreach ($keys as $key) {
                    $this->state->opened($key);
                }
                $sent += $result['delivered'] > 0 ? 1 : 0;
            }
        }

        return $sent;
    }

    public function sample(): Message
    {
        return $this->message('Test Corp', [[
            'name' => 'Purpleshadez',
            'state' => 'removed',
            'since' => Carbon::now()->subDays(4)->toDateTimeString(),
            'ticks' => 12,
            'worked_out' => 92560000.0,
            'fix_by' => Carbon::now()->addDays(26)->toDateTimeString(),
            'days_left' => 26,
        ]]);
    }

    private function message(string $corporation, array $gaps): Message
    {
        $lines = array_map(fn ($gap) => trans($gap['days_left'] > 0 ? 'ratting-manager::notifications.wallet_lost_line' : 'ratting-manager::notifications.wallet_lost_line_late', [
            'name' => $gap['name'],
            'date' => Carbon::parse($gap['since'])->format('Y-m-d'),
            'ticks' => number_format($gap['ticks']),
            'isk' => self::isk($gap['worked_out']),
            'by' => Carbon::parse($gap['fix_by'])->format('Y-m-d'),
        ]), $gaps);

        return Message::forType($this->type(), trans('ratting-manager::notifications.wallet_lost_title', ['corporation' => $corporation, 'count' => count($gaps)]))
            ->description(trans('ratting-manager::notifications.wallet_lost_body') . "\n\n" . self::lines($lines));
    }
}
