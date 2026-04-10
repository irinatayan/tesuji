<?php

declare(strict_types=1);

namespace App\Events\Invitation;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class InvitationAccepted implements ShouldBroadcast, ShouldDispatchAfterCommit, ShouldQueue
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $invitationId,
        public readonly int $fromUserId,
        public readonly int $toUserId,
        public readonly int $gameId,
    ) {}

    /** @return PrivateChannel[] */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->fromUserId),
            new PrivateChannel('user.'.$this->toUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'invitation.accepted';
    }
}
