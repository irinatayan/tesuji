<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\Game\GameFinished;
use App\Models\Game;
use App\Notifications\GameTimedOutNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class CheckGameTimeouts implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        Game::where('status', 'playing')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->each(function (Game $game): void {
                $loser = $game->current_turn;
                $winner = $loser === 'black' ? 'W' : 'B';
                $result = $winner.'+T';

                $game->update([
                    'status' => 'finished',
                    'result' => $result,
                    'finished_at' => now(),
                    'expires_at' => null,
                ]);

                event(new GameFinished(
                    gameId: $game->id,
                    result: $result,
                    score: null,
                ));

                $game->load(['blackPlayer', 'whitePlayer']);
                $game->blackPlayer->notify(new GameTimedOutNotification($game));
                $game->whitePlayer->notify(new GameTimedOutNotification($game));
            });
    }
}
