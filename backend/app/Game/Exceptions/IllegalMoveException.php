<?php

declare(strict_types=1);

namespace App\Game\Exceptions;

final class IllegalMoveException extends \RuntimeException
{
    public static function occupiedCell(): self
    {
        return new self('Cell is already occupied');
    }

    public static function suicide(): self
    {
        return new self('Suicide moves are not allowed');
    }

    public static function ko(): self
    {
        return new self('Move violates the ko rule');
    }

    public static function wrongTurn(string $expected, string $got): self
    {
        return new self("It is {$expected}'s turn, got {$got}");
    }

    public static function gameNotInProgress(): self
    {
        return new self('Game is not in progress');
    }
}
