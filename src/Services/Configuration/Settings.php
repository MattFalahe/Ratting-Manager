<?php

namespace RattingManager\Services\Configuration;

use InvalidArgumentException;
use RattingManager\Models\Setting;

class Settings
{
    /** @var array<string, string|null>|null key => stored value */
    private ?array $loaded = null;

    public function get(string $key)
    {
        $this->assertKnown($key);
        $this->load();

        if (!array_key_exists($key, $this->loaded)) {
            return SettingsRegistry::default($key);
        }

        return $this->decode(SettingsRegistry::type($key), $this->loaded[$key]);
    }

    public function set(string $key, $value): void
    {
        $this->assertKnown($key);

        $stored = $this->encode(SettingsRegistry::type($key), $value);

        Setting::updateOrCreate(
            ['key' => $key, 'corporation_id' => null],
            ['value' => $stored]
        );

        $this->load();
        $this->loaded[$key] = $stored;
    }

    /** Read the stored values again, after a transaction that wrote some was rolled back. */
    public function reload(): void
    {
        $this->loaded = null;
    }

    /** @return int[] */
    public function configuredCorporations(): array
    {
        return $this->get('general.configured_corporations');
    }

    private function load(): void
    {
        if ($this->loaded !== null) {
            return;
        }

        $this->loaded = Setting::whereNull('corporation_id')->pluck('value', 'key')->all();
    }

    private function assertKnown(string $key): void
    {
        if (!SettingsRegistry::has($key)) {
            throw new InvalidArgumentException("Unknown Ratting Manager setting: {$key}");
        }
    }

    private function encode(string $type, $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int' => (string) (int) $value,
            'bool' => $value ? '1' : '0',
            'int_list' => json_encode(self::intList($value)),
            'json' => json_encode($value),
            default => (string) $value,
        };
    }

    private function decode(string $type, ?string $value)
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int' => (int) $value,
            'bool' => $value === '1',
            'int_list' => self::intList(json_decode($value, true) ?? []),
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    private static function intList($value): array
    {
        $ids = array_map('intval', (array) $value);

        return array_values(array_unique(array_filter($ids, fn ($id) => $id > 0)));
    }
}
