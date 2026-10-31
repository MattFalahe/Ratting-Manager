<?php

namespace RattingManager\Services\Ratting;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Cached figures for the dashboards and the leaderboard.
 *
 * The figures only change when an import writes ratting data, so they are
 * kept until the next one rather than for a few minutes. Every command that
 * writes ratting data calls changed() when it lets go of the pipeline lock,
 * which moves every key on at once; the old entries run out on their own.
 */
final class FigureCache
{
    private const VERSION_KEY = 'ratting-manager:figures-version';

    public static function remember(string $key, callable $work)
    {
        return Cache::remember(
            'rm:' . self::version() . ':' . $key,
            (int) config('ratting-manager.cache_seconds', 3600),
            $work
        );
    }

    /** Ratting data has changed: every figure is worked out again on its next read. */
    public static function changed(): void
    {
        Cache::forever(self::VERSION_KEY, uniqid('', true));
    }

    /**
     * A key for a stretch of time. A stretch running up to now is the same
     * until the next import brings newer ticks in, so its end is left out
     * and the figures carry over from one minute to the next.
     */
    public static function range(Carbon $from, Carbon $to): string
    {
        $end = $to->getTimestamp() >= Carbon::now()->getTimestamp() - 60 ? 'now' : (string) $to->getTimestamp();

        return $from->getTimestamp() . '-' . $end;
    }

    private static function version(): string
    {
        return (string) Cache::rememberForever(self::VERSION_KEY, fn () => uniqid('', true));
    }
}
