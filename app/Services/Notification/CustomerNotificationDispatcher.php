<?php

namespace App\Services\Notification;

use App\Enums\CustomerNotificationChannel;
use App\Enums\CustomerNotificationType;
use App\Jobs\SendCustomerWhatsAppMessage;
use App\Models\CustomerNotificationLog;
use App\Models\Order;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppTemplatePayloadFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Throwable;

class CustomerNotificationDispatcher implements CustomerNotificationDispatcherInterface
{
    public function __construct(
        protected WhatsAppTemplatePayloadFactory $whatsAppTemplates,
    ) {}

    public function sendOnce(
        CustomerNotificationType $type,
        string $uniqueKey,
        string $recipientEmail,
        Notification $notification,
        ?User $customer = null,
        ?Order $order = null,
        ?string $customerFacingReason = null,
    ): bool {
        $emailSent = $this->sendEmailOnce(
            $type,
            $uniqueKey,
            $recipientEmail,
            $notification,
            $customer,
            $order,
        );

        if ($order !== null) {
            $this->sendWhatsAppOnce(
                $type,
                $uniqueKey,
                $customer,
                $order,
                $customerFacingReason,
            );
        }

        return $emailSent;
    }

    protected function sendEmailOnce(
        CustomerNotificationType $type,
        string $uniqueKey,
        string $recipientEmail,
        Notification $notification,
        ?User $customer,
        ?Order $order,
    ): bool {
        $recipientEmail = strtolower(trim($recipientEmail));

        if ($recipientEmail === '' || ! filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $log = $this->claim(
            $type,
            CustomerNotificationChannel::Email,
            $uniqueKey,
            $recipientEmail,
            null,
            $customer,
            $order,
        );

        if ($log === null) {
            return false;
        }

        try {
            $this->deliver($recipientEmail, $customer, $notification);
            $log->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();

            return true;
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => class_basename($exception).': '.$exception->getMessage(),
            ])->save();

            Log::warning('Customer transactional email failed.', [
                'notification_type' => $type->value,
                'channel' => CustomerNotificationChannel::Email->value,
                'order_id' => $order?->getKey(),
                'order_number' => $order?->order_number,
                'exception' => class_basename($exception),
                'message' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    protected function sendWhatsAppOnce(
        CustomerNotificationType $type,
        string $uniqueKey,
        ?User $customer,
        Order $order,
        ?string $customerFacingReason,
    ): void {
        if (! (bool) config('services.whatsapp.enabled', false)) {
            return;
        }

        $templateKey = $this->whatsAppTemplates->templateKey($type, $order);

        if ($templateKey === null) {
            return;
        }

        $destination = $this->whatsAppTemplates->resolveDestination($order);

        if ($destination === null) {
            return;
        }

        $templateName = trim((string) config('services.whatsapp.templates.'.$templateKey));

        if ($templateName === '') {
            $this->claimFailedWhatsApp(
                $type,
                $uniqueKey,
                $destination,
                $customer,
                $order,
                'WhatsApp template name is not configured for '.$templateKey.'.',
            );

            return;
        }

        if (! filled(config('services.whatsapp.access_token'))
            || ! filled(config('services.whatsapp.phone_number_id'))) {
            $this->claimFailedWhatsApp(
                $type,
                $uniqueKey,
                $destination,
                $customer,
                $order,
                'WhatsApp Cloud API credentials are incomplete.',
            );

            return;
        }

        $log = $this->claim(
            $type,
            CustomerNotificationChannel::Whatsapp,
            $uniqueKey,
            null,
            $destination,
            $customer,
            $order,
        );

        if ($log === null) {
            return;
        }

        try {
            SendCustomerWhatsAppMessage::dispatch($log->getKey(), $customerFacingReason)
                ->afterCommit();
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => class_basename($exception).': '.$exception->getMessage(),
            ])->save();

            Log::warning('Customer transactional WhatsApp dispatch failed.', [
                'notification_type' => $type->value,
                'channel' => CustomerNotificationChannel::Whatsapp->value,
                'order_id' => $order->getKey(),
                'order_number' => $order->order_number,
                'exception' => class_basename($exception),
            ]);
        }
    }

    protected function claimFailedWhatsApp(
        CustomerNotificationType $type,
        string $uniqueKey,
        string $destination,
        ?User $customer,
        Order $order,
        string $reason,
    ): void {
        $log = $this->claim(
            $type,
            CustomerNotificationChannel::Whatsapp,
            $uniqueKey,
            null,
            $destination,
            $customer,
            $order,
        );

        if ($log === null) {
            return;
        }

        if ($log->status === 'sent') {
            return;
        }

        $log->forceFill([
            'status' => 'failed',
            'error_message' => $reason,
            'failed_at' => now(),
        ])->save();
    }

    protected function claim(
        CustomerNotificationType $type,
        CustomerNotificationChannel $channel,
        string $uniqueKey,
        ?string $recipientEmail,
        ?string $recipientPhone,
        ?User $customer,
        ?Order $order,
    ): ?CustomerNotificationLog {
        $existing = CustomerNotificationLog::query()
            ->where('unique_key', $uniqueKey)
            ->where('channel', $channel)
            ->first();

        if ($existing) {
            if (in_array($existing->status, ['sent', 'skipped'], true)) {
                return null;
            }

            // Allow retry after a previous failure.
            return $existing;
        }

        try {
            return CustomerNotificationLog::query()->create([
                'type' => $type,
                'channel' => $channel,
                'unique_key' => $uniqueKey,
                'customer_id' => $customer?->getKey(),
                'order_id' => $order?->getKey(),
                'recipient_email' => $recipientEmail,
                'recipient_phone' => $recipientPhone,
                'status' => 'pending',
            ]);
        } catch (QueryException) {
            $race = CustomerNotificationLog::query()
                ->where('unique_key', $uniqueKey)
                ->where('channel', $channel)
                ->first();

            if ($race && in_array($race->status, ['sent', 'skipped'], true)) {
                return null;
            }

            return $race;
        }
    }

    protected function deliver(string $recipientEmail, ?User $customer, Notification $notification): void
    {
        $queued = $notification instanceof ShouldQueue
            ? $notification->afterCommit()
            : $notification;

        if ($customer instanceof User && strcasecmp((string) $customer->email, $recipientEmail) === 0) {
            $customer->notify($queued);

            return;
        }

        NotificationFacade::route('mail', $recipientEmail)->notify($queued);
    }
}
