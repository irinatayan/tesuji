<?php

declare(strict_types=1);

namespace Tests\Unit\Game;

use App\Game\Position;
use PHPUnit\Framework\TestCase;

class PositionTest extends TestCase
{
    public function test_creates_with_valid_coordinates(): void
    {
        $pos = new Position(3, 4);
        $this->assertSame(3, $pos->x);
        $this->assertSame(4, $pos->y);
    }

    public function test_throws_on_negative_x(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Position(-1, 0);
    }

    public function test_throws_on_negative_y(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Position(0, -1);
    }

    public function test_equals_same_coordinates(): void
    {
        $this->assertTrue((new Position(2, 3))->equals(new Position(2, 3)));
    }

    public function test_not_equals_different_coordinates(): void
    {
        $this->assertFalse((new Position(2, 3))->equals(new Position(3, 2)));
    }
}
