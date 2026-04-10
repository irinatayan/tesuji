import type { Board } from './Board';
import type { Position, Stone } from './types';

function liberties(board: Board, pos: Position): Position[] {
  const group = board.group(pos);
  const seen = new Set<string>();
  const result: Position[] = [];
  for (const p of group) {
    for (const n of board.neighbors(p)) {
      const key = `${n.x},${n.y}`;
      if (!seen.has(key) && board.get(n) === null) {
        seen.add(key);
        result.push(n);
      }
    }
  }
  return result;
}

export function isLegal(board: Board, pos: Position, stone: Stone): boolean {
  if (board.get(pos) !== null) return false;

  const hasEmptyNeighbor = board.neighbors(pos).some((n) => board.get(n) === null);
  if (hasEmptyNeighbor) return true;

  const opponent: Stone = stone === 'black' ? 'white' : 'black';

  const connectsToFriendly = board.neighbors(pos).some((n) => {
    if (board.get(n) !== stone) return false;
    return liberties(board, n).some((l) => !(l.x === pos.x && l.y === pos.y));
  });
  if (connectsToFriendly) return true;

  const capturesOpponent = board.neighbors(pos).some((n) => {
    if (board.get(n) !== opponent) return false;
    const libs = liberties(board, n);
    return libs.length === 1 && libs[0].x === pos.x && libs[0].y === pos.y;
  });

  return capturesOpponent;
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
