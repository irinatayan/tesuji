<?php

declare(strict_types=1);

namespace Tests\Unit\Game;

use App\Game\Board;
use App\Game\Position;
use App\Game\Stone;
use PHPUnit\Framework\TestCase;

class BoardTest extends TestCase
{
    public function test_empty_board_has_correct_size(): void
    {
        $board = Board::empty(19);
        $this->assertSame(19, $board->size());
    }

    public function test_empty_board_is_empty(): void
    {
        $this->assertTrue(Board::empty(9)->isEmpty());
    }

    public function test_throws_on_invalid_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Board::empty(10);
    }

    public function test_get_returns_null_on_empty_cell(): void
    {
        $board = Board::empty(9);
        $this->assertNull($board->get(new Position(0, 0)));
    }

    public function test_place_returns_new_board(): void
    {
        $board = Board::empty(9);
        $newBoard = $board->place(new Position(3, 3), Stone::Black);

        $this->assertNotSame($board, $newBoard);
    }

    public function test_place_does_not_mutate_original(): void
    {
        $board = Board::empty(9);
        $board->place(new Position(3, 3), Stone::Black);

        $this->assertNull($board->get(new Position(3, 3)));
    }

    public function test_place_stone_is_readable(): void
    {
        $board = Board::empty(9)->place(new Position(3, 3), Stone::Black);

        $this->assertSame(Stone::Black, $board->get(new Position(3, 3)));
    }

    public function test_board_is_not_empty_after_place(): void
    {
        $board = Board::empty(9)->place(new Position(0, 0), Stone::White);

        $this->assertFalse($board->isEmpty());
    }

    public function test_throws_on_out_of_bounds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Board::empty(9)->get(new Position(9, 9));
    }

    public function test_is_in_bounds(): void
    {
        $board = Board::empty(9);
        $this->assertTrue($board->isInBounds(new Position(0, 0)));
        $this->assertTrue($board->isInBounds(new Position(8, 8)));
        $this->assertFalse($board->isInBounds(new Position(9, 0)));
    }
}
