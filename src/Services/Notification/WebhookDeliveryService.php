<?php

namespace RattingManager\Services\Notification;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RattingManager\Models\Webhook;

/**
 * Posts to a webhook and records how it went. Every send in the plugin comes
 * through here, so Diagnostics can show each webhook's delivery health and
 * timeouts and errors are handled one way.
 *
 * Recording never decides the outcome: if the telemetry insert fails, the
 * send still counts by what Discord or Slack answered.
 */
class WebhookDeliveryService
{
    public const CONNECT_TIMEOUT = 5;
    public const REQUEST_TIMEOUT = 10;

    // A misbehaving endpoint can answer with kilobytes of HTML.
    public const MAX_ERROR_LENGTH = 500;
    public const MAX_SUMMARY_LENGTH = 255;

    /** Discord asks a busy webhook to wait; a wait up to this long is honoured once. */
    private const MAX_RETRY_AFTER_SECONDS = 5;

    /**
     * Only https URLs on Discord's and Slack's own hosts, so a webhook field
     * can never be used to make the server call somewhere else.
     */
    public static function isValidUrl(?string $url): bool
    {
        if (!is_string($url) || $url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parsed = parse_url($url);
        if ($parsed === false || strtolower($parsed['scheme'] ?? '') !== 'https') {
            return false;
        }
        if (isset($parsed['user']) || isset($parsed['pass'])) {
            return false;
        }
        if (isset($parsed['port']) && (int) $parsed['port'] !== 443) {
            return false;
        }

        $host = strtolower($parsed['host'] ?? '');
        foreach (['discord.com', 'discordapp.com', 'slack.com'] as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return true;
            }
        }

        return false;
    }

    public function send(Webhook $webhook, Message $message, ?string $mention, ?string $typeKey): bool
    {
        if ($webhook->isSlack()) {
            $payload = $message->toSlack();
        } else {
            [$prefix, $allowed] = WebhookDispatcher::formatMention($mention);
            $payload = $message->toDiscord($prefix, $allowed);
        }

        return $this->post($webhook, $payload, $typeKey, $message->title());
    }

    public function post(Webhook $webhook, array $payload, ?string $typeKey, ?string $summary): bool
    {
        if (!self::isValidUrl($webhook->webhook_url)) {
            Log::warning("Ratting Manager: webhook #{$webhook->id} does not have a Discord or Slack https URL, nothing sent");
            $this->record($webhook, 0, false, 0, $typeKey, $summary, 'Not a Discord or Slack https URL');

            return false;
        }

        $start = microtime(true);
        $status = 0;
        $success = false;
        $error = null;

        try {
            $response = $this->request($webhook->webhook_url, $payload);
            if ($response->status() === 429) {
                $wait = (float) ($response->header('Retry-After') ?: ($response->json('retry_after') ?? 1));
                if ($wait <= self::MAX_RETRY_AFTER_SECONDS) {
                    usleep((int) (max(0.2, $wait) * 1_000_000));
                    $response = $this->request($webhook->webhook_url, $payload);
                }
            }

            $status = $response->status();
            $success = $response->successful();
            if (!$success) {
                $error = 'HTTP ' . $status . ': ' . $response->body();
            }
        } catch (\Throwable $e) {
            // A connection error quotes the URL it was calling, token and all.
            $error = get_class($e) . ': ' . str_replace($webhook->webhook_url, $webhook->maskedUrl(), $e->getMessage());
        }

        $duration = (int) round((microtime(true) - $start) * 1000);
        $this->record($webhook, $status, $success, $duration, $typeKey, $summary, $error);

        if (!$success) {
            Log::warning(sprintf('Ratting Manager: webhook #%d did not take %s (HTTP %d): %s', $webhook->id, $typeKey ?? 'a message', $status, mb_substr((string) $error, 0, 200)));
        }

        return $success;
    }

    /**
     * Each webhook's sends over the last days: how many, how many went
     * through, and the last success and failure.
     *
     * @return array<int, array>
     */
    public function health(int $days = 7): array
    {
        $since = Carbon::now()->subDays($days);

        $counts = DB::table('ratting_manager_webhook_deliveries')
            ->where('attempted_at', '>=', $since)
            ->groupBy('webhook_id')
            ->selectRaw('webhook_id, COUNT(*) as attempts, SUM(success) as delivered, AVG(duration_ms) as avg_ms, MAX(CASE WHEN success = 1 THEN attempted_at END) as last_success, MAX(CASE WHEN success = 0 THEN attempted_at END) as last_failure')
            ->get()
            ->keyBy('webhook_id');

        $health = [];
        foreach ($counts as $id => $row) {
            $lastError = $row->last_failure
                ? DB::table('ratting_manager_webhook_deliveries')->where('webhook_id', $id)->where('success', false)->orderByDesc('attempted_at')->first(['status_code', 'error_message', 'type_key'])
                : null;

            $health[(int) $id] = [
                'attempts' => (int) $row->attempts,
                'delivered' => (int) $row->delivered,
                'avg_ms' => $row->avg_ms === null ? null : (int) round($row->avg_ms),
                'last_success' => $row->last_success,
                'last_failure' => $row->last_failure,
                'last_error' => $lastError ? ['status' => (int) $lastError->status_code, 'message' => $lastError->error_message, 'type' => $lastError->type_key] : null,
            ];
        }

        return $health;
    }

    public static function prune(int $retentionDays = 30): int
    {
        return DB::table('ratting_manager_webhook_deliveries')
            ->where('attempted_at', '<', Carbon::now()->subDays($retentionDays))
            ->delete();
    }

    private function request(string $url, array $payload)
    {
        return Http::connectTimeout(self::CONNECT_TIMEOUT)
            ->timeout(self::REQUEST_TIMEOUT)
            ->acceptJson()
            ->post($url, $payload);
    }

    private function record(Webhook $webhook, int $status, bool $success, int $duration, ?string $typeKey, ?string $summary, ?string $error): void
    {
        try {
            DB::table('ratting_manager_webhook_deliveries')->insert([
                'webhook_id' => $webhook->id,
                'attempted_at' => Carbon::now(),
                'status_code' => $status,
                'success' => $success,
                'duration_ms' => $duration,
                'type_key' => $typeKey ? mb_substr($typeKey, 0, 64) : null,
                'payload_summary' => $summary !== null ? mb_substr($summary, 0, self::MAX_SUMMARY_LENGTH) : null,
                'error_message' => $error !== null ? mb_substr($error, 0, self::MAX_ERROR_LENGTH) : null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Ratting Manager: could not record a webhook delivery: ' . $e->getMessage());
        }
    }
}
