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

    /** @return Position[] */
    public function neighbours(Position $position): array
    {
        $result = [];

        foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$dx, $dy]) {
            $x = $position->x + $dx;
            $y = $position->y + $dy;

            if ($x >= 0 && $y >= 0 && $x < $this->size && $y < $this->size) {
                $result[] = new Position($x, $y);
            }
        }

        return $result;
    }

    public function isInBounds(Position $position): bool
    {
        return $position->x >= 0
            && $position->x < $this->size
            && $position->y >= 0
            && $position->y < $this->size;
    }

    public function hash(): string
    {
        $state = '';

        foreach ($this->grid as $row) {
            foreach ($row as $cell) {
                $state .= match ($cell) {
                    Stone::Black => 'B',
                    Stone::White => 'W',
                    null => '.',
                };
            }
        }

        return hash('sha256', $state);
    }

    public function placeStone(Position $position, Stone $stone): CaptureResult
    {
        $board = $this->place($position, $stone);

        return $board->removeCaptured($stone->opposite());
    }

    public function removeCaptured(Stone $color): CaptureResult
    {
        $board = $this;
        $allCaptured = [];
        $visited = [];

        foreach ($this->grid as $y => $row) {
            foreach ($row as $x => $stone) {
                $key = "{$x},{$y}";

                if ($stone !== $color || isset($visited[$key])) {
                    continue;
                }

                $position = new Position($x, $y);
                $group = $this->group($position);

                foreach ($group as $pos) {
                    $visited["{$pos->x},{$pos->y}"] = true;
                }

                if ($this->liberties($position) === []) {
                    foreach ($group as $pos) {
                        $board = $board->remove($pos);
                        $allCaptured[] = $pos;
                    }
                }
            }
        }

        return new CaptureResult($board, $allCaptured);
    }

    /** @return Position[] */
    public function group(Position $position): array
    {
        $stone = $this->get($position);

        if ($stone === null) {
            return [];
        }

        $visited = [];
        $queue = new \SplQueue();
        $queue->enqueue($position);
        $visited["{$position->x},{$position->y}"] = $position;

        while (! $queue->isEmpty()) {
            $current = $queue->dequeue();

            foreach ($this->neighbours($current) as $neighbour) {
                $key = "{$neighbour->x},{$neighbour->y}";

                if (! isset($visited[$key]) && $this->get($neighbour) === $stone) {
                    $visited[$key] = $neighbour;
                    $queue->enqueue($neighbour);
                }
            }
        }

        return array_values($visited);
    }

    /** @return Position[] */
    public function liberties(Position $position): array
    {
        $group = $this->group($position);

        if ($group === []) {
            return [];
        }

        $liberties = [];

        foreach ($group as $pos) {
            foreach ($this->neighbours($pos) as $neighbour) {
                $key = "{$neighbour->x},{$neighbour->y}";

                if (! isset($liberties[$key]) && $this->get($neighbour) === null) {
                    $liberties[$key] = $neighbour;
                }
            }
        }

        return array_values($liberties);
    }

    private function remove(Position $position): self
    {
        $grid = $this->grid;
        $grid[$position->y][$position->x] = null;

        return new self($this->size, $grid);
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
