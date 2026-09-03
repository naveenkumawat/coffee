<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Minimal dining/table signal for live page reconciliation (R1.6).
 * Never broadcasts Eloquent models, money, secrets, or private customer data.
 */
class DiningOpsSignalBroadcasted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  list<string>  $channels  private channel names without "private-" prefix
     * @param  array{
     *     event_id: string,
     *     type: string,
     *     session_id: int,
     *     table_id: int|null,
     *     order_id?: int|null,
     *     state?: string|null,
     *     updated_at: string|null
     * }  $payload
     */
    public function __construct(
        public readonly array $channels,
        public readonly array $payload,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_values(array_map(
            static fn (string $channel): PrivateChannel => new PrivateChannel($channel),
            array_unique($this->channels),
        ));
    }

    public function broadcastAs(): string
    {
        return 'dining.ops';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
