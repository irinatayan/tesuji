<?php

declare(strict_types=1);

namespace App\Game;

enum Stone
{
    case Black;
    case White;

    public function opposite(): self
    {
        return match ($this) {
            self::Black => self::White,
            self::White => self::Black,
        };
    }
}
