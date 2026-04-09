<?php

declare(strict_types=1);

namespace Tests\Feature\Game;

use App\Game\Board;
use App\Game\GamePhase;
use App\Game\Move as DomainMove;
use App\Game\Persistence\BoardSerializer;
use App\Game\Persistence\GameMapper;
use App\Game\Position;
use App\Game\Stone;
use App\Models\Game as GameModel;
use App\Models\Move as MoveModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameMapperTest extends TestCase
{
    use RefreshDatabase;

    private GameMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mapper = new GameMapper;
    }

    public function test_restore_game_without_moves_returns_empty_board(): void
    {
        $model = GameModel::factory()->create(['board_size' => 9, 'status' => 'playing']);

        $game = $this->mapper->restore($model);

        $this->assertSame(GamePhase::Playing, $game->phase);
        $this->assertSame(Stone::Black, $game->currentTurn);
        $this->assertTrue($game->board->isEmpty());
    }

    public function test_restore_game_deserializes_board_state_from_last_move(): void
    {
        $model = GameModel::factory()->create(['board_size' => 9]);

        $board = $model->blackPlayer->gamesAsBlack()->first()->board_size;
        $emptyBoard = Board::empty(9)->place(new Position(0, 0), Stone::Black);
        $boardState = BoardSerializer::serialize($emptyBoard);

        MoveModel::factory()->create([
            'game_id' => $model->id,
            'move_number' => 1,
            'color' => 'black',
            'type' => 'play',
            'x' => 0,
            'y' => 0,
            'board_state' => $boardState,
            'position_hash' => str_repeat('a', 64),
        ]);

        $game = $this->mapper->restore($model);

        $this->assertSame(Stone::Black, $game->board->get(new Position(0, 0)));
    }

    public function test_persist_move_creates_move_record(): void
    {
        $model = GameModel::factory()->create(['board_size' => 9, 'status' => 'playing']);
        $domainGame = $this->mapper->restore($model);

        $move = DomainMove::pass(Stone::Black);
        $domainGame = $domainGame->apply($move);

        $this->mapper->persistMove($domainGame, $model, $move, 1);

        $this->assertDatabaseHas('moves', [
            'game_id' => $model->id,
            'move_number' => 1,
            'type' => 'pass',
            'color' => 'black',
        ]);
    }

    public function test_persist_move_updates_last_move_at(): void
    {
        $model = GameModel::factory()->create(['board_size' => 9, 'status' => 'playing', 'last_move_at' => null]);
        $domainGame = $this->mapper->restore($model);

        $move = DomainMove::pass(Stone::Black);
        $domainGame = $domainGame->apply($move);

        $this->mapper->persistMove($domainGame, $model, $move, 1);

        $this->assertNotNull($model->fresh()->last_move_at);
    }

    public function test_roundtrip_persist_and_restore(): void
    {
        $model = GameModel::factory()->create(['board_size' => 9, 'status' => 'playing']);
        $domainGame = $this->mapper->restore($model);

        $move = DomainMove::play(Stone::Black, new Position(3, 3));
        $domainGame = $domainGame->apply($move);

        $this->mapper->persistMove($domainGame, $model, $move, 1);
        $model->refresh();

        $restored = $this->mapper->restore($model);

        $this->assertSame(Stone::Black, $restored->board->get(new Position(3, 3)));
        $this->assertSame(Stone::White, $restored->currentTurn);
    }
}
