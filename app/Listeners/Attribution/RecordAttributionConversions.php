<?php

namespace App\Listeners\Attribution;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\Dining\DiningPaymentConfirmed;
use App\Events\Order\OrderStatusChanged;
use App\Services\Attribution\AttributionServiceInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecordAttributionConversions
{
    public function __construct(
        protected AttributionServiceInterface $attribution,
    ) {}

    public function handleOrderStatusChanged(OrderStatusChanged $event): void
    {
        if (! in_array($event->toStatus, [OrderStatus::Completed, OrderStatus::PaymentConfirmed], true)) {
            return;
        }

        $this->safeRecord($event->order->fresh(['items', 'diningSession']) ?? $event->order);
    }

    public function handleDiningPaymentConfirmed(DiningPaymentConfirmed $event): void
    {
        try {
            $session = $event->session->fresh(['orders.items']) ?? $event->session;

            foreach ($session->orders as $order) {
                if ($order->payment_status !== PaymentStatus::Confirmed && $order->status !== OrderStatus::Completed) {
                    // Dining rounds may complete operationally before session payment.
                }

                $this->safeRecord($order->fresh(['items', 'diningSession']) ?? $order);
            }
        } catch (Throwable $exception) {
            Log::warning('attribution.dining_conversion_listener_failed', [
                'session_id' => $event->session->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function safeRecord($order): void
    {
        try {
            $this->attribution->recordConversionsForOrder($order);
        } catch (Throwable $exception) {
            Log::warning('attribution.conversion_listener_failed', [
                'order_id' => $order->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
