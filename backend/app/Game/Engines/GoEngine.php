<?php

declare(strict_types=1);

namespace App\Game\Engines;

use App\Game\Board;
use App\Game\Stone;

interface GoEngine
{
    public function suggestMove(Board $board, Stone $toPlay): EngineMove;
}
