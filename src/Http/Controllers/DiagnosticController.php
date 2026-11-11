<?php

namespace RattingManager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RattingManager\Models\Webhook;
use RattingManager\Services\Diagnostic\Checks;
use RattingManager\Services\Diagnostic\TickTrace;
use RattingManager\Services\Notification\Alerts\Alerts;
use RattingManager\Services\Notification\NotificationTypes;
use RattingManager\Services\Notification\Notifier;
use RattingManager\Services\Notification\WebhookDeliveryService;
use Seat\Web\Http\Controllers\Controller;

class DiagnosticController extends Controller
{
    private const TABS = ['health', 'master-test', 'settings-health', 'data-integrity', 'tick-trace', 'notifications'];

    /**
     * Health Checks run on every load. The heavier tabs run only when they
     * are the one asked for, and are kept for a few minutes unless a forced
     * refresh asks again, the way Structure Manager's page works.
     */
    public function index(Request $request, Checks $checks, TickTrace $trace)
    {
        $tab = in_array($request->query('diag_tab'), self::TABS, true) ? $request->query('diag_tab') : 'health';
        $refresh = (bool) $request->query('refresh', false);

        $cached = function (string $key, int $seconds, callable $work) use ($refresh) {
            $key = 'rm:diagnostic:' . $key;
            if ($refresh) {
                Cache::forget($key);
            }
            return Cache::remember($key, $seconds, $work);
        };

        $health = $checks->health();
        $masterTest = $tab === 'master-test' ? $cached('master_test', 300, fn () => $checks->masterTest()) : null;
        $settingsHealth = $tab === 'settings-health' ? $cached('settings_health', 30, fn () => $checks->settingsHealth()) : null;
        $dataIntegrity = $tab === 'data-integrity' ? $cached('data_integrity', 300, fn () => $checks->dataIntegrity()) : null;

        $journalId = (int) $request->query('journal_id', 0);
        $traceResult = $tab === 'tick-trace' && $journalId > 0 ? $trace->trace($journalId) : null;

        $notifications = $tab === 'notifications' ? $this->notificationData() : null;

        $counts = ['ok' => 0, 'warn' => 0, 'info' => 0];
        foreach ($health as $check) {
            $counts[$check['status']]++;
        }

        return view('ratting-manager::diagnostic.index', [
            'tab' => $tab,
            'health' => $health,
            'counts' => $counts,
            'masterTest' => $masterTest,
            'settingsHealth' => $settingsHealth,
            'dataIntegrity' => $dataIntegrity,
            'journalId' => $journalId ?: null,
            'traceResult' => $traceResult,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Sends a sample of one notification type to one webhook, marked as a
     * test and mentioning nobody, whatever is bound.
     */
    public function notificationTest(Request $request, Notifier $notifier)
    {
        $data = $request->validate([
            'webhook_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(NotificationTypes::keys())],
        ]);

        $webhook = Webhook::findOrFail($data['webhook_id']);
        $ok = $notifier->sendTest($webhook, $data['type'], Alerts::sample($data['type']));

        $error = $ok ? null : DB::table('ratting_manager_webhook_deliveries')->where('webhook_id', $webhook->id)->orderByDesc('id')->value('error_message');

        return redirect()->route('ratting-manager.diagnostic', ['diag_tab' => 'notifications'])->with(
            $ok ? 'success' : 'error',
            $ok
                ? trans('ratting-manager::diagnostic.notif_test_sent', ['type' => NotificationTypes::name($data['type']), 'webhook' => $webhook->description])
                : trans('ratting-manager::diagnostic.notif_test_failed', ['type' => NotificationTypes::name($data['type']), 'webhook' => $webhook->description, 'error' => mb_substr((string) $error, 0, 200)])
        );
    }

    private function notificationData(): array
    {
        NotificationTypes::ensureRows();

        return [
            'webhooks' => Webhook::orderBy('description')->get(),
            'health' => app(WebhookDeliveryService::class)->health(7),
            'recent' => DB::table('ratting_manager_webhook_deliveries as d')
                ->leftJoin('ratting_manager_webhooks as w', 'w.id', '=', 'd.webhook_id')
                ->orderByDesc('d.id')
                ->limit(30)
                ->get(['d.attempted_at', 'd.success', 'd.status_code', 'd.duration_ms', 'd.type_key', 'd.payload_summary', 'd.error_message', 'w.description']),
            'open' => DB::table('ratting_manager_alert_state')->where('state', 'open')->orderByDesc('last_sent_at')->limit(50)->get(['alert_key', 'first_seen_at', 'last_sent_at']),
            'types' => NotificationTypes::keys(),
        ];
    }
}
