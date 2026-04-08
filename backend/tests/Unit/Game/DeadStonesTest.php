<?php

declare(strict_types=1);

namespace Tests\Unit\Game;

use App\Game\Game;
use App\Game\GamePhase;
use App\Game\Move;
use App\Game\Position;
use App\Game\Rules\ChineseRuleset;
use App\Game\Stone;
use PHPUnit\Framework\TestCase;

class DeadStonesTest extends TestCase
{
    private ChineseRuleset $rules;

    protected function setUp(): void
    {
        $this->rules = new ChineseRuleset;
    }

    private function gameInMarkingDead(): Game
    {
        return Game::start(9, $this->rules)
            ->apply(Move::pass(Stone::Black))
            ->apply(Move::pass(Stone::White));
    }

    public function test_game_enters_marking_dead_after_two_passes(): void
    {
        $game = $this->gameInMarkingDead();

        $this->assertSame(GamePhase::MarkingDead, $game->phase);
    }

    public function test_mark_dead_stores_proposal(): void
    {
        $dead = [new Position(3, 3)];
        $game = $this->gameInMarkingDead()->markDead($dead, Stone::Black);

        $this->assertSame($dead, $game->proposedDeadStones);
        $this->assertSame(Stone::Black, $game->proposedBy);
    }

    public function test_confirm_dead_by_opponent_finishes_game(): void
    {
        $game = $this->gameInMarkingDead()
            ->markDead([], Stone::Black)
            ->confirmDead(Stone::White);

        $this->assertSame(GamePhase::Finished, $game->phase);
        $this->assertNotNull($game->score);
    }

    public function test_cannot_confirm_own_proposal(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->gameInMarkingDead()
            ->markDead([], Stone::Black)
            ->confirmDead(Stone::Black);
    }

    public function test_dispute_returns_to_playing(): void
    {
        $game = $this->gameInMarkingDead()
            ->markDead([new Position(3, 3)], Stone::Black)
            ->disputeDead(Stone::White);

        $this->assertSame(GamePhase::Playing, $game->phase);
        $this->assertNull($game->proposedDeadStones);
    }

    public function test_dispute_sets_turn_to_disputing_player(): void
    {
        $game = $this->gameInMarkingDead()
            ->markDead([], Stone::Black)
            ->disputeDead(Stone::White);

        $this->assertSame(Stone::White, $game->currentTurn);
    }

    public function test_score_is_calculated_on_confirm(): void
    {
        $game = Game::start(9, $this->rules)
            ->apply(Move::play(Stone::Black, new Position(0, 0)))
            ->apply(Move::pass(Stone::White))
            ->apply(Move::pass(Stone::Black))
            ->markDead([], Stone::White)
            ->confirmDead(Stone::Black);

        $this->assertNotNull($game->score);
        $this->assertSame(GamePhase::Finished, $game->phase);
    }

    public function test_cannot_mark_dead_outside_marking_phase(): void
    {
        $this->expectException(\RuntimeException::class);

        Game::start(9, $this->rules)->markDead([], Stone::Black);
    }
}
