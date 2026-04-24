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
            komi: 5.5,
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
            komi: 5.5,
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

    public function test_resign_allowed_on_opponents_turn(): void
    {
        $game = Game::start(9, $this->rules)
            ->apply(Move::resign(Stone::White));

        $this->assertSame(GamePhase::Finished, $game->phase);
    }

    public function test_resign_allowed_during_scoring(): void
    {
        $game = Game::start(9, $this->rules)
            ->apply(Move::pass(Stone::Black))
            ->apply(Move::pass(Stone::White))
            ->apply(Move::resign(Stone::Black));

        $this->assertSame(GamePhase::Finished, $game->phase);
    }

    public function test_resign_not_allowed_after_finished(): void
    {
        $game = Game::start(9, $this->rules)
            ->apply(Move::resign(Stone::Black));

        $this->expectException(IllegalMoveException::class);
        $game->apply(Move::resign(Stone::White));
    }

    public function test_suicide_after_repeated_capture_sequence(): void
    {
        // Reproduces the sequence from game-25.sgf (moves 52–56) where White tried to
        // play at (1,0) after Black had captured it there twice in a row.
        //
        // Initial:
        //   col: 0  1  2
        // row 0: W  .  B   ← W[aa] already on board, B[ca]
        // row 1: .  B  .   ← B[bb]
        //
        // Sequence:
        //   W plays (1,0)  → group {(0,0),(1,0)} has liberty at (0,1)
        //   B plays (0,1)  → captures both White stones
        //   W plays (1,0)  → liberty at (0,0) [now empty]
        //   B plays (0,0)  → captures White at (1,0)
        //   W tries (1,0)  → (0,0)=B, (2,0)=B, (1,1)=B → suicide, must throw

        $board = Board::empty(9)
            ->place(new Position(0, 0), Stone::White)
            ->place(new Position(2, 0), Stone::Black)
            ->place(new Position(1, 1), Stone::Black);

        $game = Game::restore(
            board: $board,
            currentTurn: Stone::White,
            phase: GamePhase::Playing,
            ruleset: $this->rules,
            komi: 5.5,
            history: [],
            consecutivePasses: 0,
            koHash: null,
            proposedDeadStones: null,
            proposedBy: null,
            score: null,
        );

        $game = $game->apply(Move::play(Stone::White, new Position(1, 0)));
        $game = $game->apply(Move::play(Stone::Black, new Position(0, 1)));
        $game = $game->apply(Move::play(Stone::White, new Position(1, 0)));
        $game = $game->apply(Move::play(Stone::Black, new Position(0, 0)));

        $this->expectException(IllegalMoveException::class);
        $this->expectExceptionMessage('suicide');
        $game->apply(Move::play(Stone::White, new Position(1, 0)));
    }

    public function test_game_with_handicap_stones_has_them_on_board(): void
    {
        $stones = [new Position(2, 2), new Position(6, 6)];

        $game = Game::start(9, $this->rules, $stones);

        $this->assertSame(Stone::Black, $game->board->get(new Position(2, 2)));
        $this->assertSame(Stone::Black, $game->board->get(new Position(6, 6)));
    }

    public function test_game_with_handicap_stones_gives_white_first_turn(): void
    {
        $game = Game::start(9, $this->rules, [new Position(2, 2), new Position(6, 6)]);

        $this->assertSame(Stone::White, $game->currentTurn);
    }

    public function test_game_without_handicap_gives_black_first_turn(): void
    {
        $game = Game::start(9, $this->rules);

        $this->assertSame(Stone::Black, $game->currentTurn);
    }

    public function test_game_with_handicap_has_empty_history(): void
    {
        $game = Game::start(9, $this->rules, [new Position(2, 2), new Position(6, 6)]);

        $this->assertCount(0, $game->history);
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
            komi: 5.5,
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
