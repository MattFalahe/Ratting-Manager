<?php

namespace RattingManager\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The installed version against the latest stable release on Packagist, for
 * the Version Status card on Help & Documentation. The same shape as the
 * other plugins' checkers, so the card's markup is the same everywhere.
 *
 * The answer is kept for six hours and the call gives up after three
 * seconds; any failure reads as "unable to check", so Packagist being down
 * never breaks or slows the Help page for long.
 */
class VersionChecker
{
    private const PACKAGE = 'mattfalahe/ratting-manager';
    private const PACKAGIST_URL = 'https://repo.packagist.org/p2/mattfalahe/ratting-manager.json';
    private const RELEASES_URL = 'https://github.com/MattFalahe/Ratting-Manager/releases/tag/';
    private const CACHE_KEY = 'ratting-manager:packagist-latest';
    private const USER_AGENT = 'SeAT Ratting Manager (https://github.com/MattFalahe/Ratting-Manager)';

    /**
     * @return array{current: string, current_source: string, is_dev_branch: bool, latest: ?string,
     *               released: bool, status: string, message: string, release_url: ?string}
     */
    public function getStatus(): array
    {
        $current = $this->installed();
        $packagist = $this->latest();
        $latest = $packagist['latest'];

        $status = [
            'current' => $current ?? '?',
            'current_source' => $current === null ? 'unknown' : 'composer',
            'is_dev_branch' => $current !== null && $this->isBranch($current),
            'latest' => $latest,
            // Packagist answered, and has no stable release to offer yet.
            'released' => !($packagist['reached'] && $latest === null),
            'release_url' => null,
        ];

        if ($status['is_dev_branch']) {
            return $status + [
                'status' => 'dev_branch',
                'message' => $latest === null && $packagist['reached']
                    ? 'You are running a development branch (' . $current . '). Ratting Manager has no tagged release yet, so there is nothing to compare it with.'
                    : 'You are running a development branch (' . $current . '), not a tagged release. It may be ahead of or behind the latest release, depending on what has been pushed since. Install a tagged version (composer require ' . self::PACKAGE . ':^X.Y.Z) for a definite answer.',
            ];
        }

        if ($current === null || $latest === null) {
            return $status + [
                'status' => 'unknown',
                'message' => $current === null
                    ? 'Composer does not say which version is installed, so there is nothing to compare.'
                    : ($packagist['reached']
                        ? 'Packagist has no tagged release of Ratting Manager yet.'
                        : 'Unable to check the latest version: Packagist could not be reached. The plugin is not affected; this is for information only.'),
            ];
        }

        $compare = version_compare($current, $latest);

        if ($compare < 0) {
            return array_merge($status, [
                'status' => 'outdated',
                'message' => 'A newer release is available. Update the way you installed the plugin, then restart the stack. The changelog has what changed.',
                'release_url' => self::RELEASES_URL . $latest,
            ]);
        }

        if ($compare > 0) {
            return $status + [
                'status' => 'ahead',
                'message' => 'You are running a tagged pre-release newer than the latest stable release on Packagist.',
            ];
        }

        return $status + [
            'status' => 'current',
            'message' => 'You are running the latest tagged release.',
        ];
    }

    /**
     * What Composer says is installed. There is no version kept in the
     * plugin's own config to fall back on, since it would have to be bumped
     * by hand on every release and would drift.
     */
    protected function installed(): ?string
    {
        if (!class_exists(\Composer\InstalledVersions::class)) {
            return null;
        }

        try {
            if (\Composer\InstalledVersions::isInstalled(self::PACKAGE)) {
                $version = \Composer\InstalledVersions::getPrettyVersion(self::PACKAGE);

                return is_string($version) && $version !== '' ? $version : null;
            }
        } catch (\Throwable $e) {
            // Reads as not known.
        }

        return null;
    }

    /** A branch install ('dev-dev-1.0', 'dev-main') cannot be compared with a tag. */
    protected function isBranch(string $version): bool
    {
        return $version === '' || str_starts_with($version, 'dev-') || str_ends_with($version, '-dev');
    }

    /**
     * The latest stable version on Packagist. An answer is kept for six
     * hours; a failed call for ten minutes, so an outage does not make every
     * Help visit wait out the timeout.
     *
     * @return array{latest: ?string, reached: bool}
     */
    protected function latest(): array
    {
        $kept = Cache::get(self::CACHE_KEY);
        if (is_array($kept)) {
            return $kept;
        }

        $answer = ['latest' => null, 'reached' => false];
        try {
            $response = Http::timeout(3)
                ->connectTimeout(3)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => self::USER_AGENT])
                ->get(self::PACKAGIST_URL);

            $releases = $response->successful() ? $response->json('packages.' . self::PACKAGE) : null;
            if (is_array($releases)) {
                $answer['reached'] = true;
                // Newest first. Branches are not releases.
                foreach ($releases as $release) {
                    $version = (string) ($release['version'] ?? '');
                    if ($version !== '' && !str_starts_with($version, 'dev-') && !str_contains($version, '-dev')) {
                        $answer['latest'] = ltrim($version, 'v');
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Unreachable: reads as unable to check.
        }

        Cache::put(self::CACHE_KEY, $answer, $answer['reached'] ? 6 * 3600 : 600);

        return $answer;
    }
}
