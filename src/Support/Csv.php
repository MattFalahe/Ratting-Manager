<?php

namespace RattingManager\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A CSV download. UTF-8 with a byte order mark so Excel reads character
 * names right, and numbers left unformatted (dot decimals, no thousands
 * separators) so a spreadsheet can sum them.
 */
final class Csv
{
    /**
     * @param string[] $header
     * @param iterable<array> $rows
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return new StreamedResponse(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map([self::class, 'cell'], $header));
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'cell'], $row));
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . str_replace(['"', '\\', "\r", "\n"], '', $filename) . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Floats to two decimals, other numbers as they are, text kept as text. */
    public static function cell($value)
    {
        if (is_float($value)) {
            return number_format($value, 2, '.', '');
        }

        // A spreadsheet runs a cell starting with = + - @, or a tab or line
        // break before one, as a formula. Character names come from outside,
        // so a quote in front keeps any such text as text.
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r", "\n"], true)) {
            return "'" . $value;
        }

        return $value;
    }
}
