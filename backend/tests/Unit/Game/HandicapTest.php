<?php

declare(strict_types=1);

namespace Tests\Unit\Game;

use App\Game\Handicap;
use App\Game\Position;
use PHPUnit\Framework\TestCase;

class HandicapTest extends TestCase
{
    public function test_max_stones_per_board_size(): void
    {
        $this->assertSame(5, Handicap::maxStones(9));
        $this->assertSame(9, Handicap::maxStones(13));
        $this->assertSame(9, Handicap::maxStones(19));
    }

    public function test_max_stones_rejects_invalid_board_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Handicap::maxStones(11);
    }

    public function test_handicap_zero_is_always_valid(): void
    {
        $this->assertTrue(Handicap::isValid(9, 0));
        $this->assertTrue(Handicap::isValid(13, 0));
        $this->assertTrue(Handicap::isValid(19, 0));
    }

    public function test_handicap_one_is_never_valid(): void
    {
        $this->assertFalse(Handicap::isValid(9, 1));
        $this->assertFalse(Handicap::isValid(13, 1));
        $this->assertFalse(Handicap::isValid(19, 1));
    }

    public function test_valid_range_for_9x9_is_2_through_5(): void
    {
        foreach (range(2, 5) as $n) {
            $this->assertTrue(Handicap::isValid(9, $n), "handicap {$n} should be valid on 9x9");
        }
        $this->assertFalse(Handicap::isValid(9, 6));
    }

    public function test_valid_range_for_13x13_and_19x19_is_2_through_9(): void
    {
        foreach ([13, 19] as $size) {
            foreach (range(2, 9) as $n) {
                $this->assertTrue(Handicap::isValid($size, $n));
            }
            $this->assertFalse(Handicap::isValid($size, 10));
        }
    }

    public function test_fixed_positions_empty_for_zero_handicap(): void
    {
        $this->assertSame([], Handicap::fixedPositions(9, 0));
        $this->assertSame([], Handicap::fixedPositions(13, 0));
        $this->assertSame([], Handicap::fixedPositions(19, 0));
    }

    public function test_fixed_positions_rejects_handicap_one(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Handicap::fixedPositions(9, 1);
    }

    public function test_fixed_positions_rejects_too_many(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Handicap::fixedPositions(9, 6);
    }

    public function test_fixed_positions_count_matches_handicap(): void
    {
        foreach ([9 => [2, 3, 4, 5], 13 => range(2, 9), 19 => range(2, 9)] as $size => $counts) {
            foreach ($counts as $n) {
                $positions = Handicap::fixedPositions($size, $n);
                $this->assertCount($n, $positions, "size {$size}, handicap {$n}");
            }
        }
    }

    public function test_fixed_positions_are_unique(): void
    {
        foreach ([9 => [2, 3, 4, 5], 13 => range(2, 9), 19 => range(2, 9)] as $size => $counts) {
            foreach ($counts as $n) {
                $positions = Handicap::fixedPositions($size, $n);
                $keys = array_map(fn (Position $p) => "{$p->x},{$p->y}", $positions);
                $this->assertCount(count($keys), array_unique($keys), "duplicate at size {$size}, handicap {$n}");
            }
        }
    }

    public function test_fixed_positions_are_inside_board(): void
    {
        foreach ([9 => [2, 3, 4, 5], 13 => range(2, 9), 19 => range(2, 9)] as $size => $counts) {
            foreach ($counts as $n) {
                foreach (Handicap::fixedPositions($size, $n) as $pos) {
                    $this->assertGreaterThanOrEqual(0, $pos->x);
                    $this->assertGreaterThanOrEqual(0, $pos->y);
                    $this->assertLessThan($size, $pos->x);
                    $this->assertLessThan($size, $pos->y);
                }
            }
        }
    }

    public function test_9x9_handicap_2_is_opposite_diagonals(): void
    {
        $positions = Handicap::fixedPositions(9, 2);
        $this->assertTrue($positions[0]->equals(new Position(6, 2)));
        $this->assertTrue($positions[1]->equals(new Position(2, 6)));
    }

    public function test_9x9_handicap_5_includes_tengen(): void
    {
        $positions = Handicap::fixedPositions(9, 5);
        $last = end($positions);
        $this->assertTrue($last->equals(new Position(4, 4)));
    }

    public function test_19x19_handicap_4_is_all_corners(): void
    {
        $positions = Handicap::fixedPositions(19, 4);
        $expected = [
            new Position(15, 3),
            new Position(3, 15),
            new Position(15, 15),
            new Position(3, 3),
        ];
        foreach ($expected as $i => $exp) {
            $this->assertTrue($positions[$i]->equals($exp), "corner {$i}");
        }
    }

    public function test_19x19_handicap_9_includes_tengen(): void
    {
        $positions = Handicap::fixedPositions(19, 9);
        $last = end($positions);
        $this->assertTrue($last->equals(new Position(9, 9)));
    }

    public function test_13x13_handicap_6_excludes_tengen(): void
    {
        $positions = Handicap::fixedPositions(13, 6);
        $tengen = new Position(6, 6);
        foreach ($positions as $pos) {
            $this->assertFalse($pos->equals($tengen), 'H=6 on 13x13 should not include tengen');
        }
    }
}
