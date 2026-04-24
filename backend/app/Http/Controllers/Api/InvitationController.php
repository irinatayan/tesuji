<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Events\Invitation\InvitationAccepted;
use App\Events\Invitation\InvitationDeclined;
use App\Events\Invitation\InvitationReceived;
use App\Game\Handicap;
use App\Game\Rules\ChineseRuleset;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateInvitationRequest;
use App\Models\Game;
use App\Models\GameInvitation;
use App\Models\User;
use App\Notifications\InvitationReceivedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvitationController extends Controller
{
    public function store(CreateInvitationRequest $request): JsonResponse
    {
        $existing = GameInvitation::where('from_user_id', $request->user()->id)
            ->where('to_user_id', $request->to_user_id)
            ->where('status', 'pending')
            ->exists();

        if ($existing) {
            return response()->json(['message' => __('messages.invitation_duplicate')], 422);
        }

        if ($request->mode === 'realtime') {
            $hasActive = Game::where('mode', 'realtime')
                ->where('status', 'playing')
                ->where(fn ($q) => $q->where('black_player_id', $request->user()->id)->orWhere('white_player_id', $request->user()->id))
                ->exists();

            if ($hasActive) {
                return response()->json(['message' => __('messages.invitation_active_game')], 422);
            }
        }

        $invitation = GameInvitation::create([
            'from_user_id' => $request->user()->id,
            'to_user_id' => $request->to_user_id,
            'board_size' => $request->board_size,
            'mode' => $request->mode,
            'ruleset' => 'chinese',
            'time_control_type' => $request->time_control_type,
            'time_control_config' => $request->time_control_config,
            'proposed_color' => $request->proposed_color,
            'handicap' => (int) $request->input('handicap', 0),
            'handicap_placement' => $request->input('handicap_placement', 'fixed'),
            'status' => 'pending',
            'expires_at' => now()->addDays(3),
        ]);

        event(new InvitationReceived(
            invitationId: $invitation->id,
            toUserId: $invitation->to_user_id,
            from: ['id' => $request->user()->id, 'name' => $request->user()->name],
            boardSize: $invitation->board_size,
            mode: $invitation->mode,
        ));

        $invitation->load('fromUser');
        User::find($invitation->to_user_id)?->notify(new InvitationReceivedNotification($invitation));

        return response()->json($invitation->load(['fromUser:id,name', 'toUser:id,name']), 201);
    }

    public function incoming(Request $request): JsonResponse
    {
        $invitations = GameInvitation::where('to_user_id', $request->user()->id)
            ->where('status', 'pending')
            ->with(['fromUser:id,name'])
            ->latest()
            ->get();

        return response()->json($invitations);
    }

    public function outgoing(Request $request): JsonResponse
    {
        $invitations = GameInvitation::where('from_user_id', $request->user()->id)
            ->where('status', 'pending')
            ->with(['toUser:id,name'])
            ->latest()
            ->get();

        return response()->json($invitations);
    }

    public function accept(Request $request, GameInvitation $invitation): JsonResponse
    {
        if ($invitation->to_user_id !== $request->user()->id) {
            return response()->json(['message' => __('messages.invitation_not_for_you')], 403);
        }

        if ($invitation->status !== 'pending') {
            return response()->json(['message' => __('messages.invitation_not_pending')], 422);
        }

        if ($invitation->mode === 'realtime') {
            foreach ([$invitation->from_user_id, $invitation->to_user_id] as $playerId) {
                $hasActive = Game::where('mode', 'realtime')
                    ->where('status', 'playing')
                    ->where(fn ($q) => $q->where('black_player_id', $playerId)->orWhere('white_player_id', $playerId))
                    ->exists();

                if ($hasActive) {
                    $key = $playerId === $request->user()->id
                        ? 'messages.invitation_accept_self_active'
                        : 'messages.invitation_accept_opponent_active';

                    return response()->json(['message' => __($key)], 422);
                }
            }
        }

        $game = DB::transaction(function () use ($invitation): Game {
            // proposed_color = color chosen FOR THE OPPONENT (the invitee).
            // Handicap stones always go to the black player.
            $opponentColor = $invitation->proposed_color === 'random'
                ? (rand(0, 1) === 0 ? 'black' : 'white')
                : $invitation->proposed_color;

            [$blackId, $whiteId] = $opponentColor === 'black'
                ? [$invitation->to_user_id, $invitation->from_user_id]
                : [$invitation->from_user_id, $invitation->to_user_id];

            $expiresAt = $invitation->time_control_type === 'correspondence'
                ? now()->addDays($invitation->time_control_config['days_per_move'] ?? 3)
                : null;

            $rules = new ChineseRuleset;
            $handicap = (int) ($invitation->handicap ?? 0);
            $handicapStones = Handicap::fixedPositions($invitation->board_size, $handicap);
            $handicapStonesJson = array_map(
                fn ($p) => ['x' => $p->x, 'y' => $p->y],
                $handicapStones
            );

            $game = Game::create([
                'black_player_id' => $blackId,
                'white_player_id' => $whiteId,
                'mode' => $invitation->mode,
                'ruleset' => $invitation->ruleset,
                'board_size' => $invitation->board_size,
                'status' => 'playing',
                'current_turn' => $handicap >= 2 ? 'white' : 'black',
                'time_control_type' => $invitation->time_control_type,
                'time_control_config' => $invitation->time_control_config,
                'handicap' => $handicap,
                'handicap_stones' => $handicapStonesJson,
                'handicap_placement' => $invitation->handicap_placement ?? 'fixed',
                'komi' => $rules->komiWithHandicap($invitation->board_size, $handicap),
                'started_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            $invitation->update(['status' => 'accepted', 'game_id' => $game->id]);

            return $game;
        });

        event(new InvitationAccepted(
            invitationId: $invitation->id,
            fromUserId: $invitation->from_user_id,
            toUserId: $invitation->to_user_id,
            gameId: $game->id,
        ));

        return response()->json(['game_id' => $game->id]);
    }

    public function decline(Request $request, GameInvitation $invitation): JsonResponse
    {
        if ($invitation->to_user_id !== $request->user()->id) {
            return response()->json(['message' => __('messages.invitation_not_for_you')], 403);
        }

        if ($invitation->status !== 'pending') {
            return response()->json(['message' => __('messages.invitation_not_pending')], 422);
        }

        $invitation->update(['status' => 'declined']);

        event(new InvitationDeclined(
            invitationId: $invitation->id,
            fromUserId: $invitation->from_user_id,
        ));

        return response()->json(['message' => __('messages.invitation_declined')]);
    }
}
