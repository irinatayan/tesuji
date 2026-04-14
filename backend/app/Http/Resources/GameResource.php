<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Game\Board;
use App\Game\Persistence\BoardSerializer;
use App\Game\Position;
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
            'black_player' => [
                'id' => $this->blackPlayer->id,
                'name' => $this->blackPlayer->name,
            ],
            'white_player' => [
                'id' => $this->whitePlayer->id,
                'name' => $this->whitePlayer->name,
            ],
            'board' => $this->buildBoard(),
            'captures' => $this->buildCaptures(),
            'result' => $this->result,
            'score' => null,
            'started_at' => $this->started_at?->toISOString(),
            'finished_at' => $this->finished_at?->toISOString(),
            'unread_count' => $this->when(
                $this->resource->unread_count !== null,
                fn () => (int) $this->resource->unread_count,
            ),
        ];
    }

    private function buildBoard(): array
    {
        $lastMove = $this->moves->last();

        $board = $lastMove !== null
            ? BoardSerializer::deserialize($lastMove->board_state, $this->board_size)
            : Board::empty($this->board_size);

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
