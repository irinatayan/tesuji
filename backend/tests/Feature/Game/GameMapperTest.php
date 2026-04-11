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

    public function test_persist_move_saves_captures(): void
    {
        $model = GameModel::factory()->create(['board_size' => 9, 'status' => 'playing']);

        // Set up: board with a capturable White stone
        //   0 1 2
        // 0 . B .
        // 1 B W .   ← Black plays (2,1) to capture White at (1,1)
        // 2 . B .
        $boardBefore = Board::empty(9)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black)
            ->place(new Position(1, 2), Stone::Black)
            ->place(new Position(1, 1), Stone::White);

        // Persist a setup move so the board state exists
        MoveModel::create([
            'game_id' => $model->id,
            'move_number' => 1,
            'color' => 'white',
            'type' => 'play',
            'x' => 1,
            'y' => 1,
            'captures' => [],
            'board_state' => BoardSerializer::serialize($boardBefore),
            'position_hash' => $boardBefore->hash(),
            'played_at' => now(),
        ]);
        $model->update(['current_turn' => 'black']);
        $model->refresh();

        $domainGame = $this->mapper->restore($model);
        $move = DomainMove::play(Stone::Black, new Position(2, 1));
        $domainGame = $domainGame->apply($move);

        $this->mapper->persistMove($domainGame, $model, $move, 2);

        $savedMove = MoveModel::where('game_id', $model->id)->where('move_number', 2)->first();
        $this->assertNotNull($savedMove);
        $this->assertEquals([['x' => 1, 'y' => 1]], $savedMove->captures);
    }

    public function test_restore_ko_hash_from_previous_move(): void
    {
        $model = GameModel::factory()->create(['board_size' => 9, 'status' => 'playing', 'current_turn' => 'white']);

        // Board before capture (the "previous" board state)
        $boardBefore = Board::empty(9)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black)
            ->place(new Position(1, 2), Stone::Black)
            ->place(new Position(2, 0), Stone::White)
            ->place(new Position(1, 1), Stone::White)
            ->place(new Position(3, 1), Stone::White)
            ->place(new Position(2, 2), Stone::White);

        // Board after Black captures at (2,1): White at (1,1) removed
        $boardAfter = Board::empty(9)
            ->place(new Position(1, 0), Stone::Black)
            ->place(new Position(0, 1), Stone::Black)
            ->place(new Position(1, 2), Stone::Black)
            ->place(new Position(2, 1), Stone::Black)
            ->place(new Position(2, 0), Stone::White)
            ->place(new Position(3, 1), Stone::White)
            ->place(new Position(2, 2), Stone::White);

        // Move 1: sets up the position
        MoveModel::create([
            'game_id' => $model->id,
            'move_number' => 1,
            'color' => 'white',
            'type' => 'play',
            'x' => 3,
            'y' => 1,
            'captures' => [],
            'board_state' => BoardSerializer::serialize($boardBefore),
            'position_hash' => $boardBefore->hash(),
            'played_at' => now(),
        ]);

        // Move 2: Black captures at (2,1)
        MoveModel::create([
            'game_id' => $model->id,
            'move_number' => 2,
            'color' => 'black',
            'type' => 'play',
            'x' => 2,
            'y' => 1,
            'captures' => [['x' => 1, 'y' => 1]],
            'board_state' => BoardSerializer::serialize($boardAfter),
            'position_hash' => $boardAfter->hash(),
            'played_at' => now(),
        ]);

        $restored = $this->mapper->restore($model);

        // koHash should be boardBefore's hash (from move 1, not move 2)
        $this->assertSame($boardBefore->hash(), $restored->koHash);
    }

    public function test_restore_ko_hash_null_after_pass(): void
    {
        $model = GameModel::factory()->create(['board_size' => 9, 'status' => 'playing', 'current_turn' => 'black']);

        $board = Board::empty(9)->place(new Position(3, 3), Stone::Black);

        MoveModel::create([
            'game_id' => $model->id,
            'move_number' => 1,
            'color' => 'black',
            'type' => 'play',
            'x' => 3,
            'y' => 3,
            'captures' => [],
            'board_state' => BoardSerializer::serialize($board),
            'position_hash' => $board->hash(),
            'played_at' => now(),
        ]);

        MoveModel::create([
            'game_id' => $model->id,
            'move_number' => 2,
            'color' => 'white',
            'type' => 'pass',
            'x' => null,
            'y' => null,
            'captures' => [],
            'board_state' => BoardSerializer::serialize($board),
            'position_hash' => $board->hash(),
            'played_at' => now(),
        ]);

        $restored = $this->mapper->restore($model);
        $this->assertNull($restored->koHash);
    }
}
