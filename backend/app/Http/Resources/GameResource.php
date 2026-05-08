<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Game\Board;
use App\Game\Persistence\BoardSerializer;
use App\Game\Position;
use App\Game\Stone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GameResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'board_size' => $this->board_size,
            'mode' => $this->mode,
            'ruleset' => $this->ruleset,
            'status' => $this->status,
            'current_turn' => $this->current_turn,
            'handicap' => (int) $this->handicap,
            'handicap_stones' => $this->handicap_stones ?? [],
            'handicap_placement' => $this->handicap_placement ?? 'fixed',
            'komi' => (float) $this->komi,
            'black_player' => [
                'id' => $this->blackPlayer->id,
                'name' => $this->blackPlayer->name,
                'is_bot' => $this->blackPlayer->is_bot,
            ],
            'white_player' => [
                'id' => $this->whitePlayer->id,
                'name' => $this->whitePlayer->name,
                'is_bot' => $this->whitePlayer->is_bot,
            ],
            'board' => $this->buildBoard(),
            'last_move' => $this->buildLastMove(),
            'captures' => $this->buildCaptures(),
            'result' => $this->result,
            'score' => null,
            'time_control_type' => $this->time_control_type,
            'time_control_config' => $this->time_control_config,
            'black_clock' => $this->black_clock,
            'white_clock' => $this->white_clock,
            'expires_at' => $this->expires_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'turn_started_at' => $this->resolveTurnStartedAt(),
            'server_time' => (int) now()->getPreciseTimestamp(3),
            'dead_stones' => $this->dead_stones,
            'moves' => $this->when(
                $this->status === 'finished',
                fn () => $this->moves->map(fn ($m) => [
                    'move_number' => $m->move_number,
                    'color' => $m->color,
                    'type' => $m->type,
                    'x' => $m->x,
                    'y' => $m->y,
                    'captures' => $m->captures ?? [],
                ]),
            ),
            'unread_count' => $this->when(
                $this->resource->unread_count !== null,
                fn () => (int) $this->resource->unread_count,
            ),
        ];
    }

    private function resolveTurnStartedAt(): ?int
    {
        if ($this->status !== 'playing') {
            return null;
        }

        $ts = ($this->last_move_at ?? $this->started_at)?->getPreciseTimestamp(3);

        return $ts !== null ? (int) $ts : null;
    }

    private function buildBoard(): array
    {
        $lastMove = $this->moves->last();

        if ($lastMove !== null) {
            $board = BoardSerializer::deserialize($lastMove->board_state, $this->board_size);
        } else {
            $board = Board::empty($this->board_size);
            foreach ($this->handicap_stones ?? [] as $pos) {
                $board = $board->place(new Position((int) $pos['x'], (int) $pos['y']), Stone::Black);
            }
        }

        $size = $this->board_size;
        $grid = [];

        for ($y = 0; $y < $size; $y++) {
            $row = [];
            for ($x = 0; $x < $size; $x++) {
                $stone = $board->get(new Position($x, $y));
                $row[] = $stone !== null ? strtolower($stone->name) : null;
            }
            $grid[] = $row;
        }

        return $grid;
    }

    private function buildLastMove(): ?array
    {
        $last = $this->moves->last();

        if ($last === null || $last->type !== 'play') {
            return null;
        }

        return ['x' => $last->x, 'y' => $last->y];
    }

    private function buildCaptures(): array
    {
        $black = 0;
        $white = 0;
        foreach ($this->moves as $move) {
            $count = is_array($move->captures) ? count($move->captures) : 0;
            if ($move->color === 'black') {
                $black += $count;
            } elseif ($move->color === 'white') {
                $white += $count;
            }
        }

        return ['black' => $black, 'white' => $white];
    }
}
