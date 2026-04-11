<?php

declare(strict_types=1);

namespace App\Events\Invitation;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InvitationReceived implements ShouldBroadcast, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $invitationId,
        public readonly int $toUserId,
        public readonly array $from,
        public readonly int $boardSize,
        public readonly string $mode,
    ) {}

    public function broadcastWith(): array
    {
        return [
            'invitationId' => $this->invitationId,
            'from' => $this->from,
            'boardSize' => $this->boardSize,
            'mode' => $this->mode,
        ];
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('user.'.$this->toUserId);
    }

    public function broadcastAs(): string
    {
        return 'invitation.received';
    }
}
