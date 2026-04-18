<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\Game\MessageSent;
use App\Events\Game\UnreadChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\SendMessageRequest;
use App\Jobs\SendNewMessageNotification;
use App\Models\Game;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

class MessageController extends Controller
{
    public function index(Request $request, Game $game): JsonResponse
    {
        $user = $request->user();

        if ($game->black_player_id !== $user->id && $game->white_player_id !== $user->id) {
            abort(403);
        }

        $query = Message::where('game_id', $game->id)
            ->with('user:id,name');

        $after = $request->query('after');
        if ($after !== null) {
            $query->where('id', '>', (int) $after);
        } else {
            $query->orderByDesc('id')->limit(50);
        }

        $messages = $query->orderBy('id')->get();

        return response()->json([
            'data' => $messages->map(fn (Message $m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'user_name' => $m->user->name,
                'text' => $m->text,
                'created_at' => $m->created_at->toISOString(),
            ]),
        ]);
    }

    public function store(SendMessageRequest $request, Game $game): JsonResponse
    {
        $user = $request->user();

        if ($game->black_player_id !== $user->id && $game->white_player_id !== $user->id) {
            abort(403);
        }

        $key = 'message:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 2)) {
            abort(429, 'Too many messages.');
        }
        RateLimiter::hit($key, 1);

        $message = Message::create([
            'game_id' => $game->id,
            'user_id' => $user->id,
            'text' => $request->validated('text'),
            'created_at' => now(),
        ]);

        MessageSent::dispatch(
            $game->id,
            $message->id,
            $user->id,
            $user->name,
            $message->text,
            $message->created_at->toISOString(),
        );

        $recipientId = $game->black_player_id === $user->id
            ? $game->white_player_id
            : $game->black_player_id;
        $unreadCount = (int) Game::withUnreadCount($recipientId)->find($game->id)->unread_count;
        UnreadChanged::dispatch($recipientId, $game->id, $unreadCount);

        $recipient = User::find($recipientId);
        if ($recipient && ! $recipient->is_bot) {
            SendNewMessageNotification::dispatch($game, $recipient, $user->name)
                ->delay(now()->addSeconds(60));
        }

        return response()->json([
            'data' => [
                'id' => $message->id,
                'user_id' => $user->id,
                'user_name' => $user->name,
                'text' => $message->text,
                'created_at' => $message->created_at->toISOString(),
            ],
        ], 201);
    }

    public function markRead(Request $request, Game $game): Response
    {
        $user = $request->user();

        if ($game->black_player_id !== $user->id && $game->white_player_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'last_read_id' => ['required', 'integer', 'min:1'],
        ]);
        $lastReadId = (int) $data['last_read_id'];

        $exists = Message::where('game_id', $game->id)->where('id', $lastReadId)->exists();
        if (! $exists) {
            abort(422, 'Message does not belong to this game.');
        }

        DB::table('game_read_states')->upsert(
            [[
                'game_id' => $game->id,
                'user_id' => $user->id,
                'last_read_message_id' => $lastReadId,
                'updated_at' => now(),
            ]],
            ['game_id', 'user_id'],
            ['last_read_message_id' => DB::raw('GREATEST(game_read_states.last_read_message_id, EXCLUDED.last_read_message_id)'), 'updated_at' => now()],
        );

        return response()->noContent();
    }
}
