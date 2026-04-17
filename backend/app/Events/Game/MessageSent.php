<?php

declare(strict_types=1);

namespace App\Events\Game;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $gameId,
        public readonly int $messageId,
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $text,
        public readonly string $createdAt,
    ) {}

    public function broadcastWith(): array
    {
        return [
            'id' => $this->messageId,
            'user_id' => $this->userId,
            'user_name' => $this->userName,
            'text' => $this->text,
            'created_at' => $this->createdAt,
        ];
    }

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('game.'.$this->gameId);
    }

    public function broadcastAs(): string
    {
        return 'game.message.sent';
    }
}
