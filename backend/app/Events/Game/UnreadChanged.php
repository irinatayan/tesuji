<?php

declare(strict_types=1);

namespace App\Events\Game;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class UnreadChanged implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $userId,
        public readonly int $gameId,
        public readonly int $unreadCount,
    ) {}

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->gameId,
            'unread_count' => $this->unreadCount,
        ];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->userId);
    }

    public function broadcastAs(): string
    {
        return 'unread.changed';
    }
}
