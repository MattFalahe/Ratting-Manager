<?php

namespace RattingManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RattingManager\Services\Notification\WebhookDeliveryService;
use RattingManager\Services\Notification\WebhookDispatcher;
use RattingManager\Support\Csv;
use RattingManager\Support\Format;

class OutputTest extends TestCase
{
    public function test_csv_numbers_stay_numbers_a_spreadsheet_can_add(): void
    {
        $this->assertSame('1234567.50', Csv::cell(1234567.5));
        $this->assertSame(12, Csv::cell(12));
    }

    public function test_csv_text_a_spreadsheet_would_run_is_kept_as_text(): void
    {
        foreach (['=SUM(A1)', '+1', '-1', '@cmd', "\tx", "\rx", "\nx"] as $cell) {
            $this->assertSame("'" . $cell, Csv::cell($cell));
        }
    }

    public function test_csv_ordinary_text_is_left_alone(): void
    {
        $this->assertSame('Rykel Fairchilde', Csv::cell('Rykel Fairchilde'));
        $this->assertSame('', Csv::cell(''));
        $this->assertNull(Csv::cell(null));
    }

    public function test_isk(): void
    {
        $this->assertSame('1.50B ISK', Format::isk(1_500_000_000));
        $this->assertSame('2.25M ISK', Format::isk(2_250_000));
        $this->assertSame('44,000 ISK', Format::isk(44000));
    }

    public function test_hours(): void
    {
        $this->assertSame('45m', Format::hours(45));
        $this->assertSame('1h 00m', Format::hours(60));
        $this->assertSame('12h 05m', Format::hours(725));
    }

    public function test_webhooks_only_go_to_discord_and_slack_over_https(): void
    {
        foreach ([
            'https://discord.com/api/webhooks/1/x',
            'https://canary.discord.com/api/webhooks/1/x',
            'https://discordapp.com/api/webhooks/1/x',
            'https://hooks.slack.com/services/A/B/C',
        ] as $good) {
            $this->assertTrue(WebhookDeliveryService::isValidUrl($good), $good);
        }

        foreach ([
            null,
            '',
            'not a url',
            'http://discord.com/api/webhooks/1/x',
            'https://evil.example/api/webhooks/1/x',
            'https://discord.com.evil.example/x',
            'https://notdiscord.com/x',
            'https://user:pass@discord.com/x',
            'https://discord.com:8443/x',
            'file:///etc/passwd',
        ] as $bad) {
            $this->assertFalse(WebhookDeliveryService::isValidUrl($bad), (string) $bad);
        }
    }

    public function test_a_mention_only_ever_pings_who_it_names(): void
    {
        [$text, $allowed] = WebhookDispatcher::formatMention('<@&123>');
        $this->assertSame('<@&123>', $text);
        $this->assertSame(['123'], $allowed['roles']);
        $this->assertSame([], $allowed['parse']);

        [$text, $allowed] = WebhookDispatcher::formatMention('456');
        $this->assertSame('<@&456>', $text);
        $this->assertSame(['456'], $allowed['roles']);

        [$text, $allowed] = WebhookDispatcher::formatMention('<@!789>');
        $this->assertSame('<@789>', $text);
        $this->assertSame(['789'], $allowed['users']);
    }

    public function test_anything_else_in_a_mention_pings_nobody(): void
    {
        foreach (['@everyone', '@here', 'hello <@&123>', null, '', '   '] as $raw) {
            [$text, $allowed] = WebhookDispatcher::formatMention($raw);
            $this->assertSame('', $text, (string) $raw);
            $this->assertSame(['parse' => [], 'users' => [], 'roles' => []], $allowed, (string) $raw);
        }
    }
}
