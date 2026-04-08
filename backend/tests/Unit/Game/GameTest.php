<?php

declare(strict_types=1);

namespace Tests\Unit\Game;

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
}
