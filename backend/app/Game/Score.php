<?php

declare(strict_types=1);

namespace App\Game;

final readonly class Score
{
    public Stone $winner;

    public float $margin;

    public function __construct(
        public float $black,
        public float $white,
    ) {
        $this->margin = abs($this->black - $this->white);
        $this->winner = $this->black > $this->white ? Stone::Black : Stone::White;
    }

    public function result(): string
    {
        $winner = $this->winner === Stone::Black ? 'B' : 'W';

        return "{$winner}+{$this->margin}";
    }
}
