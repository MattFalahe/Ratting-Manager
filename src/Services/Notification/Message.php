<?php

namespace RattingManager\Services\Notification;

use Carbon\Carbon;

/**
 * One notification, written once and sent as a Discord embed or as Slack
 * text. Text uses Discord's markdown (**bold**, [text](url)); the Slack form
 * translates it.
 */
final class Message
{
    // Discord's embed limits. Anything over them makes Discord refuse the
    // whole post, so text is cut to fit rather than sent and lost.
    private const TITLE_MAX = 256;
    private const DESCRIPTION_MAX = 4096;
    private const FIELD_NAME_MAX = 256;
    private const FIELD_VALUE_MAX = 1024;
    private const FIELDS_MAX = 25;
    private const FOOTER_MAX = 2048;
    private const EMBED_TOTAL_MAX = 6000;

    public const USERNAME = 'Ratting Manager';

    private string $title;
    private int $colour;
    private ?string $description = null;
    private ?string $url = null;
    private ?string $thumbnail = null;
    private ?string $footer = null;
    private ?Carbon $timestamp = null;
    /** @var array<int, array{name: string, value: string, inline: bool}> */
    private array $fields = [];

    private function __construct(string $title, int $colour)
    {
        $this->title = $title;
        $this->colour = $colour;
    }

    public static function make(string $title, int $colour): self
    {
        return new self($title, $colour);
    }

    public static function forType(string $typeKey, string $title): self
    {
        return new self($title, NotificationTypes::colour($typeKey));
    }

    public function description(?string $text): self
    {
        $this->description = $text;

        return $this;
    }

    public function field(string $name, string $value, bool $inline = true): self
    {
        $this->fields[] = ['name' => $name, 'value' => $value === '' ? '-' : $value, 'inline' => $inline];

        return $this;
    }

    public function url(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /** A picture beside the embed, such as the ship or NPC from EVE's image server. */
    public function thumbnail(?string $url): self
    {
        $this->thumbnail = $url;

        return $this;
    }

    public function footer(?string $text): self
    {
        $this->footer = $text;

        return $this;
    }

    public function at(?Carbon $when): self
    {
        $this->timestamp = $when;

        return $this;
    }

    public function title(): string
    {
        return $this->title;
    }

    /** The same message with a prefix on its title, for test sends. */
    public function withTitlePrefix(string $prefix): self
    {
        $copy = clone $this;
        $copy->title = $prefix . $this->title;

        return $copy;
    }

    /**
     * @param string $mention a role or user mention as WebhookDispatcher::formatMention gives it
     * @param array $allowedMentions what Discord may ping; nothing unless a mention was set
     */
    public function toDiscord(string $mention = '', array $allowedMentions = ['parse' => []]): array
    {
        $embed = [
            'title' => self::cut($this->title, self::TITLE_MAX),
            'color' => $this->colour,
            'timestamp' => ($this->timestamp ?? Carbon::now())->toIso8601String(),
            'footer' => ['text' => self::cut($this->footer ?? self::USERNAME, self::FOOTER_MAX)],
        ];
        if ($this->description !== null && $this->description !== '') {
            $embed['description'] = self::cut($this->description, self::DESCRIPTION_MAX);
        }
        if ($this->url) {
            $embed['url'] = $this->url;
        }
        if ($this->thumbnail) {
            $embed['thumbnail'] = ['url' => $this->thumbnail];
        }

        $used = mb_strlen($embed['title']) + mb_strlen($embed['footer']['text']) + mb_strlen($embed['description'] ?? '');
        $fields = [];
        foreach (array_slice($this->fields, 0, self::FIELDS_MAX) as $f) {
            $name = self::cut($f['name'], self::FIELD_NAME_MAX);
            $value = self::cut($f['value'], self::FIELD_VALUE_MAX);
            if ($used + mb_strlen($name) + mb_strlen($value) > self::EMBED_TOTAL_MAX) {
                break;
            }
            $used += mb_strlen($name) + mb_strlen($value);
            $fields[] = ['name' => $name, 'value' => $value, 'inline' => $f['inline']];
        }
        if ($fields) {
            $embed['fields'] = $fields;
        }

        $payload = [
            'username' => self::USERNAME,
            'embeds' => [$embed],
            'allowed_mentions' => $allowedMentions,
        ];
        if ($mention !== '') {
            $payload['content'] = trim($mention);
        }

        return $payload;
    }

    /** Slack incoming webhooks take plain mrkdwn text. Discord role mentions mean nothing there, so none is added. */
    public function toSlack(): array
    {
        $title = self::slack('**' . $this->title . '**');
        if ($this->url) {
            $title = '*<' . $this->url . '|' . $this->title . '>*';
        }

        $lines = [$title];
        if ($this->description) {
            $lines[] = self::slack($this->description);
        }
        foreach ($this->fields as $f) {
            $lines[] = '*' . $f['name'] . '*: ' . self::slack($f['value']);
        }
        if ($this->footer) {
            $lines[] = '_' . $this->footer . '_';
        }

        return ['text' => implode("\n", $lines)];
    }

    private static function slack(string $text): string
    {
        $text = preg_replace('/\[([^\]]+)\]\((https?:[^)\s]+)\)/', '<$2|$1>', $text) ?? $text;

        return str_replace(['**', '__'], ['*', '_'], $text);
    }

    private static function cut(string $text, int $max): string
    {
        return mb_strlen($text) <= $max ? $text : rtrim(mb_substr($text, 0, $max - 3)) . '...';
    }
}
