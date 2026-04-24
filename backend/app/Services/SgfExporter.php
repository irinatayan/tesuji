<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;

final class SgfExporter
{
    public function export(Game $game): string
    {
        $game->loadMissing(['blackPlayer', 'whitePlayer', 'moves']);

        $komi = (float) $game->komi;
        $date = $game->started_at?->format('Y-m-d') ?? now()->format('Y-m-d');

        $sgf = '(;GM[1]FF[4]CA[UTF-8]';
        $sgf .= "SZ[{$game->board_size}]";
        $sgf .= "KM[{$komi}]";
        $sgf .= 'RU[Chinese]';

        $handicap = (int) ($game->handicap ?? 0);
        if ($handicap > 0) {
            $sgf .= "HA[{$handicap}]";
        }

        $sgf .= 'PB['.self::escape($game->blackPlayer->name).']';
        $sgf .= 'PW['.self::escape($game->whitePlayer->name).']';
        $sgf .= "DT[{$date}]";

        if ($game->result !== null) {
            $sgf .= 'RE['.self::escape($game->result).']';
        }

        $handicapStones = $game->handicap_stones ?? [];
        if ($handicapStones !== []) {
            $sgf .= 'AB';
            foreach ($handicapStones as $stone) {
                $coord = self::toSgfCoord((int) $stone['x'], (int) $stone['y']);
                $sgf .= "[{$coord}]";
            }
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
