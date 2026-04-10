<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\Game\GameFinished;
use App\Events\Game\MovePassed;
use App\Events\Game\MovePlayed;
use App\Events\Game\PlayerResigned;
use App\Game\Exceptions\IllegalMoveException;
use App\Game\Game as DomainGame;
use App\Game\Move as DomainMove;
use App\Game\MoveType;
use App\Game\Persistence\GameMapper;
use App\Mail\GameFinishedMail;
use App\Mail\YourTurnMail;
use App\Models\Game;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

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
        $domainGame = null;
        $moveNumber = null;

        DB::transaction(function () use ($model, $move, &$domainGame, &$moveNumber): void {
            DB::statement('SELECT pg_advisory_xact_lock(?)', [$model->id]);

            $game = $this->mapper->restore($model);
            $domainGame = $game->apply($move);

            $moveNumber = $model->moves()->count() + 1;
            $this->mapper->persistMove($domainGame, $model, $move, $moveNumber);
        });

        $model->refresh();

        $this->dispatchMoveEvent($model, $domainGame, $move, $moveNumber);

        if ($model->time_control_type === 'correspondence') {
            $this->sendCorrespondenceMail($model);
        }

        return $model;
    }

    private function dispatchMoveEvent(Game $model, DomainGame $domainGame, DomainMove $move, int $moveNumber): void
    {
        $color = strtolower($move->color->name);
        $lastMove = $model->moves()->latest('move_number')->first();

        match ($move->type) {
            MoveType::Play => event(new MovePlayed(
                gameId: $model->id,
                moveNumber: $moveNumber,
                x: $move->position->x,
                y: $move->position->y,
                color: $color,
                captures: $lastMove->captures ?? [],
                positionHash: $lastMove->position_hash,
            )),
            MoveType::Pass => event(new MovePassed(
                gameId: $model->id,
                moveNumber: $moveNumber,
                color: $color,
            )),
            MoveType::Resign => $this->dispatchResignEvents($model, $color),
        };
    }

    private function dispatchResignEvents(Game $model, string $color): void
    {
        event(new PlayerResigned(gameId: $model->id, color: $color));
        event(new GameFinished(
            gameId: $model->id,
            result: $model->result,
            score: null,
        ));
    }

    private function sendCorrespondenceMail(Game $model): void
    {
        $model->loadMissing(['blackPlayer', 'whitePlayer']);

        if ($model->status === 'playing') {
            $recipient = $model->current_turn === 'black'
                ? $model->blackPlayer
                : $model->whitePlayer;
            Mail::to($recipient)->queue(new YourTurnMail($model, $recipient));
        } elseif ($model->status === 'finished') {
            Mail::to($model->blackPlayer)->queue(new GameFinishedMail($model, $model->blackPlayer));
            Mail::to($model->whitePlayer)->queue(new GameFinishedMail($model, $model->whitePlayer));
        }
    }
}
