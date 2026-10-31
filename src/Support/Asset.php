<?php

namespace RattingManager\Support;

/**
 * URLs for the plugin's published scripts and stylesheets, stamped with the
 * time the file last changed. A browser keeps a script it has seen, so
 * without the stamp an update can leave it drawing a page with the old one.
 */
final class Asset
{
    public static function url(string $path): string
    {
        $path = ltrim($path, '/');
        $source = __DIR__ . '/../Resources/assets/' . $path;
        $stamp = is_file($source) ? filemtime($source) : false;

        return asset('vendor/ratting-manager/' . $path) . ($stamp ? '?v=' . $stamp : '');
    }
}
