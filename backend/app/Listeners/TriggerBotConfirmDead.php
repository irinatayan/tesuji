<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\Game\DeadStonesMarked;
use App\Jobs\BotConfirmDeadStonesJob;
use App\Models\Game;
use App\Models\User;

final class TriggerBotConfirmDead
{
    public function handle(DeadStonesMarked $event): void
    {
        $game = Game::find($event->gameId);

        if ($game === null || $game->status !== 'scoring') {
            return;
        }

        // The "by" field is the color of whoever marked dead stones.
        // The opponent should confirm. Check if the opponent is a bot.
        $opponentId = $event->by === 'black'
            ? $game->white_player_id
            : $game->black_player_id;

        $opponent = User::find($opponentId);

        if ($opponent !== null && $opponent->is_bot) {
            BotConfirmDeadStonesJob::dispatch($game->id);
        }
    }
}
