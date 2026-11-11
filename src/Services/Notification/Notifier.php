<?php

namespace RattingManager\Services\Notification;

use RattingManager\Models\Webhook;

/**
 * Sends a notification to every webhook bound to its type.
 */
class Notifier
{
    private WebhookDispatcher $dispatcher;
    private WebhookDeliveryService $delivery;

    public function __construct(WebhookDispatcher $dispatcher, WebhookDeliveryService $delivery)
    {
        $this->dispatcher = $dispatcher;
        $this->delivery = $delivery;
    }

    public function isEnabled(string $typeKey): bool
    {
        return $this->dispatcher->isTypeEnabled($typeKey);
    }

    /** Whether a notification of this type would reach any webhook now. */
    public function hasRecipients(string $typeKey, ?int $corporationId): bool
    {
        return $this->dispatcher->bindings($typeKey, $corporationId) !== [];
    }

    /**
     * @return array{bindings: int, delivered: int} how many webhooks it was for, and how many took it
     */
    public function send(string $typeKey, ?int $corporationId, Message $message): array
    {
        $bindings = $this->dispatcher->bindings($typeKey, $corporationId);

        $delivered = 0;
        foreach ($bindings as $binding) {
            if ($this->delivery->send($binding['webhook'], $message, $binding['role_mention'], $typeKey)) {
                $delivered++;
            }
        }

        return ['bindings' => count($bindings), 'delivered' => $delivered];
    }

    /**
     * Straight to one webhook, marked as a test and with no mention, whatever
     * is bound. For the Test buttons.
     */
    public function sendTest(Webhook $webhook, string $typeKey, Message $message): bool
    {
        return $this->delivery->send(
            $webhook,
            $message->withTitlePrefix('[' . trans('ratting-manager::notifications.test_prefix') . '] '),
            null,
            $typeKey
        );
    }
}
