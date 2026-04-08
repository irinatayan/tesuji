<?php

declare(strict_types=1);

namespace App\Game;

final readonly class CaptureResult
{
    /**
     * @param Position[] $captured
     */
    public function __construct(
        public Board $board,
        public array $captured,
    ) {}
}
