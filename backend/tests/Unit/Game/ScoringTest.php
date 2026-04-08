<?php

declare(strict_types=1);

namespace Tests\Unit\Game;

use App\Game\Board;
use App\Game\Position;
use App\Game\Rules\ChineseRuleset;
use App\Game\Scoring\AreaScorer;
use App\Game\Stone;
use PHPUnit\Framework\TestCase;

class ScoringTest extends TestCase
{
    private AreaScorer $scorer;

    private ChineseRuleset $rules;

    protected function setUp(): void
    {
        $this->scorer = new AreaScorer;
        $this->rules = new ChineseRuleset;
    }

    public function test_empty_board_only_komi_counts(): void
    {
        $board = Board::empty(9);
        $score = $this->scorer->score($board, [], komi: 5.5);

        $this->assertSame(0.0, $score->black);
        $this->assertSame(5.5, $score->white);
        $this->assertSame(Stone::White, $score->winner);
        $this->assertSame(5.5, $score->margin);
    }

    public function test_black_wins_with_more_stones_and_territory(): void
    {
        // Black occupies entire top half of 9x9 board
        $board = Board::empty(9);

        for ($x = 0; $x < 9; $x++) {
            $board = $board->place(new Position($x, 4), Stone::Black);
        }

        $score = $this->scorer->score($board, [], komi: 5.5);

        // Black has 9 stones + territory above (rows 0-3 = 36 cells)
        $this->assertSame(Stone::Black, $score->winner);
    }

    public function test_dead_stones_are_removed_before_scoring(): void
    {
        $board = Board::empty(9)
            ->place(new Position(0, 0), Stone::White)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black);

        $dead = [new Position(0, 0)];
        $score = $this->scorer->score($board, $dead, komi: 0.0);

        // White stone removed; Black has 2 stones + all 79 empty cells as territory
        $this->assertSame(81.0, $score->black);
        $this->assertSame(0.0, $score->white);
        $this->assertSame(Stone::Black, $score->winner);
    }

    public function test_dame_does_not_count_for_either_player(): void
    {
        // Two stones facing each other — middle cell is dame
        $board = Board::empty(9)
            ->place(new Position(0, 0), Stone::Black)
            ->place(new Position(2, 0), Stone::White);

        $score = $this->scorer->score($board, [], komi: 0.0);

        // (1,0) is dame — neither player gets it
        $this->assertSame(1.0, $score->black); // 1 stone, no territory
        $this->assertSame(1.0, $score->white); // 1 stone, no territory
    }

    public function test_result_string_format(): void
    {
        $board = Board::empty(9);
        $score = $this->scorer->score($board, [], komi: 5.5);

        $this->assertSame('W+5.5', $score->result());
    }

    public function test_komi_added_to_white(): void
    {
        $board = Board::empty(9)
            ->place(new Position(0, 0), Stone::Black);

        $score = $this->scorer->score($board, [], komi: 7.5);

        // Black has 1 stone + 80 territory cells = 81; White has only komi
        $this->assertSame(81.0, $score->black);
        $this->assertSame(7.5, $score->white);
        $this->assertSame(Stone::Black, $score->winner);
    }
}
