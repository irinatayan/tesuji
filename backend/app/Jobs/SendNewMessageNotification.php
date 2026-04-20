<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Game;
use App\Models\User;
use App\Notifications\NewMessageNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendNewMessageNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly Game $game,
        private readonly User $recipient,
        private readonly string $senderName,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $game = Game::withUnreadCount($this->recipient->id)->find($this->game->id);

        if (! $game || $game->unread_count === 0) {
            return;
        }

        $this->recipient->notify(new NewMessageNotification(
            $this->game,
            (int) $game->unread_count,
            $this->senderName,
        ));
    }
}
