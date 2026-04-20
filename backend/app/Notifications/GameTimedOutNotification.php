<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\GameTimedOutMail;
use App\Models\Game;
use App\Models\User;
use App\Notifications\Messages\TelegramMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class GameTimedOutNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Game $game) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $notifiable->channelsFor('game_finished');
    }

    public function toMail(User $notifiable): GameTimedOutMail
    {
        return (new GameTimedOutMail($this->game, $notifiable))->to($notifiable->email);
    }

    public function toTelegram(User $notifiable): TelegramMessage
    {
        $opponent = $this->game->black_player_id === $notifiable->id
            ? $this->game->whitePlayer->name
            : $this->game->blackPlayer->name;

        $result = $this->game->result ?? 'finished';
        $url = config('app.frontend_url').'/game/'.$this->game->id;

        return new TelegramMessage(
            "Game against <b>{$opponent}</b> ended on timeout: {$result}\n<a href=\"{$url}\">View result</a>"
        );
    }

    public function backoff(): array
    {
        return [5, 30, 120];
    }
}
