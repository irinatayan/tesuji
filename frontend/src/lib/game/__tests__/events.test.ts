import { describe, it, expect } from 'vitest';
import { Board } from '../Board';
import { applyMovePlayed, type MovePlayed } from '../events';

function makeEvent(overrides: Partial<MovePlayed> = {}): MovePlayed {
  return {
    type: 'game.move.played',
    game_id: 1,
    move_number: 1,
    x: 0,
    y: 0,
    color: 'black',
    captures: [],
    position_hash: 'abc',
    ...overrides,
  };
}

describe('applyMovePlayed', () => {
  it('places stone on board', () => {
    const board = Board.empty(9);
    const next = applyMovePlayed(board, makeEvent({ x: 3, y: 4, color: 'black' }));
    expect(next.get({ x: 3, y: 4 })).toBe('black');
  });

  it('removes captured stones', () => {
    const board = Board.empty(9).set({ x: 1, y: 0 }, 'white').set({ x: 0, y: 1 }, 'white');

    const event = makeEvent({
      x: 0,
      y: 0,
      color: 'black',
      captures: [
        { x: 1, y: 0 },
        { x: 0, y: 1 },
      ],
    });

    const next = applyMovePlayed(board, event);
    expect(next.get({ x: 0, y: 0 })).toBe('black');
    expect(next.get({ x: 1, y: 0 })).toBeNull();
    expect(next.get({ x: 0, y: 1 })).toBeNull();
  });

  it('does not mutate original board', () => {
    const board = Board.empty(9);
    applyMovePlayed(board, makeEvent({ x: 2, y: 2 }));
    expect(board.get({ x: 2, y: 2 })).toBeNull();
  });

  it('places white stone correctly', () => {
    const board = Board.empty(9);
    const next = applyMovePlayed(board, makeEvent({ x: 5, y: 5, color: 'white' }));
    expect(next.get({ x: 5, y: 5 })).toBe('white');
  });
});
