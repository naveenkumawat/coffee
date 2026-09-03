<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Minimal inventory/refill signal for live page reconciliation (R1.5).
 * Never broadcasts Eloquent models or financial fields.
 */
class InventoryOpsSignalBroadcasted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<string>  $roleChannels  e.g. ['role.administrator', 'role.operator']
     * @param  array{
     *     event_id: string,
     *     type: string,
     *     entity: string,
     *     entity_id: int,
     *     name?: string|null,
     *     state?: string|null,
     *     updated_at: string|null
     * }  $payload
     */
    public function __construct(
        public readonly array $roleChannels,
        public readonly array $payload,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            static fn (string $channel): PrivateChannel => new PrivateChannel($channel),
            $this->roleChannels,
        );
    }

    public function broadcastAs(): string
    {
        return 'inventory.ops';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
