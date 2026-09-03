<?php

namespace App\Http\Resources\Api\V1;

use App\Models\OperationalNotificationRecipient;
use App\Services\OperationalNotification\OperationalNotificationServiceInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin OperationalNotificationRecipient
 */
class OperationalNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var OperationalNotificationRecipient $recipient */
        $recipient = $this->resource;
        $notification = $recipient->notification;

        $subject = null;
        if ($notification?->subject_type && $notification->subject_id) {
            $subject = [
                'type' => class_basename($notification->subject_type),
                'id' => (int) $notification->subject_id,
            ];
        }

        $metrics = app(OperationalNotificationServiceInterface::class)->metricsForRecipient($recipient);

        return [
            'id' => (int) $notification?->getKey(),
            'uuid' => $notification?->uuid,
            'recipient_id' => (int) $recipient->getKey(),
            'type' => $notification?->type,
            'category' => $notification?->category,
            'priority' => $notification?->priority instanceof \BackedEnum
                ? $notification->priority->value
                : $notification?->priority,
            'title' => $notification?->title,
            'message' => $notification?->message,
            'action_required' => (bool) $notification?->action_required,
            'action_code' => $notification?->action_code,
            'action_url' => $notification?->action_url,
            'subject' => $subject,
            'resolved_at' => $notification?->resolved_at?->toIso8601String(),
            'broadcast_at' => $recipient->broadcast_at?->toIso8601String(),
            'delivered_at' => $recipient->delivered_at?->toIso8601String(),
            'first_seen_at' => $recipient->first_seen_at?->toIso8601String(),
            'read_at' => $recipient->read_at?->toIso8601String(),
            'acknowledged_at' => $recipient->acknowledged_at?->toIso8601String(),
            'action_started_at' => $recipient->action_started_at?->toIso8601String(),
            'action_completed_at' => $recipient->action_completed_at?->toIso8601String(),
            'reminder_count' => (int) $recipient->reminder_count,
            'last_reminded_at' => $recipient->last_reminded_at?->toIso8601String(),
            'created_at' => $notification?->created_at?->toIso8601String(),
            'metrics' => $metrics,
        ];
    }
}
