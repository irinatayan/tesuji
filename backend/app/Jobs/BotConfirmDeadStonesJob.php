<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\Game\GameFinished;
use App\Game\Persistence\GameMapper;
use App\Game\Stone;
use App\Models\Game;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class BotConfirmDeadStonesJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 10;

    public function __construct(public readonly int $gameId) {}

    public function handle(GameMapper $mapper): void
    {
        $game = Game::find($this->gameId);

        if ($game === null || $game->status !== 'scoring') {
            return;
        }

        $botUser = $this->findBotPlayer($game);

        if ($botUser === null) {
            return;
        }

        $stone = $game->black_player_id === $botUser->id ? Stone::Black : Stone::White;

        try {
            $domainGame = $mapper->restore($game);
            $domainGame = $domainGame->confirmDead($stone);
        } catch (\RuntimeException) {
            return;
        }

        $result = $domainGame->score?->result();

        $game->update([
            'status' => 'finished',
            'result' => $result,
            'finished_at' => now(),
        ]);

        event(new GameFinished(
            gameId: $game->id,
            result: $result ?? '',
            score: $domainGame->score !== null ? [
                'black' => $domainGame->score->black,
                'white' => $domainGame->score->white,
            ] : null,
        ));

        Log::info("BotConfirmDeadStonesJob: bot confirmed dead stones in game {$this->gameId}");
    }

    private function findBotPlayer(Game $game): ?User
    {
        $black = User::find($game->black_player_id);
        if ($black?->is_bot) {
            return $black;
        }

        $white = User::find($game->white_player_id);
        if ($white?->is_bot) {
            return $white;
        }

        return null;
    }
}
