<?php

declare(strict_types=1);

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class TelegramWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $secret = config('services.telegram.webhook_secret');
        if ($secret && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            return response('Unauthorized', 401);
        }

        $update = $request->json()->all();
        $message = $update['message'] ?? null;

        if (! $message) {
            return response('OK');
        }

        $text = $message['text'] ?? '';
        $chatId = $message['from']['id'] ?? null;

        if (! $chatId) {
            return response('OK');
        }

        if (str_starts_with($text, '/start ')) {
            $this->handlePairing(trim(substr($text, 7)), (int) $chatId);
        }

        return response('OK');
    }

    private function handlePairing(string $token, int $chatId): void
    {
        $userId = Cache::get("telegram_pairing:{$token}");

        if (! $userId) {
            $this->sendMessage($chatId, 'This link has expired or is invalid. Please generate a new one in the app.');

            return;
        }

        $user = User::find($userId);

        if (! $user) {
            return;
        }

        $user->update(['telegram_chat_id' => $chatId]);
        Cache::forget("telegram_pairing:{$token}");

        Log::info('Telegram account paired', ['user_id' => $userId, 'chat_id' => $chatId]);

        $this->sendMessage($chatId, "Done! Notifications for <b>{$user->name}</b> are now connected.");
    }

    private function sendMessage(int $chatId, string $text): void
    {
        $token = config('services.telegram.bot_token');
        if (! $token) {
            return;
        }

        \Illuminate\Support\Facades\Http::post("https://api.telegram.org/bot{$token}/sendMessage", [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    }
}
