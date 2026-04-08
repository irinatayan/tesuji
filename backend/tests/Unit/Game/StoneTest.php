<?php

declare(strict_types=1);

namespace Tests\Unit\Game;

use App\Game\Stone;
use PHPUnit\Framework\TestCase;

class StoneTest extends TestCase
{
    public function test_opposite_of_black_is_white(): void
    {
        $this->assertSame(Stone::White, Stone::Black->opposite());
    }

    public function test_opposite_of_white_is_black(): void
    {
        $this->assertSame(Stone::Black, Stone::White->opposite());
    }
}
