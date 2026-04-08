<?php

declare(strict_types=1);

namespace App\Game;

final readonly class Position
{
    public function __construct(
        public readonly int $x,
        public readonly int $y,
    ) {
        if ($x < 0 || $y < 0) {
            throw new \InvalidArgumentException("Position coordinates must be non-negative, got ({$x}, {$y})");
        }
    }

    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y;
    }

    public function __toString(): string
    {
        return "({$this->x}, {$this->y})";
    }
}
