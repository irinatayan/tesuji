<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Game;
use App\Models\User;
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
        $enabled = $prefs['new_message']['telegram'] ?? true;

        if (! $enabled || ! $notifiable->telegram_chat_id) {
            return [];
        }

        return [\App\Notifications\Channels\TelegramChannel::class];
    }

    public function toTelegram(User $notifiable): TelegramMessage
    {
        $opponent = $this->game->black_player_id === $notifiable->id
            ? $this->game->whitePlayer->name
            : $this->game->blackPlayer->name;

        $url = config('app.frontend_url').'/game/'.$this->game->id;

        $body = $this->unreadCount === 1
            ? "<b>{$this->senderName}</b> sent you a message"
            : "<b>{$this->unreadCount}</b> new messages from <b>{$opponent}</b>";

        return new TelegramMessage("{$body}\n<a href=\"{$url}\">Open game</a>");
    }

    public function backoff(): array
    {
        return [5, 30, 120];
    }
}
