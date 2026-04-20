<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\GameInvitation;
use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use App\Notifications\Messages\TelegramMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class InvitationReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly GameInvitation $invitation) {}

    /** @return list<string> */
    public function via(User $notifiable): array
    {
        $prefs = $notifiable->notification_preferences ?? [];
        $enabled = $prefs['invitation']['telegram'] ?? false;

        if (! $enabled || ! $notifiable->telegram_chat_id) {
            return [];
        }

        return [TelegramChannel::class];
    }

    public function toTelegram(User $notifiable): TelegramMessage
    {
        $from = $this->invitation->fromUser->name;
        $size = $this->invitation->board_size;
        $url = config('app.frontend_url').'/lobby';

        return new TelegramMessage(
            "<b>{$from}</b> invites you to a {$size}×{$size} game\n<a href=\"{$url}\">Open lobby</a>"
        );
    }

    public function backoff(): array
    {
        return [5, 30, 120];
    }
}
