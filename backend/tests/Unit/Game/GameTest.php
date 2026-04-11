<?php

declare(strict_types=1);

namespace Tests\Unit\Game;

use App\Game\Board;
use App\Game\Exceptions\IllegalMoveException;
use App\Game\Game;
use App\Game\GamePhase;
use App\Game\Move;
use App\Game\Position;
use App\Game\Rules\ChineseRuleset;
use App\Game\Stone;
use PHPUnit\Framework\TestCase;

class GameTest extends TestCase
{
    private ChineseRuleset $rules;

    protected function setUp(): void
    {
        $this->rules = new ChineseRuleset;
    }

    public function test_new_game_starts_in_playing_phase(): void
    {
        $game = Game::start(9, $this->rules);

        $this->assertSame(GamePhase::Playing, $game->phase);
    }

    public function test_new_game_starts_with_black_turn(): void
    {
        $game = Game::start(9, $this->rules);

        $this->assertSame(Stone::Black, $game->currentTurn);
    }

    public function test_play_move_switches_turn(): void
    {
        $game = Game::start(9, $this->rules)
            ->apply(Move::play(Stone::Black, new Position(3, 3)));

        $this->assertSame(Stone::White, $game->currentTurn);
    }

    public function test_illegal_move_throws_exception(): void
    {
        $game = Game::start(9, $this->rules)
            ->apply(Move::play(Stone::Black, new Position(3, 3)));

        $this->expectException(IllegalMoveException::class);
        $game->apply(Move::play(Stone::White, new Position(3, 3)));
    }

    public function test_wrong_turn_throws_exception(): void
    {
        $game = Game::start(9, $this->rules);

        $this->expectException(IllegalMoveException::class);
        $game->apply(Move::play(Stone::White, new Position(3, 3)));
    }

    public function test_two_consecutive_passes_transition_to_marking_dead(): void
    {
        $game = Game::start(9, $this->rules)
            ->apply(Move::pass(Stone::Black))
            ->apply(Move::pass(Stone::White));

        $this->assertSame(GamePhase::MarkingDead, $game->phase);
    }

    public function test_one_pass_does_not_end_game(): void
    {
        $game = Game::start(9, $this->rules)
            ->apply(Move::pass(Stone::Black));

        $this->assertSame(GamePhase::Playing, $game->phase);
    }

    public function test_pass_then_play_resets_consecutive_passes(): void
    {
        $game = Game::start(9, $this->rules)
            ->apply(Move::pass(Stone::Black))
            ->apply(Move::play(Stone::White, new Position(3, 3)))
            ->apply(Move::pass(Stone::Black));

        $this->assertSame(GamePhase::Playing, $game->phase);
        $this->assertSame(1, $game->consecutivePasses);
    }

    public function test_resign_transitions_to_finished(): void
    {
        $game = Game::start(9, $this->rules)
            ->apply(Move::resign(Stone::Black));

        $this->assertSame(GamePhase::Finished, $game->phase);
    }

    public function test_move_after_finished_throws_exception(): void
    {
        $game = Game::start(9, $this->rules)
            ->apply(Move::resign(Stone::Black));

        $this->expectException(IllegalMoveException::class);
        $game->apply(Move::play(Stone::White, new Position(3, 3)));
    }

    public function test_history_grows_with_each_move(): void
    {
        $game = Game::start(9, $this->rules)
            ->apply(Move::play(Stone::Black, new Position(3, 3)))
            ->apply(Move::pass(Stone::White))
            ->apply(Move::resign(Stone::Black));

        $this->assertCount(3, $game->history);
    }

    public function test_game_is_immutable(): void
    {
        $game = Game::start(9, $this->rules);
        $game->apply(Move::play(Stone::Black, new Position(3, 3)));

        $this->assertSame(Stone::Black, $game->currentTurn);
        $this->assertCount(0, $game->history);
    }

    public function test_last_captures_contains_captured_positions(): void
    {
        // Set up a board where Black can capture White at (1,1)
        //   0 1 2
        // 0 . B .
        // 1 B W .   ← Black plays (2,1) to capture White at (1,1)
        // 2 . B .
        $board = Board::empty(9)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black)
            ->place(new Position(1, 2), Stone::Black)
            ->place(new Position(1, 1), Stone::White);

        $game = Game::restore(
            board: $board,
            currentTurn: Stone::Black,
            phase: GamePhase::Playing,
            ruleset: $this->rules,
            history: [],
            consecutivePasses: 0,
            koHash: null,
            proposedDeadStones: null,
            proposedBy: null,
            score: null,
        );

        $after = $game->apply(Move::play(Stone::Black, new Position(2, 1)));

        $this->assertCount(1, $after->lastCaptures);
        $this->assertTrue($after->lastCaptures[0]->equals(new Position(1, 1)));
        $this->assertNull($after->board->get(new Position(1, 1)));
    }

    public function test_last_captures_empty_when_no_capture(): void
    {
        $game = Game::start(9, $this->rules)
            ->apply(Move::play(Stone::Black, new Position(3, 3)));

        $this->assertCount(0, $game->lastCaptures);
    }

    public function test_ko_rule_prevents_immediate_recapture(): void
    {
        // Classic Ko shape:
        //   0 1 2 3
        // 0 . B W .
        // 1 B W . W
        // 2 . B W .
        $board = Board::empty(9)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black)
            ->place(new Position(1, 2), Stone::Black)
            ->place(new Position(2, 0), Stone::White)
            ->place(new Position(1, 1), Stone::White)
            ->place(new Position(3, 1), Stone::White)
            ->place(new Position(2, 2), Stone::White);

        $game = Game::restore(
            board: $board,
            currentTurn: Stone::Black,
            phase: GamePhase::Playing,
            ruleset: $this->rules,
            history: [],
            consecutivePasses: 0,
            koHash: null,
            proposedDeadStones: null,
            proposedBy: null,
            score: null,
        );

        // Black captures White at (1,1) by playing (2,1)
        $afterCapture = $game->apply(Move::play(Stone::Black, new Position(2, 1)));

        // White tries to recapture at (1,1) — Ko violation
        $this->expectException(IllegalMoveException::class);
        $afterCapture->apply(Move::play(Stone::White, new Position(1, 1)));
    }

    public function test_pass_resets_ko_restriction(): void
    {
        $board = Board::empty(9)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black)
            ->place(new Position(1, 2), Stone::Black)
            ->place(new Position(2, 0), Stone::White)
            ->place(new Position(1, 1), Stone::White)
            ->place(new Position(3, 1), Stone::White)
            ->place(new Position(2, 2), Stone::White);

        $game = Game::restore(
            board: $board,
            currentTurn: Stone::Black,
            phase: GamePhase::Playing,
            ruleset: $this->rules,
            history: [],
            consecutivePasses: 0,
            koHash: null,
            proposedDeadStones: null,
            proposedBy: null,
            score: null,
        );

        // Black captures at (2,1)
        $afterCapture = $game->apply(Move::play(Stone::Black, new Position(2, 1)));

        // White passes (resets Ko)
        $afterPass = $afterCapture->apply(Move::pass(Stone::White));
        $this->assertNull($afterPass->koHash);
    }
}
