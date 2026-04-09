<?php

declare(strict_types=1);

namespace Tests\Unit\Game;

use App\Game\Game;
use App\Game\Move;
use App\Game\Position;
use App\Game\Rules\ChineseRuleset;
use App\Game\Stone;
use PHPUnit\Framework\TestCase;

class ScenarioFixtureTest extends TestCase
{
    /** @return array<string, mixed> */
    private static function loadFixtures(): array
    {
        $path = __DIR__.'/../../fixtures/game-scenarios.json';

        return json_decode(file_get_contents($path), true);
    }

    public function test_all_scenarios(): void
    {
        $fixtures = self::loadFixtures();

        foreach ($fixtures['scenarios'] as $scenario) {
            $this->runScenario($scenario);
        }
    }

    /** @param array<string, mixed> $scenario */
    private function runScenario(array $scenario): void
    {
        $name = $scenario['name'];
        $ruleset = new ChineseRuleset;
        $game = Game::start($scenario['size'], $ruleset);

        foreach ($scenario['steps'] as $step) {
            $move = $step['move'];
            $domainMove = $this->buildMove($move);

            $game = $game->apply($domainMove);

            $expected = $step['board'];
            $size = $scenario['size'];

            for ($y = 0; $y < $size; $y++) {
                for ($x = 0; $x < $size; $x++) {
                    $cell = $game->board->get(new Position($x, $y));
                    $actual = $cell ? strtolower($cell->name) : null;
                    $this->assertSame(
                        $expected[$y][$x],
                        $actual,
                        "Scenario '{$name}': board mismatch at ({$x},{$y})"
                    );
                }
            }

            $this->assertSame(
                $step['event']['position_hash'],
                $game->board->hash(),
                "Scenario '{$name}': hash mismatch"
            );
        }
    }

    /** @param array<string, mixed> $move */
    private function buildMove(array $move): Move
    {
        $stone = $move['color'] === 'black' ? Stone::Black : Stone::White;

        return match ($move['type']) {
            'play' => Move::play($stone, new Position($move['x'], $move['y'])),
            'pass' => Move::pass($stone),
            default => throw new \InvalidArgumentException("Unknown move type: {$move['type']}"),
        };
    }
}
