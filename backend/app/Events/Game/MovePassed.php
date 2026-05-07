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
        public readonly ?array $blackClock = null,
        public readonly ?array $whiteClock = null,
        public readonly ?string $expiresAt = null,
    ) {}

    public function broadcastWith(): array
    {
        return [
            'game_id' => $this->gameId,
            'move_number' => $this->moveNumber,
            'color' => $this->color,
            'status' => $this->status,
            'black_clock' => $this->blackClock,
            'white_clock' => $this->whiteClock,
            'expires_at' => $this->expiresAt,
        ];
    }

    public function broadcastOn(): PresenceChannel
    {
        return new PresenceChannel('game.'.$this->gameId);
    }

    public function broadcastAs(): string
    {
        return 'game.move.passed';
    }
}
