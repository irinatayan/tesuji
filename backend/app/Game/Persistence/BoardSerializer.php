<?php

declare(strict_types=1);

namespace App\Game\Persistence;

use App\Game\Board;
use App\Game\Position;
use App\Game\Stone;

final class BoardSerializer
{
    private const EMPTY = 0x00;

    private const BLACK = 0x01;

    private const WHITE = 0x02;

    public static function serialize(Board $board): string
    {
        $size = $board->size();
        $bytes = '';

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $stone = $board->get(new Position($x, $y));
                $bytes .= match ($stone) {
                    Stone::Black => chr(self::BLACK),
                    Stone::White => chr(self::WHITE),
                    null => chr(self::EMPTY),
                };
            }
        }

        return $bytes;
    }

    public static function deserialize(string $bytes, int $size): Board
    {
        $board = Board::empty($size);

        for ($i = 0; $i < strlen($bytes); $i++) {
            $byte = ord($bytes[$i]);

            if ($byte === self::EMPTY) {
                continue;
            }

            $x = $i % $size;
            $y = intdiv($i, $size);
            $stone = $byte === self::BLACK ? Stone::Black : Stone::White;
            $board = $board->place(new Position($x, $y), $stone);
        }

        return $board;
    }
}
