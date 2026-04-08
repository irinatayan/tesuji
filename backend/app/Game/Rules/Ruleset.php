<?php

declare(strict_types=1);

namespace App\Game\Rules;

interface Ruleset
{
    public function name(): string;

    public function komi(int $boardSize): float;

    public function isSuicideAllowed(): bool;
}
