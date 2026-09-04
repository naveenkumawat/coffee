<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Loyalty\LoyaltyServiceInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class AwardLoyaltyPointsForOrderJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 120;

    public function __construct(public int $orderId) {}

    public function uniqueId(): string
    {
        return 'loyalty-award-order-'.$this->orderId;
    }

    public function handle(LoyaltyServiceInterface $loyalty): void
    {
        $order = Order::query()->with('diningSession')->find($this->orderId);

        if ($order === null) {
            return;
        }

        $loyalty->awardForOrder($order);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('loyalty.award_job_failed', [
            'order_id' => $this->orderId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
