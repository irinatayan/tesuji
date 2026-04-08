<?php

declare(strict_types=1);

namespace App\Game\Scoring;

use App\Game\Board;
use App\Game\Position;
use App\Game\Score;
use App\Game\Stone;

final class AreaScorer
{
    /**
     * @param  Position[]  $deadStones
     */
    public function score(Board $board, array $deadStones, float $komi): Score
    {
        $board = $board->withoutStones($deadStones);

        $blackStones = 0;
        $whiteStones = 0;

        for ($y = 0; $y < $board->size(); $y++) {
            for ($x = 0; $x < $board->size(); $x++) {
                $cell = $board->get(new Position($x, $y));

                if ($cell === Stone::Black) {
                    $blackStones++;
                } elseif ($cell === Stone::White) {
                    $whiteStones++;
                }
            }
        }

        $blackTerritory = 0;
        $whiteTerritory = 0;

        foreach ($board->territory() as $owner) {
            if ($owner === Stone::Black) {
                $blackTerritory++;
            } elseif ($owner === Stone::White) {
                $whiteTerritory++;
            }
        }

        return new Score(
            black: $blackStones + $blackTerritory,
            white: $whiteStones + $whiteTerritory + $komi,
        );
    }
}
