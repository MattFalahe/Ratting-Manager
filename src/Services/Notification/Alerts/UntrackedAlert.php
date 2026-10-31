<?php

namespace RattingManager\Services\Notification\Alerts;

use Carbon\Carbon;
use RattingManager\Services\Notification\Message;
use RattingManager\Services\Ratting\RattingMetrics;

/**
 * Ratters the corporation journal pays who have no wallet token on SeAT.
 * One post per corporation lists the characters not posted before; each is
 * posted once, and again only if they add a token and later lose it.
 */
class UntrackedAlert extends Alert
{
    /** Only characters who ratted this recently, so a long-gone ratter is not news. */
    private const RECENT_DAYS = 30;

    public function type(): string
    {
        return 'untracked_ratter';
    }

    public function run(): int
    {
        if (!$this->notifier->isEnabled($this->type())) {
            return 0;
        }

        $metrics = app(RattingMetrics::class);
        $sent = 0;

        foreach ($this->corporations() as $corporationId) {
            $prefix = 'untracked:' . $corporationId . ':';
            $open = $this->state->open($prefix);

            foreach ($open as $key => $payload) {
                if ($this->players->hasWalletToken((int) substr($key, strlen($prefix)))) {
                    $this->state->close($key);
                    unset($open[$key]);
                }
            }

            $untracked = [];
            foreach ($metrics->untracked([$corporationId], self::RECENT_DAYS) as $character) {
                $untracked[$prefix . $character['character_id']] = $character;
            }

            $new = array_diff_key($untracked, $open);
            if ($new === []) {
                continue;
            }

            $result = $this->notifier->send($this->type(), $corporationId, $this->message($this->corporationName($corporationId), array_values($new)));
            if ($this->done($result)) {
                foreach ($new as $key => $character) {
                    $this->state->opened($key);
                }
                $sent += $result['delivered'] > 0 ? 1 : 0;
            }
        }

        return $sent;
    }

    public function sample(): Message
    {
        return $this->message('Test Corp', [
            ['name' => 'Purpleshadez', 'ticks' => 5, 'net' => 42560000.0, 'last_tick' => Carbon::now()->subHours(3)->toDateTimeString(), 'on_seat' => false],
        ]);
    }

    private function message(string $corporation, array $characters): Message
    {
        $lines = array_map(fn ($c) => trans('ratting-manager::notifications.untracked_line', [
            'name' => $c['name'],
            'ticks' => number_format($c['ticks']),
            'isk' => self::isk($c['net']),
            'date' => Carbon::parse($c['last_tick'])->format('Y-m-d'),
        ]) . ($c['on_seat'] ? '' : ', ' . trans('ratting-manager::notifications.not_on_seat')), $characters);

        return Message::forType($this->type(), trans('ratting-manager::notifications.untracked_title', ['corporation' => $corporation, 'count' => count($characters)]))
            ->description(trans('ratting-manager::notifications.untracked_body') . "\n\n" . self::lines($lines));
    }
}
