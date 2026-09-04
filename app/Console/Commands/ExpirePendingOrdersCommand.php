<?php

namespace App\Console\Commands;

use App\Services\Order\OrderServiceInterface;
use Illuminate\Console\Command;

class ExpirePendingOrdersCommand extends Command
{
    protected $signature = 'coffee:expire-pending-orders
                            {--limit=100 : Maximum orders to process per run}';

    protected $description = 'Auto-cancel unpaid retail Pending Payment orders whose payment window has expired';

    public function handle(OrderServiceInterface $orders): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $cancelled = $orders->expireDuePendingPaymentOrders($limit);

        $this->info("Cancelled {$cancelled} expired pending payment order(s).");

        return self::SUCCESS;
    }
}
