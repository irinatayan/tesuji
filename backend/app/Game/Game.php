<?php

declare(strict_types=1);

namespace App\Game;

use App\Game\Exceptions\IllegalMoveException;
use App\Game\Rules\Ruleset;

final readonly class Game
{
    /** @param Move[] $history */
    private function __construct(
        public Board $board,
        public Stone $currentTurn,
        public GamePhase $phase,
        public Ruleset $ruleset,
        public array $history,
        public int $consecutivePasses,
        public ?string $koHash,
    ) {}

    public static function start(int $boardSize, Ruleset $ruleset): self
    {
        return new self(
            board: Board::empty($boardSize),
            currentTurn: Stone::Black,
            phase: GamePhase::Playing,
            ruleset: $ruleset,
            history: [],
            consecutivePasses: 0,
            koHash: null,
        );
    }

    public function apply(Move $move): self
    {
        if ($this->phase !== GamePhase::Playing) {
            throw IllegalMoveException::gameNotInProgress();
        }

        if ($move->color !== $this->currentTurn) {
            throw IllegalMoveException::wrongTurn(
                $this->currentTurn->name,
                $move->color->name
            );
        }

        return match ($move->type) {
            MoveType::Play => $this->applyPlay($move),
            MoveType::Pass => $this->applyPass($move),
            MoveType::Resign => $this->applyResign($move),
        };
    }

    private function applyPlay(Move $move): self
    {
        if (! $this->board->isLegalMove($move->position, $move->color, $this->ruleset, $this->koHash)) {
            $cell = $this->board->get($move->position);

            if ($cell !== null) {
                throw IllegalMoveException::occupiedCell();
            }

            $result = $this->board->placeStone($move->position, $move->color);
            if ($result->board->liberties($move->position) === []) {
                throw IllegalMoveException::suicide();
            }

            throw IllegalMoveException::ko();
        }

        $previousHash = $this->board->hash();
        $result = $this->board->placeStone($move->position, $move->color);

        return new self(
            board: $result->board,
            currentTurn: $this->currentTurn->opposite(),
            phase: GamePhase::Playing,
            ruleset: $this->ruleset,
            history: [...$this->history, $move],
            consecutivePasses: 0,
            koHash: $previousHash,
        );
    }

    private function applyPass(Move $move): self
    {
        $newPasses = $this->consecutivePasses + 1;
        $newPhase = $newPasses >= 2 ? GamePhase::MarkingDead : GamePhase::Playing;

        return new self(
            board: $this->board,
            currentTurn: $this->currentTurn->opposite(),
            phase: $newPhase,
            ruleset: $this->ruleset,
            history: [...$this->history, $move],
            consecutivePasses: $newPasses,
            koHash: null,
        );
    }

    private function applyResign(Move $move): self
    {
        return new self(
            board: $this->board,
            currentTurn: $this->currentTurn->opposite(),
            phase: GamePhase::Finished,
            ruleset: $this->ruleset,
            history: [...$this->history, $move],
            consecutivePasses: 0,
            koHash: null,
        );
    }
}
