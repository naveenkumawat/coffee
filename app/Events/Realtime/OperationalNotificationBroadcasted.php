<?php

namespace App\Events\Realtime;

use App\Models\OperationalNotification;
use App\Models\OperationalNotificationRecipient;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * R1.2 generic operational notification fan-out (per recipient private user channel).
 * Minimal DTO only — never broadcasts Eloquent models.
 */
class OperationalNotificationBroadcasted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  array{
     *     id: int,
     *     uuid: string,
     *     recipient_id: int,
     *     type: string,
     *     category: string,
     *     priority: string,
     *     title: string,
     *     message: string,
     *     action_required: bool,
     *     action_code: string|null,
     *     action_url: string|null,
     *     subject: array{type: string, id: int}|null,
     *     created_at: string|null
     * }  $payload
     */
    public function __construct(
        public readonly int $userId,
        public readonly array $payload,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'operational.notification';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }

    public static function fromRecipient(
        OperationalNotification $notification,
        OperationalNotificationRecipient $recipient,
    ): self {
        $subject = null;

        if ($notification->subject_type && $notification->subject_id) {
            $subject = [
                'type' => class_basename($notification->subject_type),
                'id' => (int) $notification->subject_id,
            ];
        }

        return new self(
            userId: (int) $recipient->user_id,
            payload: [
                'id' => (int) $notification->getKey(),
                'uuid' => (string) $notification->uuid,
                'recipient_id' => (int) $recipient->getKey(),
                'type' => (string) $notification->type,
                'category' => (string) $notification->category,
                'priority' => $notification->priority instanceof \BackedEnum
                    ? $notification->priority->value
                    : (string) $notification->priority,
                'title' => (string) $notification->title,
                'message' => (string) $notification->message,
                'action_required' => (bool) $notification->action_required,
                'action_code' => $notification->action_code,
                'action_url' => $notification->action_url,
                'subject' => $subject,
                'created_at' => $notification->created_at?->toIso8601String(),
            ],
        );
    }
}
