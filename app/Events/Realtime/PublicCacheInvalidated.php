<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Public cache version changed. Payload is cache_version only.
 */
class PublicCacheInvalidated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $cacheVersion,
    ) {}

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('public.cache'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'public.cache.invalidated';
    }

    /**
     * @return array{cache_version: string}
     */
    public function broadcastWith(): array
    {
        return [
            'cache_version' => $this->cacheVersion,
        ];
    }
}
