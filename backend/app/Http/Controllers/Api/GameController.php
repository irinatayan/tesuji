<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Game\Exceptions\IllegalMoveException;
use App\Game\Move as DomainMove;
use App\Game\Position;
use App\Game\Stone;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateGameRequest;
use App\Http\Requests\PlayMoveRequest;
use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Models\User;
use App\Services\GameService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
    public function __construct(private readonly GameService $gameService) {}

    public function store(CreateGameRequest $request): JsonResponse
    {
        $color = $request->color === 'random'
            ? (rand(0, 1) === 0 ? 'black' : 'white')
            : $request->color;

        $opponent = User::findOrFail($request->opponent_id);

        [$blackId, $whiteId] = $color === 'black'
            ? [$request->user()->id, $opponent->id]
            : [$opponent->id, $request->user()->id];

        $game = Game::create([
            'black_player_id' => $blackId,
            'white_player_id' => $whiteId,
            'mode' => $request->mode,
            'ruleset' => 'chinese',
            'board_size' => $request->board_size,
            'status' => 'playing',
            'current_turn' => 'black',
            'time_control_type' => $request->time_control_type,
            'time_control_config' => $request->time_control_config,
            'started_at' => now(),
        ]);

        $game->load(['blackPlayer', 'whitePlayer', 'moves']);

        return (new GameResource($game))->response()->setStatusCode(201);
    }

    public function show(Request $request, Game $game): GameResource
    {
        $game->load(['blackPlayer', 'whitePlayer', 'moves']);

        return new GameResource($game);
    }

    public function move(PlayMoveRequest $request, Game $game): GameResource|JsonResponse
    {
        $stone = $this->resolvePlayerStone($request, $game);

        if ($stone === null) {
            return response()->json(['message' => 'You are not a participant of this game.'], 403);
        }

        $move = DomainMove::play($stone, new Position($request->x, $request->y));

        try {
            $game = $this->gameService->applyMove($game, $move);
        } catch (IllegalMoveException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $game->load(['blackPlayer', 'whitePlayer', 'moves']);

        return new GameResource($game);
    }

    public function pass(Request $request, Game $game): GameResource|JsonResponse
    {
        $stone = $this->resolvePlayerStone($request, $game);

        if ($stone === null) {
            return response()->json(['message' => 'You are not a participant of this game.'], 403);
        }

        try {
            $game = $this->gameService->applyMove($game, DomainMove::pass($stone));
        } catch (IllegalMoveException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $game->load(['blackPlayer', 'whitePlayer', 'moves']);

        return new GameResource($game);
    }

    public function resign(Request $request, Game $game): GameResource|JsonResponse
    {
        $stone = $this->resolvePlayerStone($request, $game);

        if ($stone === null) {
            return response()->json(['message' => 'You are not a participant of this game.'], 403);
        }

        try {
            $game = $this->gameService->applyMove($game, DomainMove::resign($stone));
        } catch (IllegalMoveException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $game->load(['blackPlayer', 'whitePlayer', 'moves']);

        return new GameResource($game);
    }

    private function resolvePlayerStone(Request $request, Game $game): ?Stone
    {
        $userId = $request->user()->id;

        if ($userId === $game->black_player_id) {
            return Stone::Black;
        }

        if ($userId === $game->white_player_id) {
            return Stone::White;
        }

        return null;
    }
}
