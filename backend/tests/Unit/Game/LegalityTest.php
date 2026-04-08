<?php

declare(strict_types=1);

namespace Tests\Unit\Game;

use App\Game\Board;
use App\Game\Position;
use App\Game\Rules\ChineseRuleset;
use App\Game\Stone;
use PHPUnit\Framework\TestCase;

class LegalityTest extends TestCase
{
    private ChineseRuleset $rules;

    protected function setUp(): void
    {
        $this->rules = new ChineseRuleset;
    }

    public function test_move_on_occupied_cell_is_illegal(): void
    {
        $board = Board::empty(9)->place(new Position(3, 3), Stone::Black);

        $this->assertFalse($board->isLegalMove(new Position(3, 3), Stone::White, $this->rules));
    }

    public function test_normal_move_is_legal(): void
    {
        $board = Board::empty(9);

        $this->assertTrue($board->isLegalMove(new Position(3, 3), Stone::Black, $this->rules));
    }

    public function test_suicide_move_is_illegal(): void
    {
        // Black surrounds (0,0), White tries to play there — suicide
        $board = Board::empty(9)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black);

        $this->assertFalse($board->isLegalMove(new Position(0, 0), Stone::White, $this->rules));
    }

    public function test_move_that_captures_before_suicide_is_legal(): void
    {
        // White plays into surrounded area but captures a Black stone first
        $board = Board::empty(9)
            ->place(new Position(0, 1), Stone::White)
            ->place(new Position(1, 0), Stone::White)
            ->place(new Position(1, 1), Stone::Black)
            ->place(new Position(2, 0), Stone::Black);

        // White at (0,0) captures Black at (1,0)? No — let's set up a real capture-before-suicide
        // White plays (0,0): surrounded by Black except (1,0) has Black which White captures
        $board2 = Board::empty(9)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black)
            ->place(new Position(0, 0), Stone::White);

        // After White plays (0,0), Black at (1,0) and (0,1) still have liberties — White has no liberties
        // This IS suicide for White — illegal
        $this->assertFalse($board2->isLegalMove(new Position(0, 0), Stone::White, $this->rules));

        // Now: White plays into a spot that captures Black first, leaving White with liberties
        $board3 = Board::empty(9)
            ->place(new Position(0, 1), Stone::White)
            ->place(new Position(1, 0), Stone::White)
            ->place(new Position(2, 1), Stone::Black)
            ->place(new Position(1, 2), Stone::Black)
            ->place(new Position(1, 1), Stone::Black);

        // White at (1,0) already placed; White plays (0,0) — not a capture scenario here
        // Cleaner test: White surrounds a single Black stone, then plays capturing it
        $board4 = Board::empty(9)
            ->place(new Position(0, 0), Stone::Black)
            ->place(new Position(2, 0), Stone::White)
            ->place(new Position(1, 1), Stone::White)
            ->place(new Position(0, 1), Stone::White);

        // White plays (1,0): captures Black at (0,0), White group at (1,0) has liberty at (0,0) after capture
        $this->assertTrue($board4->isLegalMove(new Position(1, 0), Stone::White, $this->rules));
    }

    public function test_ko_move_is_illegal(): void
    {
        // Classic ko shape:
        // . B . .
        // B W B .
        // . B . .
        $board = Board::empty(9)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black)
            ->place(new Position(2, 1), Stone::Black)
            ->place(new Position(1, 2), Stone::Black)
            ->place(new Position(1, 1), Stone::White);

        // Black captures White at (1,1)
        $afterCapture = $board->placeStone(new Position(1, 1), Stone::Black);
        $hashBeforeCapture = $board->hash();

        // White tries to recapture at (1,1) — would restore position before Black's move
        $this->assertFalse(
            $afterCapture->board->isLegalMove(new Position(1, 1), Stone::White, $this->rules, $hashBeforeCapture)
        );
    }

    public function test_move_after_ko_elsewhere_is_legal(): void
    {
        $board = Board::empty(9);
        $hashTwoMovesAgo = Board::empty(9)->hash();

        // Playing somewhere that does NOT restore the old position is legal
        $this->assertTrue($board->isLegalMove(new Position(5, 5), Stone::Black, $this->rules, $hashTwoMovesAgo));
    }
}
