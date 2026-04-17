<?php

declare(strict_types=1);

namespace App\Services;

use App\Game\Rules\ChineseRuleset;
use App\Models\Game;

final class SgfExporter
{
    public function export(Game $game): string
    {
        $game->loadMissing(['blackPlayer', 'whitePlayer', 'moves']);

        $komi = (new ChineseRuleset)->komi($game->board_size);
        $date = $game->started_at?->format('Y-m-d') ?? now()->format('Y-m-d');

        $sgf = '(;GM[1]FF[4]CA[UTF-8]';
        $sgf .= "SZ[{$game->board_size}]";
        $sgf .= "KM[{$komi}]";
        $sgf .= 'RU[Chinese]';
        $sgf .= 'PB['.self::escape($game->blackPlayer->name).']';
        $sgf .= 'PW['.self::escape($game->whitePlayer->name).']';
        $sgf .= "DT[{$date}]";

        if ($game->result !== null) {
            $sgf .= 'RE['.self::escape($game->result).']';
        }

        $sgf .= "\n";

        foreach ($game->moves as $move) {
            $color = $move->color === 'black' ? 'B' : 'W';

            if ($move->type === 'pass') {
                $sgf .= ";{$color}[]";
            } elseif ($move->type === 'resign') {
                break;
            } else {
                $coord = self::toSgfCoord($move->x, $move->y);
                $sgf .= ";{$color}[{$coord}]";
            }
        }

        $sgf .= ")\n";

        return $sgf;
    }

    private static function toSgfCoord(int $x, int $y): string
    {
        return chr(ord('a') + $x).chr(ord('a') + $y);
    }

    private static function escape(string $value): string
    {
        return str_replace(['\\', ']'], ['\\\\', '\\]'], $value);
    }
}
