<?php

declare(strict_types=1);

namespace App\Game\Engines;

use App\Game\Position;
use InvalidArgumentException;

/**
 * Converts between internal Position (x, y) where (0, 0) is the top-left
 * corner and GTP vertex notation like "D4" where column letters skip 'I'
 * and rows count from the bottom.
 */
final class GtpCoordinates
{
    public static function toVertex(Position $position, int $boardSize): string
    {
        return self::columnLetter($position->x).($boardSize - $position->y);
    }

    public static function fromVertex(string $vertex, int $boardSize): Position
    {
        $vertex = strtoupper(trim($vertex));

        if ($vertex === '' || ! preg_match('/^([A-HJ-T])([0-9]+)$/', $vertex, $matches)) {
            throw new InvalidArgumentException("Invalid GTP vertex: '{$vertex}'");
        }

        $row = (int) $matches[2];
        if ($row < 1 || $row > $boardSize) {
            throw new InvalidArgumentException("Row out of range for board size {$boardSize}: '{$vertex}'");
        }

        $x = self::letterColumn($matches[1]);
        if ($x >= $boardSize) {
            throw new InvalidArgumentException("Column out of range for board size {$boardSize}: '{$vertex}'");
        }

        return new Position($x, $boardSize - $row);
    }

    private static function columnLetter(int $x): string
    {
        $code = ord('A') + $x;
        if ($x >= 8) {
            $code++;
        }

        return chr($code);
    }

    private static function letterColumn(string $letter): int
    {
        $code = ord($letter);
        $offset = $code - ord('A');

        return $code > ord('I') ? $offset - 1 : $offset;
    }
}
