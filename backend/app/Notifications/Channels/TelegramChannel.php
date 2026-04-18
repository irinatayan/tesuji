<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Notifications\Messages\TelegramMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TelegramChannel
{
    public function __construct(private readonly string $token) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! $notifiable->telegram_chat_id) {
            return;
        }

        /** @var TelegramMessage $message */
        $message = $notification->toTelegram($notifiable);

        $response = Http::post("https://api.telegram.org/bot{$this->token}/sendMessage", [
            'chat_id' => $notifiable->telegram_chat_id,
            'text' => $message->text,
            'parse_mode' => $message->parseMode,
            'disable_web_page_preview' => true,
        ]);

        if (! $response->successful()) {
            Log::warning('Telegram notification failed', [
                'chat_id' => $notifiable->telegram_chat_id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }
}
