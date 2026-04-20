<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Game;
use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\Messages\TelegramMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class NewMessageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Game $game,
        private readonly int $unreadCount,
        private readonly string $senderName,
    ) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        // Telegram only — email notifications for chat messages are too noisy
        $prefs = $notifiable->notification_preferences ?? [];
        $enabled = $prefs['new_message']['telegram'] ?? false;

        if (! $enabled || ! $notifiable->telegram_chat_id) {
            return [];
        }

        return [TelegramChannel::class];
    }

    public function toTelegram(User $notifiable): TelegramMessage
    {
        $opponent = $this->game->black_player_id === $notifiable->id
            ? $this->game->whitePlayer->name
            : $this->game->blackPlayer->name;

        $url = config('app.frontend_url').'/game/'.$this->game->id;

        $body = $this->unreadCount === 1
            ? __('messages.tg_new_message', ['sender' => $this->senderName])
            : __('messages.tg_new_messages', ['count' => $this->unreadCount, 'opponent' => $opponent]);

        return new TelegramMessage("{$body}\n{$url}");
    }

    public function backoff(): array
    {
        return [5, 30, 120];
    }
}
