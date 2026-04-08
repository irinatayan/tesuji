<?php

declare(strict_types=1);

namespace App\Game;

enum MoveType
{
    case Play;
    case Pass;
    case Resign;
}
