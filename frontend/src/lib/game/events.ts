import { Board } from './Board';
import type { Position, Stone } from './types';

export interface MovePlayed {
  type: 'game.move.played';
  game_id: number;
  move_number: number;
  x: number;
  y: number;
  color: Stone;
  captures: Position[];
  position_hash: string;
}

export interface MovePassed {
  type: 'game.move.passed';
  game_id: number;
  move_number: number;
  color: Stone;
}

export function applyMovePlayed(board: Board, event: MovePlayed): Board {
  let next = board;
  for (const cap of event.captures) {
    next = next.removeStone(cap);
  }
  return next.placeStone({ x: event.x, y: event.y }, event.color);
}
