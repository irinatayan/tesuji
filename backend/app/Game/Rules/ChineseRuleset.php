<?php

declare(strict_types=1);

namespace App\Game\Rules;

final class ChineseRuleset implements Ruleset
{
    public function name(): string
    {
        return 'chinese';
    }

    public function komi(int $boardSize): float
    {
        return match ($boardSize) {
            9 => 5.5,
            13 => 6.5,
            19 => 7.5,
            default => throw new \InvalidArgumentException("Unsupported board size: {$boardSize}"),
        };
    }

    public function isSuicideAllowed(): bool
    {
        return false;
    }
}
