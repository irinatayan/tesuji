import type { Cell, Position, Stone } from './types';

export class Board {
  readonly size: number;
  private readonly cells: ReadonlyArray<ReadonlyArray<Cell>>;

  private constructor(size: number, cells: ReadonlyArray<ReadonlyArray<Cell>>) {
    this.size = size;
    this.cells = cells;
  }

  static empty(size: number): Board {
    const cells = Array.from({ length: size }, () =>
      Array.from<Cell>({ length: size }).fill(null),
    );
    return new Board(size, cells);
  }

  get(pos: Position): Cell {
    return this.cells[pos.y][pos.x];
  }

  set(pos: Position, cell: Cell): Board {
    const next = this.cells.map((row, y) =>
      y === pos.y ? row.map((c, x) => (x === pos.x ? cell : c)) : row,
    );
    return new Board(this.size, next);
  }

  neighbors(pos: Position): Position[] {
    const result: Position[] = [];
    const { x, y } = pos;
    if (y > 0) result.push({ x, y: y - 1 });
    if (y < this.size - 1) result.push({ x, y: y + 1 });
    if (x > 0) result.push({ x: x - 1, y });
    if (x < this.size - 1) result.push({ x: x + 1, y });
    return result;
  }

  toArray(): Cell[][] {
    return this.cells.map((row) => [...row]);
  }

  placeStone(pos: Position, stone: Stone): Board {
    return this.set(pos, stone);
  }

  removeStone(pos: Position): Board {
    return this.set(pos, null);
  }
}
