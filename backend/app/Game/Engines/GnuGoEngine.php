<?php

declare(strict_types=1);

namespace App\Game\Engines;

use App\Game\Board;
use App\Game\Move;
use App\Game\MoveType;
use App\Game\Position;
use App\Game\Stone;

/**
 * GoEngine implementation backed by any GTP-speaking engine (GNU Go by default).
 *
 * The conversation per call to {@see suggestMove()}:
 *   1. boardsize N
 *   2. clear_board
 *   3. set_free_handicap VERTEX...  (if handicap stones present)
 *   4. play COLOR VERTEX  (for each historical move)
 *   5. genmove COLOR      (the move we actually want)
 *
 * Resign moves in the history are skipped (the game would already be over).
 */
final class GnuGoEngine implements GoEngine
{
    public function __construct(private readonly GtpClient $gtp) {}

    public function suggestMove(Board $board, Stone $toPlay, array $history = [], array $handicapStones = []): EngineMove
    {
        $size = $board->size();

        $this->gtp->send("boardsize {$size}");
        $this->gtp->send('clear_board');

        if ($handicapStones !== []) {
            $vertices = array_map(
                fn (Position $p) => GtpCoordinates::toVertex($p, $size),
                $handicapStones
            );
            $this->gtp->send('set_free_handicap '.implode(' ', $vertices));
        }

        foreach ($history as $move) {
            $this->replay($move, $size);
        }

        $response = $this->gtp->send('genmove '.$this->colorName($toPlay));

        return $this->parseMove($response, $size);
    }

    private function replay(Move $move, int $size): void
    {
        match ($move->type) {
            MoveType::Play => $this->gtp->send(sprintf(
                'play %s %s',
                $this->colorName($move->color),
                GtpCoordinates::toVertex($move->position, $size),
            )),
            MoveType::Pass => $this->gtp->send('play '.$this->colorName($move->color).' pass'),
            MoveType::Resign => null,
        };
    }

    private function colorName(Stone $stone): string
    {
        return $stone === Stone::Black ? 'black' : 'white';
    }

    private function parseMove(string $response, int $size): EngineMove
    {
        $response = strtoupper(trim($response));

        return match (true) {
            $response === 'PASS' => EngineMove::pass(),
            $response === 'RESIGN' => EngineMove::resign(),
            default => EngineMove::play(GtpCoordinates::fromVertex($response, $size)),
        };
    }
}
