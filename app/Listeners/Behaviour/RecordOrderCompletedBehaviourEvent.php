<?php

namespace App\Listeners\Behaviour;

use App\Enums\OrderStatus;
use App\Events\Order\OrderStatusChanged;
use App\Services\Behaviour\BehaviourEventServiceInterface;
use App\Services\Personalisation\PersonalisationProfileServiceInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecordOrderCompletedBehaviourEvent
{
    public function __construct(
        protected BehaviourEventServiceInterface $behaviour,
        protected PersonalisationProfileServiceInterface $profiles,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->toStatus !== OrderStatus::Completed) {
            return;
        }

        try {
            $order = $event->order->fresh() ?? $event->order;
            $this->behaviour->recordOrderCompleted($order);

            if ($order->customer_id) {
                $this->profiles->dispatchRebuildForCustomer((int) $order->customer_id);
            }
        } catch (Throwable $exception) {
            Log::warning('behaviour.order_completed_listener_failed', [
                'order_id' => $event->order->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
