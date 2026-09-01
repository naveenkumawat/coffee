<?php

namespace App\Services\Notification;

use App\Enums\StaffNotificationAudience;
use App\Enums\StaffNotificationChannel;
use App\Enums\StaffNotificationType;
use App\Enums\UserRole;
use App\Models\StaffNotificationLog;
use App\Models\User;
use App\Notifications\StaffOperationalNotification;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class StaffNotificationDispatcher implements StaffNotificationDispatcherInterface
{
    public function notify(
        StaffNotificationType $type,
        string $uniqueKey,
        StaffNotificationAudience $audience,
        StaffNotificationContext $context,
        bool $sendEmail = false,
    ): void {
        $this->notifyRecipients(
            $this->recipientsFor($audience),
            $type,
            $uniqueKey,
            $context,
            $sendEmail,
        );
    }

    public function notifyUser(
        User $user,
        StaffNotificationType $type,
        string $uniqueKey,
        StaffNotificationContext $context,
        bool $sendEmail = false,
    ): void {
        if (! $user->is_active) {
            return;
        }

        $this->notifyRecipients(
            collect([$user]),
            $type,
            $uniqueKey,
            $context,
            $sendEmail,
        );
    }

    public function recipientsFor(StaffNotificationAudience $audience): Collection
    {
        $roles = match ($audience) {
            StaffNotificationAudience::Administrators => [
                UserRole::Owner->value,
                UserRole::Manager->value,
            ],
            StaffNotificationAudience::Operators => [
                UserRole::Operator->value,
            ],
            StaffNotificationAudience::Baristas => [
                UserRole::Barista->value,
            ],
            StaffNotificationAudience::Chefs => [
                UserRole::Chef->value,
            ],
            StaffNotificationAudience::Waiters => [
                UserRole::Waiter->value,
            ],
        };

        return User::query()
            ->whereIn('role', $roles)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, User>  $recipients
     */
    protected function notifyRecipients(
        Collection $recipients,
        StaffNotificationType $type,
        string $uniqueKey,
        StaffNotificationContext $context,
        bool $sendEmail,
    ): void {
        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($recipients as $recipient) {
            $this->sendChannel(
                StaffNotificationChannel::Database,
                $type,
                $uniqueKey,
                $context,
                $recipient,
            );

            if ($sendEmail) {
                $this->sendChannel(
                    StaffNotificationChannel::Email,
                    $type,
                    $uniqueKey,
                    $context,
                    $recipient,
                );
            }
        }
    }

    protected function sendChannel(
        StaffNotificationChannel $channel,
        StaffNotificationType $type,
        string $uniqueKey,
        StaffNotificationContext $context,
        User $recipient,
    ): void {
        $log = $this->claim($channel, $type, $uniqueKey, $context, $recipient);

        if ($log === null) {
            return;
        }

        try {
            $recipient->notify(
                (new StaffOperationalNotification($type, $context, [$channel->value]))->afterCommit(),
            );

            $log->forceFill([
                'status' => 'sent',
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $log->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => class_basename($exception).': '.$exception->getMessage(),
            ])->save();

            Log::warning('Staff operational notification failed.', [
                'notification_type' => $type->value,
                'channel' => $channel->value,
                'order_id' => $context->order?->getKey(),
                'ingredient_id' => $context->ingredient?->getKey(),
                'inventory_refill_request_id' => $context->refillRequest?->getKey(),
                'user_id' => $recipient->getKey(),
                'exception' => class_basename($exception),
            ]);
        }
    }

    protected function claim(
        StaffNotificationChannel $channel,
        StaffNotificationType $type,
        string $uniqueKey,
        StaffNotificationContext $context,
        User $recipient,
    ): ?StaffNotificationLog {
        $existing = StaffNotificationLog::query()
            ->where('unique_key', $uniqueKey)
            ->where('user_id', $recipient->getKey())
            ->where('channel', $channel)
            ->first();

        if ($existing) {
            if (in_array($existing->status, ['sent', 'skipped'], true)) {
                return null;
            }

            return $existing;
        }

        try {
            return StaffNotificationLog::query()->create([
                'type' => $type,
                'channel' => $channel,
                'unique_key' => $uniqueKey,
                'user_id' => $recipient->getKey(),
                'order_id' => $context->order?->getKey(),
                'ingredient_id' => $context->ingredient?->getKey(),
                'inventory_refill_request_id' => $context->refillRequest?->getKey(),
                'status' => 'pending',
            ]);
        } catch (QueryException) {
            $race = StaffNotificationLog::query()
                ->where('unique_key', $uniqueKey)
                ->where('user_id', $recipient->getKey())
                ->where('channel', $channel)
                ->first();

            if ($race && in_array($race->status, ['sent', 'skipped'], true)) {
                return null;
            }

            return $race;
        }
    }
}
