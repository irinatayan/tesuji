<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
        ]);

        $users = User::where('id', '!=', $request->user()->id)
            ->where('is_bot', false)
            ->where(function ($query) use ($request): void {
                $query->whereRaw('name ILIKE ?', ['%'.$request->search.'%'])
                    ->orWhereRaw('email ILIKE ?', ['%'.$request->search.'%']);
            })
            ->select('id', 'name')
            ->limit(20)
            ->get();

        return response()->json($users);
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
        return $this->show($request, $request->user());
    }
}
