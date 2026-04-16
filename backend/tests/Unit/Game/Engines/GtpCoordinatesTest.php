<?php

declare(strict_types=1);

namespace Tests\Unit\Game\Engines;

use App\Game\Engines\GtpCoordinates;
use App\Game\Position;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GtpCoordinatesTest extends TestCase
{
    public function test_top_left_corner_on_9x9(): void
    {
        $this->assertSame('A9', GtpCoordinates::toVertex(new Position(0, 0), 9));
    }

    public function test_bottom_right_corner_on_9x9_skips_i(): void
    {
        $this->assertSame('J1', GtpCoordinates::toVertex(new Position(8, 8), 9));
    }

    public function test_bottom_right_corner_on_19x19(): void
    {
        $this->assertSame('T1', GtpCoordinates::toVertex(new Position(18, 18), 19));
    }

    public function test_column_h_is_eighth_column(): void
    {
        $this->assertSame('H9', GtpCoordinates::toVertex(new Position(7, 0), 9));
    }

    public function test_parse_a1_on_9x9(): void
    {
        $pos = GtpCoordinates::fromVertex('A1', 9);
        $this->assertSame(0, $pos->x);
        $this->assertSame(8, $pos->y);
    }

    public function test_parse_j1_on_9x9_skips_i(): void
    {
        $pos = GtpCoordinates::fromVertex('J1', 9);
        $this->assertSame(8, $pos->x);
        $this->assertSame(8, $pos->y);
    }

    public function test_parse_is_case_insensitive_and_trims(): void
    {
        $pos = GtpCoordinates::fromVertex(' d4 ', 9);
        $this->assertSame(3, $pos->x);
        $this->assertSame(5, $pos->y);
    }

    public function test_round_trip_for_all_positions_on_9x9(): void
    {
        for ($x = 0; $x < 9; $x++) {
            for ($y = 0; $y < 9; $y++) {
                $pos = new Position($x, $y);
                $vertex = GtpCoordinates::toVertex($pos, 9);
                $parsed = GtpCoordinates::fromVertex($vertex, 9);
                $this->assertTrue($pos->equals($parsed), "Round-trip failed for ({$x},{$y}) via {$vertex}");
            }
        }
    }

    public function test_round_trip_for_all_positions_on_19x19(): void
    {
        for ($x = 0; $x < 19; $x++) {
            for ($y = 0; $y < 19; $y++) {
                $pos = new Position($x, $y);
                $vertex = GtpCoordinates::toVertex($pos, 19);
                $parsed = GtpCoordinates::fromVertex($vertex, 19);
                $this->assertTrue($pos->equals($parsed), "Round-trip failed for ({$x},{$y}) via {$vertex}");
            }
        }
    }

    public function test_rejects_letter_i(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GtpCoordinates::fromVertex('I5', 19);
    }

    public function test_rejects_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GtpCoordinates::fromVertex('', 9);
    }

    public function test_rejects_malformed_vertex(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GtpCoordinates::fromVertex('A', 9);
    }

    public function test_rejects_row_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GtpCoordinates::fromVertex('A0', 9);
    }

    public function test_rejects_row_above_board_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GtpCoordinates::fromVertex('A10', 9);
    }

    public function test_rejects_column_above_board_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
        GtpCoordinates::fromVertex('K1', 9);
    }
}
