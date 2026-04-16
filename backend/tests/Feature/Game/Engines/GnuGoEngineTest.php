<?php

declare(strict_types=1);

namespace Tests\Feature\Game\Engines;

use App\Game\Board;
use App\Game\Engines\GnuGoEngine;
use App\Game\Engines\ProcessGtpClient;
use App\Game\Move;
use App\Game\MoveType;
use App\Game\Position;
use App\Game\Stone;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
class GnuGoEngineTest extends TestCase
{
    private ?ProcessGtpClient $client = null;

    protected function setUp(): void
    {
        parent::setUp();

        exec('which gnugo 2>/dev/null', $output, $code);
        if ($code !== 0) {
            $this->markTestSkipped('gnugo binary not available in PATH');
        }
    }

    protected function tearDown(): void
    {
        $this->client?->close();
        parent::tearDown();
    }

    public function test_suggests_legal_move_on_empty_board(): void
    {
        $this->client = new ProcessGtpClient;
        $engine = new GnuGoEngine($this->client);

        $move = $engine->suggestMove(Board::empty(9), Stone::Black);

        $this->assertSame(MoveType::Play, $move->type);
        $this->assertNotNull($move->position);
        $this->assertGreaterThanOrEqual(0, $move->position->x);
        $this->assertLessThan(9, $move->position->x);
        $this->assertGreaterThanOrEqual(0, $move->position->y);
        $this->assertLessThan(9, $move->position->y);
    }

    public function test_responds_after_replaying_history(): void
    {
        $this->client = new ProcessGtpClient;
        $engine = new GnuGoEngine($this->client);

        $history = [
            Move::play(Stone::Black, new Position(4, 4)),
        ];
        $boardAfterBlack = Board::empty(9)->placeStone(new Position(4, 4), Stone::Black)->board;

        $botMove = $engine->suggestMove($boardAfterBlack, Stone::White, $history);

        $this->assertSame(MoveType::Play, $botMove->type);
        $this->assertNotNull($botMove->position);
        $this->assertFalse(
            $botMove->position->equals(new Position(4, 4)),
            'bot must not play on the occupied point',
        );
    }

    public function test_reuses_client_across_multiple_suggestions(): void
    {
        $this->client = new ProcessGtpClient;
        $engine = new GnuGoEngine($this->client);

        $first = $engine->suggestMove(Board::empty(9), Stone::Black);
        $second = $engine->suggestMove(Board::empty(13), Stone::White);

        $this->assertSame(MoveType::Play, $first->type);
        $this->assertSame(MoveType::Play, $second->type);
        $this->assertLessThan(13, $second->position->x);
        $this->assertLessThan(13, $second->position->y);
    }
}
