import { describe, it, expect } from 'vitest';
import { readFileSync } from 'fs';
import { resolve } from 'path';
import { Board } from '../Board';
import { applyMovePlayed, type MovePlayed } from '../events';
import type { Cell, Position } from '../types';

interface ScenarioStep {
  move: { type: string; color: string; x?: number; y?: number };
  event: {
    x: number;
    y: number;
    color: 'black' | 'white';
    captures: Position[];
    position_hash: string;
  };
  board: Cell[][];
}

interface Scenario {
  name: string;
  size: number;
  steps: ScenarioStep[];
}

interface Fixtures {
  scenarios: Scenario[];
}

const fixturesPath = resolve(
  __dirname,
  '../../../../../backend/tests/fixtures/game-scenarios.json',
);
const fixtures: Fixtures = JSON.parse(readFileSync(fixturesPath, 'utf-8'));

describe('shared game scenarios', () => {
  for (const scenario of fixtures.scenarios) {
    describe(scenario.name, () => {
      it('board state matches after each step', () => {
        let board = Board.empty(scenario.size);

        for (const step of scenario.steps) {
          if (step.move.type !== 'play') continue;

          const event: MovePlayed = {
            type: 'game.move.played',
            game_id: 1,
            move_number: 1,
            x: step.event.x,
            y: step.event.y,
            color: step.event.color,
            captures: step.event.captures,
            position_hash: step.event.position_hash,
          };

          board = applyMovePlayed(board, event);

          for (let y = 0; y < scenario.size; y++) {
            for (let x = 0; x < scenario.size; x++) {
              expect(board.get({ x, y })).toBe(
                step.board[y][x],
                `scenario '${scenario.name}': board mismatch at (${x},${y})`,
              );
            }
          }
        }
      });
    });
  }
});
