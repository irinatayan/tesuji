<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook:set {--url= : Override the webhook URL (useful for local dev with ngrok)} {--remove : Remove the webhook instead of setting it}';

    protected $description = 'Register the Telegram bot webhook with the current APP_URL';

    public function handle(): int
    {
        $token = config('services.telegram.bot_token');
        $secret = config('services.telegram.webhook_secret');

        if (! $token) {
            $this->error('TELEGRAM_BOT_TOKEN is not set.');

            return self::FAILURE;
        }

        $base = "https://api.telegram.org/bot{$token}";

        if ($this->option('remove')) {
            $response = Http::post("{$base}/deleteWebhook");
            $this->info($response->json('description', 'Done'));

            return self::SUCCESS;
        }

        $base_url = $this->option('url') ?? config('app.url');
        $url = rtrim($base_url, '/').'/api/webhooks/telegram';

        $payload = ['url' => $url];
        if ($secret) {
            $payload['secret_token'] = $secret;
        }

        $response = Http::post("{$base}/setWebhook", $payload);

        if ($response->json('ok')) {
            $this->info("Webhook set: {$url}");
        } else {
            $this->error('Failed: '.$response->json('description'));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
