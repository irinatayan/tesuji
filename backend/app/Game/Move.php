<?php

declare(strict_types=1);

namespace App\Game;

final readonly class Move
{
    private function __construct(
        public MoveType $type,
        public Stone $color,
        public ?Position $position,
    ) {}

    public static function play(Stone $color, Position $position): self
    {
        return new self(MoveType::Play, $color, $position);
    }

    public static function pass(Stone $color): self
    {
        return new self(MoveType::Pass, $color, null);
    }

    public static function resign(Stone $color): self
    {
        return new self(MoveType::Resign, $color, null);
    }
}
