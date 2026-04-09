import type { Board } from './Board';
import type { Position, Stone } from './types';

export function isLegal(board: Board, pos: Position, stone: Stone): boolean {
  if (board.get(pos) !== null) return false;

  const hasEmptyNeighbor = board.neighbors(pos).some((n) => board.get(n) === null);
  if (!hasEmptyNeighbor) return false;

  return true;
}

export function legalMoves(board: Board, stone: Stone): Position[] {
  const moves: Position[] = [];
  for (let y = 0; y < board.size; y++) {
    for (let x = 0; x < board.size; x++) {
      const pos = { x, y };
      if (isLegal(board, pos, stone)) {
        moves.push(pos);
      }
    }
  }
  return moves;
}
