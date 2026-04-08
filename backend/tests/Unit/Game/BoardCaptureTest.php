<?php

declare(strict_types=1);

namespace Tests\Unit\Game;

use App\Game\Board;
use App\Game\Position;
use App\Game\Stone;
use PHPUnit\Framework\TestCase;

class BoardCaptureTest extends TestCase
{
    public function test_single_stone_is_captured(): void
    {
        $board = Board::empty(9)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black)
            ->place(new Position(2, 1), Stone::Black)
            ->place(new Position(1, 2), Stone::Black)
            ->place(new Position(1, 1), Stone::White);

        $result = $board->removeCaptured(Stone::White);

        $this->assertNull($result->board->get(new Position(1, 1)));
        $this->assertCount(1, $result->captured);
        $this->assertTrue($result->captured[0]->equals(new Position(1, 1)));
    }

    public function test_group_is_captured(): void
    {
        // White group at (1,0) and (1,1) surrounded by Black
        $board = Board::empty(9)
            ->place(new Position(0, 0), Stone::Black)
            ->place(new Position(2, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black)
            ->place(new Position(2, 1), Stone::Black)
            ->place(new Position(1, 2), Stone::Black)
            ->place(new Position(1, 0), Stone::White)
            ->place(new Position(1, 1), Stone::White);

        $result = $board->removeCaptured(Stone::White);

        $this->assertNull($result->board->get(new Position(1, 0)));
        $this->assertNull($result->board->get(new Position(1, 1)));
        $this->assertCount(2, $result->captured);
    }

    public function test_stone_with_liberty_is_not_captured(): void
    {
        $board = Board::empty(9)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black)
            ->place(new Position(1, 1), Stone::White);

        $result = $board->removeCaptured(Stone::White);

        $this->assertSame(Stone::White, $result->board->get(new Position(1, 1)));
        $this->assertCount(0, $result->captured);
    }

    public function test_multiple_groups_captured_in_one_move(): void
    {
        // Two isolated white stones captured at once
        $board = Board::empty(9)
            ->place(new Position(0, 1), Stone::Black)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(2, 1), Stone::Black)
            ->place(new Position(1, 2), Stone::Black)
            ->place(new Position(3, 1), Stone::Black)
            ->place(new Position(4, 0), Stone::Black)
            ->place(new Position(5, 1), Stone::Black)
            ->place(new Position(4, 2), Stone::Black)
            ->place(new Position(1, 1), Stone::White)
            ->place(new Position(4, 1), Stone::White);

        $result = $board->removeCaptured(Stone::White);

        $this->assertNull($result->board->get(new Position(1, 1)));
        $this->assertNull($result->board->get(new Position(4, 1)));
        $this->assertCount(2, $result->captured);
    }

    public function test_placeStone_captures_opponent(): void
    {
        // White at (1,1) surrounded, Black completes the capture
        $board = Board::empty(9)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black)
            ->place(new Position(2, 1), Stone::Black)
            ->place(new Position(1, 1), Stone::White);

        $result = $board->placeStone(new Position(1, 2), Stone::Black);

        $this->assertNull($result->board->get(new Position(1, 1)));
        $this->assertSame(Stone::Black, $result->board->get(new Position(1, 2)));
        $this->assertCount(1, $result->captured);
    }

    public function test_captured_positions_are_correct(): void
    {
        $board = Board::empty(9)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black)
            ->place(new Position(2, 1), Stone::Black)
            ->place(new Position(1, 2), Stone::Black)
            ->place(new Position(1, 1), Stone::White);

        $result = $board->removeCaptured(Stone::White);

        $this->assertTrue($result->captured[0]->equals(new Position(1, 1)));
    }
}
