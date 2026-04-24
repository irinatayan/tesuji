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

    /**
     * Komi value when playing with a given handicap.
     * Each ruleset decides how handicap affects komi (e.g. Chinese: 0.5 when handicap ≥ 2).
     */
    public function komiWithHandicap(int $boardSize, int $handicap): float;

    public function isSuicideAllowed(): bool;

    /** @param Position[] $deadStones */
    public function score(Board $board, array $deadStones, float $komi): Score;
}
