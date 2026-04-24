<?php

declare(strict_types=1);

namespace App\Game;

/**
 * Fixed handicap stone placement using standard AGA hoshi tables.
 *
 * Coordinates are 0-indexed internal (x = column left→right, y = row top→bottom).
 * See wiki/feature-handicap.md for decisions, conventions, and the full tables.
 */
final class Handicap
{
    /**
     * Maximum number of handicap stones supported for a given board size.
     * Handicap of 0 (no handicap) and 1 (not supported — see wiki) are outside this range.
     */
    public static function maxStones(int $boardSize): int
    {
        return match ($boardSize) {
            9 => 5,
            13, 19 => 9,
            default => throw new \InvalidArgumentException("Unsupported board size: {$boardSize}"),
        };
    }

    /** Whether the given count is a valid fixed-handicap value for the board size. */
    public static function isValid(int $boardSize, int $stones): bool
    {
        if ($stones === 0) {
            return true;
        }
        if ($stones === 1) {
            return false;
        }

        return $stones >= 2 && $stones <= self::maxStones($boardSize);
    }

    /**
     * Positions for a fixed-handicap setup on the given board size.
     * Returns an empty array for handicap 0.
     *
     * @return list<Position>
     */
    public static function fixedPositions(int $boardSize, int $stones): array
    {
        if ($stones === 0) {
            return [];
        }
        if (! self::isValid($boardSize, $stones)) {
            throw new \InvalidArgumentException(
                "Invalid handicap {$stones} for {$boardSize}x{$boardSize} board"
            );
        }

        $table = match ($boardSize) {
            9 => self::PLACEMENTS_9,
            13 => self::PLACEMENTS_13,
            19 => self::PLACEMENTS_19,
        };

        return array_map(
            fn (array $xy) => new Position($xy[0], $xy[1]),
            $table[$stones]
        );
    }

    /**
     * 9x9 placement table.
     * Hoshi at (2,2) (6,2) (2,6) (6,6) + tengen (4,4).
     */
    private const PLACEMENTS_9 = [
        2 => [[6, 2], [2, 6]],
        3 => [[6, 2], [2, 6], [6, 6]],
        4 => [[6, 2], [2, 6], [6, 6], [2, 2]],
        5 => [[6, 2], [2, 6], [6, 6], [2, 2], [4, 4]],
    ];

    /**
     * 13x13 placement table. Follows standard 19x19 conventions scaled down.
     * Hoshi at (3,3) (9,3) (3,9) (9,9) + sides (6,3)(3,6)(9,6)(6,9) + tengen (6,6).
     */
    private const PLACEMENTS_13 = [
        2 => [[9, 3], [3, 9]],
        3 => [[9, 3], [3, 9], [9, 9]],
        4 => [[9, 3], [3, 9], [9, 9], [3, 3]],
        5 => [[9, 3], [3, 9], [9, 9], [3, 3], [6, 6]],
        6 => [[9, 3], [3, 9], [9, 9], [3, 3], [3, 6], [9, 6]],
        7 => [[9, 3], [3, 9], [9, 9], [3, 3], [3, 6], [9, 6], [6, 6]],
        8 => [[9, 3], [3, 9], [9, 9], [3, 3], [3, 6], [9, 6], [6, 3], [6, 9]],
        9 => [[9, 3], [3, 9], [9, 9], [3, 3], [3, 6], [9, 6], [6, 3], [6, 9], [6, 6]],
    ];

    /**
     * 19x19 placement table — AGA standard.
     * Hoshi at (3,3) (15,3) (3,15) (15,15) + sides (9,3)(3,9)(15,9)(9,15) + tengen (9,9).
     */
    private const PLACEMENTS_19 = [
        2 => [[15, 3], [3, 15]],
        3 => [[15, 3], [3, 15], [15, 15]],
        4 => [[15, 3], [3, 15], [15, 15], [3, 3]],
        5 => [[15, 3], [3, 15], [15, 15], [3, 3], [9, 9]],
        6 => [[15, 3], [3, 15], [15, 15], [3, 3], [3, 9], [15, 9]],
        7 => [[15, 3], [3, 15], [15, 15], [3, 3], [3, 9], [15, 9], [9, 9]],
        8 => [[15, 3], [3, 15], [15, 15], [3, 3], [3, 9], [15, 9], [9, 3], [9, 15]],
        9 => [[15, 3], [3, 15], [15, 15], [3, 3], [3, 9], [15, 9], [9, 3], [9, 15], [9, 9]],
    ];
}
