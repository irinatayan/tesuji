export type Stone = 'black' | 'white';
export type Cell = Stone | null;

export interface Position {
  x: number;
  y: number;
}
