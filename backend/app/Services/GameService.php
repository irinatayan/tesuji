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
use App\Game\Position;
use App\Models\Game;
use App\Notifications\GameFinishedNotification;
use App\Notifications\GameTimedOutNotification;
use App\Notifications\OpponentMovedNotification;
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
        $domainGame = null;
        $moveNumber = null;
        $timedOut = false;

        DB::transaction(function () use ($model, $move, &$domainGame, &$moveNumber, &$timedOut): void {
            DB::statement('SELECT pg_advisory_xact_lock(?)', [$model->id]);

            $model->refresh();

            if ($model->time_control_type === 'absolute' && $model->expires_at?->isPast()) {
                $timedOut = true;
                $loser = $model->current_turn;
                $winner = $loser === 'black' ? 'W' : 'B';
                $model->update([
                    'status' => 'finished',
                    'result' => $winner.'+T',
                    'finished_at' => now(),
                    'expires_at' => null,
                ]);

                return;
            }

            $game = $this->mapper->restore($model);
            $domainGame = $game->apply($move);

            $moveNumber = $model->moves()->count() + 1;
            $this->mapper->persistMove($domainGame, $model, $move, $moveNumber);
        });

        $model->refresh();

        if ($timedOut) {
            event(new GameFinished(gameId: $model->id, result: $model->result, score: null));
            $model->loadMissing(['blackPlayer', 'whitePlayer']);
            $model->blackPlayer->notify(new GameTimedOutNotification($model));
            $model->whitePlayer->notify(new GameTimedOutNotification($model));

            return $model;
        }

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

        $captures = array_map(
            fn (Position $p) => ['x' => $p->x, 'y' => $p->y],
            $domainGame->lastCaptures
        );

        $blackClock = $model->black_clock ?: null;
        $whiteClock = $model->white_clock ?: null;
        $expiresAt = $model->expires_at?->toISOString();

        match ($move->type) {
            MoveType::Play => event(new MovePlayed(
                gameId: $model->id,
                moveNumber: $moveNumber,
                x: $move->position->x,
                y: $move->position->y,
                color: $color,
                captures: $captures,
                positionHash: $lastMove->position_hash,
                blackClock: $blackClock,
                whiteClock: $whiteClock,
                expiresAt: $expiresAt,
            )),
            MoveType::Pass => event(new MovePassed(
                gameId: $model->id,
                moveNumber: $moveNumber,
                color: $color,
                status: $model->status,
                blackClock: $blackClock,
                whiteClock: $whiteClock,
                expiresAt: $expiresAt,
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
            $recipient->notify(new OpponentMovedNotification($model));
        } elseif ($model->status === 'finished') {
            $model->blackPlayer->notify(new GameFinishedNotification($model));
            $model->whitePlayer->notify(new GameFinishedNotification($model));
        }
    }
}
