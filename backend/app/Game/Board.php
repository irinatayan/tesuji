<?php

declare(strict_types=1);

namespace App\Game;

final readonly class Board
{
    /** @param array<int, array<int, Stone|null>> $grid */
    private function __construct(
        private int $size,
        private array $grid,
    ) {}

    public static function empty(int $size): self
    {
        if (! in_array($size, [9, 13, 19], strict: true)) {
            throw new \InvalidArgumentException("Board size must be 9, 13 or 19, got {$size}");
        }

        $grid = array_fill(0, $size, array_fill(0, $size, null));

        return new self($size, $grid);
    }

    public function size(): int
    {
        return $this->size;
    }

    public function get(Position $position): ?Stone
    {
        $this->assertInBounds($position);

        return $this->grid[$position->y][$position->x];
    }

    public function place(Position $position, Stone $stone): self
    {
        $this->assertInBounds($position);

        $grid = $this->grid;
        $grid[$position->y][$position->x] = $stone;

        return new self($this->size, $grid);
    }

    public function isEmpty(): bool
    {
        foreach ($this->grid as $row) {
            foreach ($row as $cell) {
                if ($cell !== null) {
                    return false;
                }
            }
        }

        return true;
    }

    public function isInBounds(Position $position): bool
    {
        return $position->x >= 0
            && $position->x < $this->size
            && $position->y >= 0
            && $position->y < $this->size;
    }

    private function assertInBounds(Position $position): void
    {
        if (! $this->isInBounds($position)) {
            throw new \InvalidArgumentException(
                "Position {$position} is out of bounds for {$this->size}x{$this->size} board"
            );
        }
    }
}
