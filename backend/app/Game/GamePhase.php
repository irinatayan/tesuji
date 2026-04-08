<?php

declare(strict_types=1);

namespace App\Game;

enum GamePhase
{
    case Playing;
    case MarkingDead;
    case Finished;
}
