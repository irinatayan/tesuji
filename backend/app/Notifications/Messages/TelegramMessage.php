<?php

declare(strict_types=1);

namespace App\Notifications\Messages;

final class TelegramMessage
{
    public function __construct(
        public readonly string $text,
        public readonly string $parseMode = 'HTML',
    ) {}
}