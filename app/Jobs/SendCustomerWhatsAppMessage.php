<?php

namespace App\Jobs;

use App\Contracts\WhatsApp\WhatsAppNotificationProviderInterface;
use App\Enums\CustomerNotificationChannel;
use App\Enums\CustomerNotificationType;
use App\Models\CustomerNotificationLog;
use App\Services\WhatsApp\WhatsAppTemplatePayloadFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SendCustomerWhatsAppMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public int $timeout = 30;

    public function __construct(
        public int $notificationLogId,
        public ?string $customerFacingReason = null,
    ) {}

    public function handle(
        WhatsAppNotificationProviderInterface $provider,
        WhatsAppTemplatePayloadFactory $payloadFactory,
    ): void {
        $log = CustomerNotificationLog::query()->find($this->notificationLogId);

        if ($log === null) {
            return;
        }

        if ($log->channel !== CustomerNotificationChannel::Whatsapp) {
            return;
        }

        if ($log->status === 'sent' || $log->status === 'skipped') {
            return;
        }

        if (! (bool) config('services.whatsapp.enabled', false)) {
            $log->forceFill([
                'status' => 'skipped',
                'error_message' => 'WhatsApp notifications disabled.',
                'failed_at' => null,
            ])->save();

            return;
        }

        $order = $log->order;

        if ($order === null || ! $log->type instanceof CustomerNotificationType) {
            $log->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => 'WhatsApp notification is missing order context.',
            ])->save();

            return;
        }

        $destination = $log->recipient_phone
            ?: $payloadFactory->resolveDestination($order);

        if ($destination === null) {
            $log->forceFill([
                'status' => 'skipped',
                'error_message' => 'No eligible WhatsApp destination phone.',
                'failed_at' => null,
            ])->save();

            return;
        }

        $message = $payloadFactory->make(
            $log->type,
            $order,
            $destination,
            $this->customerFacingReason,
        );

        if ($message === null) {
            $log->forceFill([
                'status' => 'skipped',
                'error_message' => 'WhatsApp template mapping unavailable for this notification.',
                'failed_at' => null,
            ])->save();

            return;
        }

        $result = $provider->sendTemplate($message);

        if ($result->success) {
            $log->forceFill([
                'status' => 'sent',
                'provider_message_id' => $result->providerMessageId,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();

            return;
        }

        $log->forceFill([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $this->safeDiagnostic($result->errorCode, $result->safeErrorMessage),
        ])->save();

        if ($result->retryable) {
            throw new RuntimeException(
                'Transient WhatsApp delivery failure: '.($result->errorCode ?: 'retryable'),
            );
        }
    }

    public function failed(?Throwable $exception): void
    {
        $log = CustomerNotificationLog::query()->find($this->notificationLogId);

        if ($log === null || $log->status === 'sent') {
            return;
        }

        $log->forceFill([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $log->error_message
                ?: class_basename($exception ?? RuntimeException::class).': WhatsApp delivery exhausted retries.',
        ])->save();

        Log::warning('WhatsApp customer notification failed permanently.', [
            'notification_log_id' => $this->notificationLogId,
            'notification_type' => $log->type?->value,
            'order_id' => $log->order_id,
            'exception' => $exception ? class_basename($exception) : null,
        ]);
    }

    protected function safeDiagnostic(?string $errorCode, ?string $safeErrorMessage): string
    {
        $parts = array_filter([
            $errorCode,
            $safeErrorMessage,
        ], static fn (?string $part): bool => filled($part));

        $message = implode(': ', $parts) ?: 'WhatsApp delivery failed.';

        return mb_strlen($message) > 500 ? mb_substr($message, 0, 497).'…' : $message;
    }
}
