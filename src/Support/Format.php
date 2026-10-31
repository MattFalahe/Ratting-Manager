<?php

namespace RattingManager\Support;

final class Format
{
    public static function isk($value): string
    {
        $value = (float) $value;
        $abs = abs($value);

        if ($abs >= 1_000_000_000) {
            return number_format($value / 1_000_000_000, 2) . 'B ISK';
        }
        if ($abs >= 1_000_000) {
            return number_format($value / 1_000_000, 2) . 'M ISK';
        }

        return number_format($value, 0) . ' ISK';
    }

    public static function hours(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h > 0 ? sprintf('%dh %02dm', $h, $m) : sprintf('%dm', $m);
    }
}
