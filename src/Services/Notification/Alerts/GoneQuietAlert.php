<?php

namespace RattingManager\Services\Notification\Alerts;

use Carbon\Carbon;
use RattingManager\Services\Notification\Message;
use RattingManager\Services\Ratting\RattingMetrics;

/**
 * Players who ratted regularly and have stopped, as the Gone quiet list on
 * the Corporation tab has them. One post per corporation lists the players
 * new to the list; a player who rats again leaves it, and is posted again
 * if they go quiet another time.
 *
 * A player who left, or whose ratting characters all did, is quiet for a
 * reason everyone already knows, so the list shows them and this does not
 * post them. Once they are back and quiet again, they are posted as usual.
 */
class GoneQuietAlert extends Alert
{
    public function type(): string
    {
        return 'gone_quiet';
    }

    public function run(): int
    {
        if (!$this->notifier->isEnabled($this->type())) {
            return 0;
        }

        $metrics = app(RattingMetrics::class);
        $sent = 0;

        foreach ($this->corporations() as $corporationId) {
            $prefix = 'gone_quiet:' . $corporationId . ':';
            $open = $this->state->open($prefix);
            $quiet = [];
            foreach ($metrics->goneQuiet([$corporationId]) as $player) {
                $quiet[$prefix . $player['main_id']] = $player;
            }

            foreach (array_diff_key($open, $quiet) as $key => $payload) {
                $this->state->close($key);
            }

            $new = array_filter(array_diff_key($quiet, $open), fn ($player) => !($player['gone'] ?? false));
            if ($new === []) {
                continue;
            }

            $result = $this->notifier->send($this->type(), $corporationId, $this->message($this->corporationName($corporationId), array_values($new)));
            if ($this->done($result)) {
                foreach ($new as $key => $player) {
                    $this->state->opened($key, ['last_tick' => $player['last_tick']]);
                }
                $sent += $result['delivered'] > 0 ? 1 : 0;
            }
        }

        return $sent;
    }

    public function sample(): Message
    {
        return $this->message('Test Corp', [
            ['name' => 'Rykel Fairchilde', 'last_tick' => Carbon::now()->subDays(16)->toDateTimeString(), 'ticks' => 412],
            ['name' => 'Solo Ratter', 'last_tick' => Carbon::now()->subDays(21)->toDateTimeString(), 'ticks' => 96, 'alts_left' => [
                ['name' => 'Solo Ratter Alt', 'left_at' => Carbon::now()->subDays(25)->toDateTimeString()],
            ]],
        ]);
    }

    private function message(string $corporation, array $players): Message
    {
        $lines = array_map(fn ($p) => implode(', ', array_merge(
            [trans('ratting-manager::notifications.quiet_line', [
                'name' => $p['name'],
                'date' => Carbon::parse($p['last_tick'])->format('Y-m-d'),
                'days' => (int) Carbon::parse($p['last_tick'])->diffInDays(Carbon::now()),
                'ticks' => number_format($p['ticks']),
            ])],
            array_map(fn ($alt) => trans('ratting-manager::notifications.quiet_alt_left', [
                'name' => $alt['name'],
                'date' => Carbon::parse($alt['left_at'])->format('Y-m-d'),
            ]), $p['alts_left'] ?? [])
        )), $players);

        return Message::forType($this->type(), trans('ratting-manager::notifications.quiet_title', ['corporation' => $corporation, 'count' => count($players)]))
            ->description(trans('ratting-manager::notifications.quiet_body', [
                'regular' => $this->settings->get('data.regular_ratter_days'),
                'quiet' => $this->settings->get('data.gone_quiet_days'),
            ]) . "\n\n" . self::lines($lines));
    }
}
