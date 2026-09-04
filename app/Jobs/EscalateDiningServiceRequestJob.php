<?php

namespace App\Jobs;

use App\Models\DiningServiceRequest;
use App\Services\Dining\DiningServiceRequestServiceInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class EscalateDiningServiceRequestJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $uniqueFor = 120;

    public function __construct(public int $diningServiceRequestId) {}

    public function uniqueId(): string
    {
        return 'dining-service-request-escalate-'.$this->diningServiceRequestId;
    }

    public function handle(DiningServiceRequestServiceInterface $service): void
    {
        $request = DiningServiceRequest::query()->find($this->diningServiceRequestId);

        if ($request === null) {
            return;
        }

        $service->escalateIfDue($request);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('dining.service_request_escalation_failed', [
            'dining_service_request_id' => $this->diningServiceRequestId,
            'message' => $exception?->getMessage(),
        ]);
    }
}
