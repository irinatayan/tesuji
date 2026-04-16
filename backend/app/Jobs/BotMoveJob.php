<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Game\Board;
use App\Game\Engines\EngineMove;
use App\Game\Engines\GoEngine;
use App\Game\Move as DomainMove;
use App\Game\MoveType;
use App\Game\Persistence\BoardSerializer;
use App\Game\Position;
use App\Game\Stone;
use App\Models\Game;
use App\Models\Move as MoveModel;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class BotMoveJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 30;

    public function __construct(public readonly int $gameId) {}

    public function handle(GameService $gameService, GoEngine $engine): void
    {
        $game = Game::with('moves')->find($this->gameId);

        if ($game === null || $game->status !== 'playing') {
            return;
        }

        $botUser = $this->currentPlayer($game);

        if ($botUser === null || ! $botUser->is_bot) {
            return;
        }

        $stone = $game->current_turn === 'black' ? Stone::Black : Stone::White;
        $lastMove = $game->moves->last();

        $board = $lastMove !== null
            ? BoardSerializer::deserialize($lastMove->board_state, $game->board_size)
            : Board::empty($game->board_size);

        $history = $game->moves->map(fn (MoveModel $m) => $this->toDomainMove($m))->all();

        $engineMove = $engine->suggestMove($board, $stone, $history);

        $domainMove = $this->toDomainMoveFromEngine($engineMove, $stone);

        $gameService->applyMove($game, $domainMove);

        Log::info("BotMoveJob: bot played {$domainMove->type->name} in game {$this->gameId}");
    }

    private function currentPlayer(Game $game): ?User
    {
        $id = $game->current_turn === 'black'
            ? $game->black_player_id
            : $game->white_player_id;

        return User::find($id);
    }

    private function toDomainMove(MoveModel $model): DomainMove
    {
        $color = $model->color === 'black' ? Stone::Black : Stone::White;

        return match ($model->type) {
            'play' => DomainMove::play($color, new Position($model->x, $model->y)),
            'pass' => DomainMove::pass($color),
            'resign' => DomainMove::resign($color),
        };
    }

    private function toDomainMoveFromEngine(EngineMove $engineMove, Stone $stone): DomainMove
    {
        return match ($engineMove->type) {
            MoveType::Play => DomainMove::play($stone, $engineMove->position),
            MoveType::Pass => DomainMove::pass($stone),
            MoveType::Resign => DomainMove::resign($stone),
        };
    }
}
