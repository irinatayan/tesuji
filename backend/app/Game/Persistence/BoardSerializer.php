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

    /**
     * Serialize a Board to a hex-encoded string suitable for PostgreSQL BYTEA storage.
     * Format: '\x' followed by hex digits, e.g. '\x000102...'.
     */
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

        return '\\x'.bin2hex($bytes);
    }

    /**
     * Deserialize a Board from a PostgreSQL BYTEA value.
     * Accepts either a PHP stream resource (PDO pgsql returns LOB streams)
     * or a raw binary string (unit tests / SQLite).
     */
    public static function deserialize(mixed $bytes, int $size): Board
    {
        if (is_resource($bytes)) {
            $raw = '';
            while (! feof($bytes)) {
                $raw .= fread($bytes, 8192);
            }
            $bytes = $raw;
        }

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
