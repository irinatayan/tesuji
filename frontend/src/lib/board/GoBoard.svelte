<script lang="ts">
  import type { Board } from '$lib/game/Board';
  import { isLegal } from '$lib/game/legality';
  import type { Position, Stone } from '$lib/game/types';

  const STAR_POINTS: Record<number, [number, number][]> = {
    9: [
      [2, 2],
      [6, 2],
      [4, 4],
      [2, 6],
      [6, 6],
    ],
    13: [
      [3, 3],
      [9, 3],
      [6, 6],
      [3, 9],
      [9, 9],
    ],
    19: [
      [3, 3],
      [9, 3],
      [15, 3],
      [3, 9],
      [9, 9],
      [15, 9],
      [3, 15],
      [9, 15],
      [15, 15],
    ],
  };

  let {
    size = 9,
    board,
    currentTurn = 'black',
    onmove,
  }: {
    size: number;
    board: Board;
    currentTurn?: Stone;
    onmove?: (pos: Position) => void;
  } = $props();

  const SVG_SIZE = 540;
  const MARGIN = 40;
  const cellSize = (SVG_SIZE - 2 * MARGIN) / (size - 1);
  const stoneRadius = cellSize * 0.47;

  function px(coord: number): number {
    return MARGIN + coord * cellSize;
  }

  const lines = Array.from({ length: size }, (_, i) => i);
  const starPoints = STAR_POINTS[size] ?? [];

  let hoveredPos = $state<Position | null>(null);
</script>

<svg
  width={SVG_SIZE}
  height={SVG_SIZE}
  viewBox="0 0 {SVG_SIZE} {SVG_SIZE}"
  style="display:block"
  onmouseleave={() => (hoveredPos = null)}
>
  <rect width={SVG_SIZE} height={SVG_SIZE} fill="#DCB167" />

  {#each lines as i}
    <line x1={px(0)} y1={px(i)} x2={px(size - 1)} y2={px(i)} stroke="#8B6914" stroke-width="1" />
    <line x1={px(i)} y1={px(0)} x2={px(i)} y2={px(size - 1)} stroke="#8B6914" stroke-width="1" />
  {/each}

  {#each starPoints as [x, y]}
    <circle cx={px(x)} cy={px(y)} r="4" fill="#8B6914" />
  {/each}

  {#each lines as y}
    {#each lines as x}
      {@const cell = board.get({ x, y })}
      {#if cell !== null}
        <circle
          cx={px(x)}
          cy={px(y)}
          r={stoneRadius}
          fill={cell === 'black' ? '#1a1a1a' : '#f5f5f5'}
          stroke={cell === 'black' ? '#000' : '#ccc'}
          stroke-width="1"
        />
      {/if}
    {/each}
  {/each}

  {#if hoveredPos && isLegal(board, hoveredPos, currentTurn)}
    <circle
      cx={px(hoveredPos.x)}
      cy={px(hoveredPos.y)}
      r={stoneRadius}
      fill={currentTurn === 'black' ? '#1a1a1a' : '#f5f5f5'}
      opacity="0.45"
      pointer-events="none"
    />
  {/if}

  {#each lines as y}
    {#each lines as x}
      <rect
        x={px(x) - cellSize / 2}
        y={px(y) - cellSize / 2}
        width={cellSize}
        height={cellSize}
        fill="transparent"
        style="cursor:pointer"
        onmouseenter={() => (hoveredPos = { x, y })}
        onclick={() => onmove?.({ x, y })}
      />
    {/each}
  {/each}
</svg>
