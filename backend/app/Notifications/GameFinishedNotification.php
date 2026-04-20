<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Mail\GameFinishedMail;
use App\Models\Game;
use App\Models\User;
use App\Notifications\Messages\TelegramMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class GameFinishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Game $game) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        return $notifiable->channelsFor('game_finished');
    }

    public function toMail(User $notifiable): GameFinishedMail
    {
        return (new GameFinishedMail($this->game, $notifiable))->to($notifiable->email);
    }

    public function toTelegram(User $notifiable): TelegramMessage
    {
        $opponent = $this->game->black_player_id === $notifiable->id
            ? $this->game->whitePlayer->name
            : $this->game->blackPlayer->name;

        $result = $this->game->result ?? 'finished';
        $url = config('app.frontend_url').'/game/'.$this->game->id;

        return new TelegramMessage(
            __('messages.tg_game_finished', ['opponent' => $opponent, 'size' => $this->game->board_size, 'result' => $result])."\n{$url}"
        );
    }

    public function backoff(): array
    {
        return [5, 30, 120];
    }
}
