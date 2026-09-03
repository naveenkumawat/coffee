<?php

namespace App\Jobs;

use App\Services\Personalisation\PersonalisationProfileServiceInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class RebuildPersonalisationProfileJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $uniqueFor = 60;

    public function __construct(
        public ?int $customerId = null,
        public ?string $visitorKey = null,
    ) {}

    public function uniqueId(): string
    {
        if ($this->customerId !== null) {
            return 'personalisation:customer:'.$this->customerId;
        }

        return 'personalisation:visitor:'.($this->visitorKey ?? 'unknown');
    }

    public function handle(PersonalisationProfileServiceInterface $profiles): void
    {
        try {
            if ($this->customerId !== null) {
                $profiles->rebuildForCustomer($this->customerId);

                return;
            }

            if ($this->visitorKey !== null && $this->visitorKey !== '') {
                $profiles->rebuildForVisitor($this->visitorKey);
            }
        } catch (Throwable $exception) {
            Log::warning('personalisation.rebuild_job_failed', [
                'customer_id' => $this->customerId,
                'visitor_key' => $this->visitorKey,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
