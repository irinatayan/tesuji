<?php

declare(strict_types=1);

namespace App\Game\Engines;

use App\Game\Board;
use App\Game\Move;
use App\Game\Stone;

interface GoEngine
{
    /**
     * @param  list<Move>  $history  Full move history since game start, in order.
     *                               Used by engines that need it for ko/superko detection.
     */
    public function suggestMove(Board $board, Stone $toPlay, array $history = []): EngineMove;
}
