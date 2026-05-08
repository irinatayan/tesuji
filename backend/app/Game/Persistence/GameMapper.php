<?php

declare(strict_types=1);

namespace App\Game\Persistence;

use App\Game\Board;
use App\Game\Game as DomainGame;
use App\Game\GamePhase;
use App\Game\Move as DomainMove;
use App\Game\MoveType;
use App\Game\Position;
use App\Game\Rules\ChineseRuleset;
use App\Game\Rules\Ruleset;
use App\Game\Stone;
use App\Models\Game as GameModel;
use App\Models\Move as MoveModel;
use Carbon\Carbon;

final class GameMapper
{
    public function restore(GameModel $model): DomainGame
    {
        $ruleset = $this->resolveRuleset($model->ruleset);
        $recentMoves = $model->moves()->reorder()->orderByDesc('move_number')->take(2)->get();
        $lastMove = $recentMoves->first();
        $prevMove = $recentMoves->count() > 1 ? $recentMoves->last() : null;

        if ($lastMove !== null) {
            $board = BoardSerializer::deserialize($lastMove->board_state, $model->board_size);
        } else {
            $board = Board::empty($model->board_size);
            foreach ($this->handicapStones($model) as $position) {
                $board = $board->place($position, Stone::Black);
            }
        }

        $history = $model->moves->map(fn (MoveModel $m) => $this->toDomainMove($m))->all();

        $phase = match ($model->status) {
            'playing' => GamePhase::Playing,
            'scoring' => GamePhase::MarkingDead,
            default => GamePhase::Finished,
        };

        $currentTurn = $model->current_turn !== null
            ? ($model->current_turn === 'black' ? Stone::Black : Stone::White)
            : Stone::Black;

        $proposedDeadStones = null;
        if ($model->dead_stones !== null) {
            $proposedDeadStones = array_map(
                fn (array $p) => new Position($p['x'], $p['y']),
                $model->dead_stones
            );
        }

        $koHash = ($lastMove !== null && $lastMove->type === 'play')
            ? ($prevMove?->position_hash ?? Board::empty($model->board_size)->hash())
            : null;

        return DomainGame::restore(
            board: $board,
            currentTurn: $currentTurn,
            phase: $phase,
            ruleset: $ruleset,
            komi: (float) $model->komi,
            history: $history,
            consecutivePasses: $this->countTrailingPasses($history),
            koHash: $koHash,
            proposedDeadStones: $proposedDeadStones,
            proposedBy: null,
            score: null,
        );
    }

    public function persistMove(DomainGame $game, GameModel $model, DomainMove $move, int $moveNumber): void
    {
        $board = $game->board;

        $turnStartedAt = $model->time_control_type === 'absolute'
            ? ($model->moves()->reorder()->latest('move_number')->value('played_at') ?? $model->started_at)
            : null;

        MoveModel::create([
            'game_id' => $model->id,
            'move_number' => $moveNumber,
            'color' => $move->color === Stone::Black ? 'black' : 'white',
            'type' => match ($move->type) {
                MoveType::Play => 'play',
                MoveType::Pass => 'pass',
                MoveType::Resign => 'resign',
            },
            'x' => $move->position?->x,
            'y' => $move->position?->y,
            'captures' => array_map(
                fn (Position $p) => ['x' => $p->x, 'y' => $p->y],
                $game->lastCaptures
            ),
            'position_hash' => $board->hash(),
            'board_state' => BoardSerializer::serialize($board),
            'played_at' => now(),
        ]);

        $newStatus = match ($game->phase) {
            GamePhase::Playing => 'playing',
            GamePhase::MarkingDead => 'scoring',
            GamePhase::Finished => 'finished',
        };

        $update = [
            'current_turn' => $game->currentTurn === Stone::Black ? 'black' : 'white',
            'status' => $newStatus,
            'last_move_at' => now(),
        ];

        if ($model->time_control_type === 'correspondence') {
            $update['expires_at'] = $newStatus === 'playing'
                ? now()->addDays($model->time_control_config['days_per_move'] ?? 3)
                : null;
        }

        if ($model->time_control_type === 'absolute') {
            $elapsedMs = max(0, now()->getTimestampMs() - Carbon::parse($turnStartedAt)->getTimestampMs());

            $blackRemaining = $model->black_clock['remaining_ms'] ?? 0;
            $whiteRemaining = $model->white_clock['remaining_ms'] ?? 0;

            if ($move->color === Stone::Black) {
                $blackRemaining = max(0, $blackRemaining - $elapsedMs);
            } else {
                $whiteRemaining = max(0, $whiteRemaining - $elapsedMs);
            }

            $update['black_clock'] = ['remaining_ms' => $blackRemaining];
            $update['white_clock'] = ['remaining_ms' => $whiteRemaining];
            $update['expires_at'] = $newStatus === 'playing'
                ? now()->addMilliseconds(
                    $move->color === Stone::Black ? $whiteRemaining : $blackRemaining
                )
                : null;
        }

        if ($game->phase === GamePhase::Finished && $move->type === MoveType::Resign) {
            $winner = $move->color === Stone::Black ? 'W' : 'B';
            $update['result'] = $winner.'+R';
            $update['finished_at'] = now();
        }

        $model->update($update);
    }

    private function toDomainMove(MoveModel $model): DomainMove
    {
        $color = $model->color === 'black' ? Stone::Black : Stone::White;

        return match ($model->type) {
            'play' => DomainMove::play($color, new Position($model->x, $model->y)),
            'pass' => DomainMove::pass($color),
            'resign' => DomainMove::resign($color),
        };
    }

    /** @param DomainMove[] $history */
    private function countTrailingPasses(array $history): int
    {
        $count = 0;
        foreach (array_reverse($history) as $move) {
            if ($move->type !== MoveType::Pass) {
                break;
            }
            $count++;
        }

        return $count;
    }

    private function resolveRuleset(string $name): Ruleset
    {
        return match ($name) {
            'chinese' => new ChineseRuleset,
            default => throw new \InvalidArgumentException("Unknown ruleset: {$name}"),
        };
    }

    /** @return list<Position> */
    private function handicapStones(GameModel $model): array
    {
        $raw = $model->handicap_stones ?? [];
        if ($raw === []) {
            return [];
        }

        return array_map(
            fn (array $p) => new Position((int) $p['x'], (int) $p['y']),
            $raw
        );
    }
}
