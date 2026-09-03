<?php

namespace App\Events\Realtime;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * R1.1 foundation probe — proves auth + private channel delivery.
 * Intentionally tiny payload; never includes models or order/payment data.
 */
class RealtimeConnectionProbe implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly string $probeId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->userId),
            new PrivateChannel('realtime.probe'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'realtime.probe';
    }

    /**
     * @return array{probe_id: string, user_id: int}
     */
    public function broadcastWith(): array
    {
        return [
            'probe_id' => $this->probeId,
            'user_id' => $this->userId,
        ];
    }

    public static function forUser(User $user, ?string $probeId = null): self
    {
        return new self(
            userId: (int) $user->id,
            probeId: $probeId ?? bin2hex(random_bytes(8)),
        );
    }
}
