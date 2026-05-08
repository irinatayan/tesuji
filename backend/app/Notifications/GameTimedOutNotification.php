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
        $game = $this->game;
        $isBlack = $game->black_player_id === $notifiable->id;
        $opponent = $isBlack ? $game->whitePlayer->name : $game->blackPlayer->name;

        $notifiableColor = $isBlack ? 'B' : 'W';
        $winnerLetter = str_starts_with($game->result ?? '', 'W') ? 'W' : 'B';
        $iWon = $notifiableColor === $winnerLetter;

        $outcome = $iWon
            ? __('messages.tg_timeout_won', ['opponent' => $opponent])
            : __('messages.tg_timeout_lost', ['opponent' => $opponent]);

        $time = $this->formatTime($game->time_control_type, $game->time_control_config);
        $mode = __('messages.tg_mode_'.$game->mode);
        $details = __('messages.tg_invitation_details', ['mode' => $mode, 'time' => $time]);

        $url = config('app.frontend_url').'/game/'.$game->id;

        return new TelegramMessage("{$outcome}\n{$game->board_size}×{$game->board_size} · {$details}\n{$url}");
    }

    private function formatTime(string $type, array $config): string
    {
        if ($type === 'absolute') {
            $seconds = $config['main_time'] ?? 0;
            $hours = intdiv($seconds, 3600);
            $minutes = intdiv($seconds % 3600, 60);
            $duration = $hours > 0
                ? ($minutes > 0 ? "{$hours}h {$minutes}min" : "{$hours}h")
                : "{$minutes}min";

            return __('messages.tg_time_absolute', ['duration' => $duration]);
        }

        if ($type === 'correspondence') {
            $days = $config['days_per_move'] ?? 3;

            return trans_choice('messages.tg_time_correspondence', $days, ['days' => $days]);
        }

        return '';
    }

    public function backoff(): array
    {
        return [5, 30, 120];
    }
}
