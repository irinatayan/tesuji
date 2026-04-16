<?php

declare(strict_types=1);

namespace App\Game\Engines;

use App\Game\MoveType;
use App\Game\Position;

final readonly class EngineMove
{
    private function __construct(
        public MoveType $type,
        public ?Position $position = null,
    ) {}

    public static function play(Position $position): self
    {
        return new self(MoveType::Play, $position);
    }

    public static function pass(): self
    {
        return new self(MoveType::Pass);
    }

    public static function resign(): self
    {
        return new self(MoveType::Resign);
    }
}
