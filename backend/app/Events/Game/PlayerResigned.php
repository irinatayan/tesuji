<?php

declare(strict_types=1);

namespace App\Events\Game;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PlayerResigned implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $gameId,
        public readonly string $color,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('game.'.$this->gameId);
    }

    public function broadcastAs(): string
    {
        return 'game.player.resigned';
    }
}
