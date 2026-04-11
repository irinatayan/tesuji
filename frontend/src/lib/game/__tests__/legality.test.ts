import { describe, it, expect } from 'vitest';
import { Board } from '../Board';
import { isLegal, legalMoves } from '../legality';

describe('isLegal', () => {
  it('empty cell with empty neighbor is legal', () => {
    const board = Board.empty(9);
    expect(isLegal(board, { x: 0, y: 0 }, 'black')).toBe(true);
  });

  it('occupied cell is illegal', () => {
    const board = Board.empty(9).set({ x: 0, y: 0 }, 'white');
    expect(isLegal(board, { x: 0, y: 0 }, 'black')).toBe(false);
  });

  it('cell surrounded by stones is illegal (obvious suicide)', () => {
    // Surround corner (1,1) on a 3x3 board: fill all 4 neighbors
    const board = Board.empty(3)
      .set({ x: 1, y: 0 }, 'white')
      .set({ x: 0, y: 1 }, 'white')
      .set({ x: 2, y: 1 }, 'white')
      .set({ x: 1, y: 2 }, 'white');
    expect(isLegal(board, { x: 1, y: 1 }, 'black')).toBe(false);
  });

  it('corner with both neighbors filled is illegal', () => {
    const board = Board.empty(9).set({ x: 1, y: 0 }, 'white').set({ x: 0, y: 1 }, 'white');
    expect(isLegal(board, { x: 0, y: 0 }, 'black')).toBe(false);
  });

  it('corner with one empty neighbor is legal', () => {
    const board = Board.empty(9).set({ x: 1, y: 0 }, 'white');
    expect(isLegal(board, { x: 0, y: 0 }, 'black')).toBe(true);
  });

  it('move connecting to friendly group with liberties is legal', () => {
    // Black at (1,0); Black plays (0,0) — corner with one neighbor (1,0) being friendly with liberties
    const board = Board.empty(9)
      .set({ x: 1, y: 0 }, 'black')
      .set({ x: 0, y: 1 }, 'white');
    // (0,0) has neighbors: (1,0)=black (has liberty at (2,0)), (0,1)=white
    // No empty neighbor, but connects to friendly group with liberty → legal
    expect(isLegal(board, { x: 0, y: 0 }, 'black')).toBe(true);
  });

  it('capture-before-suicide is legal', () => {
    //   0 1 2
    // 0 . W B   Black plays (0,0): no empty neighbors, no friendly neighbors,
    // 1 W B .   but both W stones have (0,0) as only liberty → captured → legal
    // 2 B . .
    const board = Board.empty(9)
      .set({ x: 1, y: 0 }, 'white')
      .set({ x: 0, y: 1 }, 'white')
      .set({ x: 2, y: 0 }, 'black')
      .set({ x: 1, y: 1 }, 'black')
      .set({ x: 0, y: 2 }, 'black');
    expect(isLegal(board, { x: 0, y: 0 }, 'black')).toBe(true);
  });
});

describe('legalMoves', () => {
  it('empty 3x3 board has 9 legal moves', () => {
    const board = Board.empty(3);
    expect(legalMoves(board, 'black')).toHaveLength(9);
  });

  it('filled board has no legal moves', () => {
    let board = Board.empty(3);
    for (let y = 0; y < 3; y++) {
      for (let x = 0; x < 3; x++) {
        board = board.set({ x, y }, 'black');
      }
    }
    expect(legalMoves(board, 'white')).toHaveLength(0);
  });
});
