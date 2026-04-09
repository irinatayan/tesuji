<?php

declare(strict_types=1);

namespace App\Services;

use App\Game\Exceptions\IllegalMoveException;
use App\Game\Move as DomainMove;
use App\Game\Persistence\GameMapper;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

final class GameService
{
    public function __construct(private readonly GameMapper $mapper) {}

    /**
     * Apply a domain move to a game inside a transaction with an advisory lock.
     *
     * @throws IllegalMoveException
     */
    public function applyMove(Game $model, DomainMove $move): Game
    {
        DB::transaction(function () use ($model, $move): void {
            DB::statement('SELECT pg_advisory_xact_lock(?)', [$model->id]);

            $game = $this->mapper->restore($model);
            $game = $game->apply($move);

            $moveNumber = $model->moves()->count() + 1;
            $this->mapper->persistMove($game, $model, $move, $moveNumber);
        });

        return $model->refresh();
    }
}
