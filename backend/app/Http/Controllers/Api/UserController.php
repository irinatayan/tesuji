<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = User::where('id', '!=', $request->user()->id)
            ->where('is_bot', false)
            ->select('id', 'name')
            ->orderBy('name');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->whereRaw('name ILIKE ?', ['%'.$search.'%'])
                    ->orWhereRaw('email ILIKE ?', ['%'.$search.'%']);
            });
        }

        $users = $query->paginate(20);

        return response()->json([
            'data' => $users->items(),
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $total = Game::where('status', 'finished')
            ->where(fn ($q) => $q
                ->where('black_player_id', $user->id)
                ->orWhere('white_player_id', $user->id)
            )->count();

        $wins = Game::where('status', 'finished')
            ->where(fn ($q) => $q
                ->where(fn ($q) => $q
                    ->where('black_player_id', $user->id)
                    ->where('result', 'like', 'B+%')
                )
                ->orWhere(fn ($q) => $q
                    ->where('white_player_id', $user->id)
                    ->where('result', 'like', 'W+%')
                )
            )->count();

        $losses = $total - $wins;

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'created_at' => $user->created_at,
            'stats' => [
                'total' => $total,
                'wins' => $wins,
                'losses' => $losses,
                'win_rate' => $total > 0 ? round($wins / $total * 100, 1) : 0,
            ],
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        $user = $request->user();
        $base = $this->show($request, $user);

        return response()->json(array_merge($base->getData(true), [
            'telegram_connected' => $user->telegram_chat_id !== null,
            'notification_preferences' => $user->notification_preferences ?? [],
        ]));
    }

    public function updatePreferences(Request $request): Response
    {
        $events = ['new_message', 'opponent_moved', 'invitation', 'game_finished'];
        $channels = ['telegram', 'mail'];

        $rules = [];
        foreach ($events as $event) {
            foreach ($channels as $channel) {
                $rules["{$event}.{$channel}"] = ['boolean'];
            }
        }

        $validated = $request->validate($rules);
        $request->user()->update(['notification_preferences' => $validated]);

        return response()->noContent();
    }

    public function games(Request $request, User $user): JsonResponse
    {
        $games = Game::where('status', 'finished')
            ->where(fn ($q) => $q
                ->where('black_player_id', $user->id)
                ->orWhere('white_player_id', $user->id)
            )
            ->with(['blackPlayer:id,name', 'whitePlayer:id,name'])
            ->select('id', 'mode', 'board_size', 'result', 'started_at', 'finished_at', 'black_player_id', 'white_player_id')
            ->orderByDesc('finished_at')
            ->paginate(20);

        return response()->json($games);
    }
}
