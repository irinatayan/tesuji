<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateGameRequest;
use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameController extends Controller
{
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
}
