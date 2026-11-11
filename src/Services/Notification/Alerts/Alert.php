<?php

namespace RattingManager\Services\Notification\Alerts;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RattingManager\Services\Character\Players;
use RattingManager\Services\Configuration\Settings;
use RattingManager\Services\Notification\AlertState;
use RattingManager\Services\Notification\Message;
use RattingManager\Services\Notification\Notifier;
use RattingManager\Services\Universe\SolarSystems;
use RattingManager\Support\Format;

/**
 * What every alert shares: who to send to, what has been sent, and how
 * names, systems and times read in a post.
 */
abstract class Alert
{
    protected Notifier $notifier;
    protected AlertState $state;
    protected Settings $settings;
    protected Players $players;
    protected SolarSystems $systems;

    private array $corporationNames = [];

    public function __construct(Notifier $notifier, AlertState $state, Settings $settings, Players $players, SolarSystems $systems)
    {
        $this->notifier = $notifier;
        $this->state = $state;
        $this->settings = $settings;
        $this->players = $players;
        $this->systems = $systems;
    }

    /** The notification type this alert sends. */
    abstract public function type(): string;

    /** Checks for anything new and sends it. @return int posts sent */
    abstract public function run(): int;

    /** A post as this alert would send it, from made-up figures, for the Test buttons. */
    abstract public function sample(): Message;

    /**
     * Whether a send counts as done: it reached a webhook, or nothing is
     * bound to hear it. A send that every bound webhook refused is tried
     * again on the next run.
     */
    protected function done(array $result): bool
    {
        return $result['delivered'] > 0 || $result['bindings'] === 0;
    }

    protected function corporationName(int $corporationId): string
    {
        if (!array_key_exists($corporationId, $this->corporationNames)) {
            $this->corporationNames[$corporationId] = DB::table('corporation_infos')->where('corporation_id', $corporationId)->value('name')
                ?? DB::table('universe_names')->where('entity_id', $corporationId)->value('name')
                ?? 'Corporation #' . $corporationId;
        }

        return $this->corporationNames[$corporationId];
    }

    /** "0-VG7A (-0.5)" */
    protected function systemLabel(int $systemId): string
    {
        if ($systemId <= 0) {
            return trans('ratting-manager::common.system_not_given');
        }

        $d = $this->systems->details([$systemId])[$systemId];

        return $d['name'] . ' (' . number_format($d['security'], 1) . ')';
    }

    protected function dotlan(int $systemId): ?string
    {
        return $systemId > 0 ? 'https://evemaps.dotlan.net/system/' . rawurlencode($this->systems->name($systemId)) : null;
    }

    protected static function eveTime($when): string
    {
        return Carbon::parse($when)->format('Y-m-d H:i');
    }

    protected static function isk(float $value): string
    {
        return Format::isk($value);
    }

    /** "**A**, **B** and 3 more" */
    protected static function nameList(array $names, int $show = 3): string
    {
        $bold = array_map(fn ($n) => '**' . $n . '**', array_slice($names, 0, $show));
        $list = implode(', ', $bold);

        return count($names) > $show
            ? trans('ratting-manager::notifications.and_more', ['list' => $list, 'count' => count($names) - $show])
            : $list;
    }

    /**
     * Lines for a digest post, cut short with "and N more" so it stays
     * inside Discord's limits.
     */
    protected static function lines(array $lines, int $max = 20): string
    {
        $shown = array_slice($lines, 0, $max);
        if (count($lines) > $max) {
            $shown[] = trans('ratting-manager::notifications.more_lines', ['count' => count($lines) - $max]);
        }

        return implode("\n", $shown);
    }

    /** Players' corporations, as picked in Settings. @return int[] */
    protected function corporations(): array
    {
        return $this->settings->configuredCorporations();
    }
}
