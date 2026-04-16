<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Game\MovePassed;
use App\Events\Game\MovePlayed;
use App\Jobs\BotMoveJob;
use App\Models\Game;
use App\Models\User;

final class TriggerBotMove
{
    public function handle(MovePlayed|MovePassed $event): void
    {
        $game = Game::find($event->gameId);

        if ($game === null || $game->status !== 'playing') {
            return;
        }

        $currentPlayerId = $game->current_turn === 'black'
            ? $game->black_player_id
            : $game->white_player_id;

        $currentPlayer = User::find($currentPlayerId);

        if ($currentPlayer !== null && $currentPlayer->is_bot) {
            BotMoveJob::dispatch($game->id);
        }
    }
}
