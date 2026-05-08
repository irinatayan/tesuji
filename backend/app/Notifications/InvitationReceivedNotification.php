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
        $inv = $this->invitation;
        $url = config('app.frontend_url').'/lobby';

        $mode = __('messages.tg_mode_'.$inv->mode);
        $time = $this->formatTime($inv->time_control_type, $inv->time_control_config);

        $text = __('messages.tg_invitation', ['from' => $inv->fromUser->name, 'size' => $inv->board_size])
            ."\n".__('messages.tg_invitation_details', ['mode' => $mode, 'time' => $time])
            ."\n{$url}";

        return new TelegramMessage($text);
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
