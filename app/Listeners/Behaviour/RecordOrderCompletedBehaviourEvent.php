<?php

namespace App\Listeners\Behaviour;

use App\Enums\OrderStatus;
use App\Events\Order\OrderStatusChanged;
use App\Services\Behaviour\BehaviourEventServiceInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class RecordOrderCompletedBehaviourEvent
{
    public function __construct(
        protected BehaviourEventServiceInterface $behaviour,
    ) {}

    public function handle(OrderStatusChanged $event): void
    {
        if ($event->toStatus !== OrderStatus::Completed) {
            return;
        }

        try {
            $this->behaviour->recordOrderCompleted($event->order->fresh() ?? $event->order);
        } catch (Throwable $exception) {
            Log::warning('behaviour.order_completed_listener_failed', [
                'order_id' => $event->order->getKey(),
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
