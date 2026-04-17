<?php

declare(strict_types=1);

namespace App\Events\Game;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class MovePassed implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $gameId,
        public readonly int $moveNumber,
        public readonly string $color,
        public readonly string $status,
    ) {}

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('game.'.$this->gameId);
    }

    public function broadcastAs(): string
    {
        return 'game.move.passed';
    }
}
