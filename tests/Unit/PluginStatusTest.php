<?php

namespace RattingManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RattingManager\Services\Ecosystem\PluginStatus;

class PluginStatusTest extends TestCase
{
    private const NONE = ['manager-core' => false, 'seat-broadcast' => false, 'seat-connector' => false, 'warlof-discord' => false];

    /** The rules as another plugin would use them: Manager Core connected, one partner reached through it. */
    private const LINKED = [
        'manager-core' => ['name' => 'Manager Core', 'role' => PluginStatus::CRUCIAL, 'ready' => true, 'via' => null],
        'corp-wallet-manager' => ['name' => 'Corp Wallet Manager', 'role' => PluginStatus::OPTIONAL, 'ready' => true, 'via' => 'manager-core'],
    ];

    private const LINKED_FEATURES = [
        'wallet-signals' => [
            ['role' => PluginStatus::CRUCIAL, 'any' => ['manager-core']],
            ['role' => PluginStatus::OPTIONAL, 'any' => ['corp-wallet-manager']],
        ],
    ];

    private static function labels(array $badges): array
    {
        return array_map(fn (array $b) => $b['label'] . '/' . $b['tone'] . ($b['pulse'] ? '/pulse' : ''), $badges);
    }

    public function test_manager_core_is_coming_soon_whether_or_not_it_is_installed(): void
    {
        foreach ([false, true] as $installed) {
            $status = PluginStatus::resolve(['manager-core' => $installed] + self::NONE);

            $this->assertSame('red', $status['hub']['state']);
            $this->assertSame('Coming soon', $status['hub']['banner']);
            $this->assertTrue($status['hub']['coming_soon']);
            $this->assertSame(
                ['Coming soon/danger/pulse', $installed ? 'Installed/success' : 'Not installed/secondary'],
                self::labels($status['plugins']['manager-core']['badges'])
            );
        }
    }

    public function test_role_names_need_one_discord_plugin_and_say_which(): void
    {
        $none = PluginStatus::resolve(self::NONE);
        $this->assertSame('yellow', $none['features']['role-names']['state']);
        $this->assertSame('Plugin not installed', $none['features']['role-names']['banner']);
        $this->assertSame('SeAT Broadcast or SeAT Connector', $none['features']['role-names']['needs']);
        $this->assertSame(['Not installed/warning'], self::labels($none['plugins']['seat-broadcast']['badges']));

        foreach (['seat-broadcast' => 'SeAT Broadcast', 'seat-connector' => 'SeAT Connector', 'warlof-discord' => 'Warlof Discord'] as $key => $name) {
            $status = PluginStatus::resolve([$key => true] + self::NONE);
            $this->assertSame('on', $status['features']['role-names']['state'], $key);
            $this->assertNull($status['features']['role-names']['banner'], $key);
            $this->assertSame($name, $status['features']['role-names']['using'], $key);
        }
    }

    public function test_a_missing_plugin_whose_job_another_already_does_is_grey(): void
    {
        foreach (['seat-broadcast', 'warlof-discord'] as $key) {
            $status = PluginStatus::resolve([$key => true] + self::NONE);
            $this->assertSame(['Not installed/secondary'], self::labels($status['plugins']['seat-connector']['badges']), $key);
        }

        $connector = PluginStatus::resolve(['seat-connector' => true] + self::NONE);
        $this->assertSame(['Not installed/secondary'], self::labels($connector['plugins']['seat-broadcast']['badges']));
        $this->assertSame(['Installed/success'], self::labels($connector['plugins']['seat-connector']['badges']));
    }

    public function test_warlof_is_found_but_not_listed(): void
    {
        $this->assertSame(['manager-core', 'seat-broadcast', 'seat-connector'], array_keys(PluginStatus::resolve(self::NONE)['plugins']));
    }

    public function test_a_partner_without_manager_core_is_cut_off_and_manager_core_is_recommended(): void
    {
        $status = PluginStatus::resolve(['manager-core' => false, 'corp-wallet-manager' => true], self::LINKED, self::LINKED_FEATURES);

        $this->assertSame(['Not installed/danger/pulse', 'Recommended/info'], self::labels($status['plugins']['manager-core']['badges']));
        $this->assertSame(['Installed/success', 'Needs Manager Core/danger'], self::labels($status['plugins']['corp-wallet-manager']['badges']));
        $this->assertSame('red', $status['features']['wallet-signals']['state']);
        $this->assertSame('Manager Core', $status['features']['wallet-signals']['needs']);
        $this->assertSame(['state' => 'red', 'banner' => 'Not installed', 'coming_soon' => false, 'installed' => false], $status['hub']);
    }

    public function test_manager_core_without_the_partner_only_loses_that_feature(): void
    {
        $status = PluginStatus::resolve(['manager-core' => true, 'corp-wallet-manager' => false], self::LINKED, self::LINKED_FEATURES);

        $this->assertSame('on', $status['hub']['state']);
        $this->assertSame(['Installed/success'], self::labels($status['plugins']['manager-core']['badges']));
        $this->assertSame(['Not installed/warning'], self::labels($status['plugins']['corp-wallet-manager']['badges']));
        $this->assertSame('yellow', $status['features']['wallet-signals']['state']);
        $this->assertSame('Corp Wallet Manager', $status['features']['wallet-signals']['needs']);
    }

    public function test_everything_there_turns_the_feature_on(): void
    {
        $status = PluginStatus::resolve(['manager-core' => true, 'corp-wallet-manager' => true], self::LINKED, self::LINKED_FEATURES);

        $this->assertSame('on', $status['features']['wallet-signals']['state']);
        $this->assertSame('Manager Core and Corp Wallet Manager', $status['features']['wallet-signals']['using']);
        $this->assertSame(['Installed/success'], self::labels($status['plugins']['corp-wallet-manager']['badges']));
    }

    public function test_a_feature_waiting_on_a_connection_not_built_yet_is_coming_soon(): void
    {
        $status = PluginStatus::resolve(['manager-core' => true], PluginStatus::PLUGINS, [
            'later' => [['role' => PluginStatus::CRUCIAL, 'any' => ['manager-core']]],
        ]);

        $this->assertSame('red', $status['features']['later']['state']);
        $this->assertSame('Coming soon', $status['features']['later']['banner']);
    }
}
