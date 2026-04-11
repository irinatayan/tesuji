<?php

declare(strict_types=1);

namespace App\Game\Exceptions;

final class IllegalMoveException extends \RuntimeException
{
    /** @var array<string, string> */
    public readonly array $params;

    public function __construct(string $key, array $params = [])
    {
        parent::__construct($key);
        $this->params = $params;
    }

    public static function occupiedCell(): self
    {
        return new self('occupied');
    }

    public static function suicide(): self
    {
        return new self('suicide');
    }

    public static function ko(): self
    {
        return new self('ko');
    }

    public static function wrongTurn(string $expected, string $got): self
    {
        return new self('wrong_turn', ['expected' => $expected, 'got' => $got]);
    }

    public static function gameNotInProgress(): self
    {
        return new self('not_in_progress');
    }
}
