<?php

namespace App\Console\Commands;

use App\Enums\DiningServiceRequestStatus;
use App\Models\DiningServiceRequest;
use App\Services\Dining\DiningServiceRequestService;
use App\Services\Dining\DiningServiceRequestServiceInterface;
use Illuminate\Console\Command;

class EscalateDiningServiceRequestsCommand extends Command
{
    protected $signature = 'coffee:escalate-dining-service-requests';

    protected $description = 'Escalate pending preferred-waiter dining service requests past the due window';

    public function handle(DiningServiceRequestServiceInterface $service): int
    {
        $due = DiningServiceRequest::query()
            ->where('status', DiningServiceRequestStatus::Pending->value)
            ->whereNull('escalated_at')
            ->where(function ($query): void {
                $query
                    ->where('expires_at', '<=', now())
                    ->orWhere(function ($inner): void {
                        $inner
                            ->whereNull('expires_at')
                            ->whereNotNull('preferred_waiter_user_id')
                            ->where(
                                'created_at',
                                '<=',
                                now()->subSeconds(DiningServiceRequestService::ESCALATION_SECONDS),
                            );
                    });
            })
            ->orderBy('id')
            ->limit(100)
            ->get();

        $count = 0;

        foreach ($due as $request) {
            $service->escalateIfDue($request);
            $count++;
        }

        $this->info("Processed {$count} dining service request escalation(s).");

        return self::SUCCESS;
    }
}
