<?php

declare(strict_types=1);

namespace App\Game\Rules;

use App\Game\Board;
use App\Game\Position;
use App\Game\Score;

interface Ruleset
{
    public function name(): string;

    public function komi(int $boardSize): float;

    public function isSuicideAllowed(): bool;

    /** @param Position[] $deadStones */
    public function score(Board $board, array $deadStones): Score;
}
