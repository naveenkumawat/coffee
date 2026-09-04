<?php

namespace App\Listeners\Loyalty;

use App\Enums\OrderStatus;
use App\Events\Dining\DiningPaymentConfirmed;
use App\Events\Order\OrderStatusChanged;
use App\Jobs\AwardLoyaltyPointsForOrderJob;
use Illuminate\Support\Facades\Log;
use Throwable;

class AwardLoyaltyOnOrderCompleted
{
    public function handleOrderStatusChanged(OrderStatusChanged $event): void
    {
        if ($event->toStatus !== OrderStatus::Completed) {
            return;
        }

        $this->dispatchSafely((int) $event->order->getKey());
    }

    public function handleDiningPaymentConfirmed(DiningPaymentConfirmed $event): void
    {
        try {
            $session = $event->session->fresh(['orders']) ?? $event->session;

            foreach ($session->orders as $order) {
                $this->dispatchSafely((int) $order->getKey());
            }
        } catch (Throwable $exception) {
            Log::warning('loyalty.dining_payment_listener_failed', [
                'dining_session_id' => $event->session->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function dispatchSafely(int $orderId): void
    {
        try {
            AwardLoyaltyPointsForOrderJob::dispatch($orderId)->afterCommit();
        } catch (Throwable $exception) {
            Log::warning('loyalty.award_dispatch_failed', [
                'order_id' => $orderId,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
