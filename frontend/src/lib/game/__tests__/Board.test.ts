import { describe, it, expect } from 'vitest';
import { Board } from '../Board';

describe('Board', () => {
  it('creates empty board of correct size', () => {
    const board = Board.empty(9);
    expect(board.size).toBe(9);
    for (let y = 0; y < 9; y++) {
      for (let x = 0; x < 9; x++) {
        expect(board.get({ x, y })).toBeNull();
      }
    }
  });

  it('set places a stone and returns new board', () => {
    const board = Board.empty(9);
    const next = board.set({ x: 3, y: 4 }, 'black');
    expect(next.get({ x: 3, y: 4 })).toBe('black');
  });

  it('set does not mutate original board', () => {
    const board = Board.empty(9);
    board.set({ x: 3, y: 4 }, 'black');
    expect(board.get({ x: 3, y: 4 })).toBeNull();
  });

  it('set can clear a stone', () => {
    const board = Board.empty(9).set({ x: 0, y: 0 }, 'white');
    const cleared = board.set({ x: 0, y: 0 }, null);
    expect(cleared.get({ x: 0, y: 0 })).toBeNull();
  });

  describe('neighbors', () => {
    it('corner has 2 neighbors', () => {
      const board = Board.empty(9);
      expect(board.neighbors({ x: 0, y: 0 })).toHaveLength(2);
    });

    it('edge has 3 neighbors', () => {
      const board = Board.empty(9);
      expect(board.neighbors({ x: 4, y: 0 })).toHaveLength(3);
    });

    it('center has 4 neighbors', () => {
      const board = Board.empty(9);
      expect(board.neighbors({ x: 4, y: 4 })).toHaveLength(4);
    });

    it('returns correct neighbor positions', () => {
      const board = Board.empty(9);
      const neighbors = board.neighbors({ x: 2, y: 2 });
      expect(neighbors).toContainEqual({ x: 2, y: 1 });
      expect(neighbors).toContainEqual({ x: 2, y: 3 });
      expect(neighbors).toContainEqual({ x: 1, y: 2 });
      expect(neighbors).toContainEqual({ x: 3, y: 2 });
    });
  });
});
