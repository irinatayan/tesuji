<script lang="ts">
  import type { Board } from '$lib/game/Board';

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

  let { size = 9, board }: { size: number; board: Board } = $props();

  const SVG_SIZE = 540;
  const MARGIN = 40;
  const cellSize = (SVG_SIZE - 2 * MARGIN) / (size - 1);

  function px(coord: number): number {
    return MARGIN + coord * cellSize;
  }

  const lines = Array.from({ length: size }, (_, i) => i);
  const starPoints = STAR_POINTS[size] ?? [];
</script>

<svg width={SVG_SIZE} height={SVG_SIZE} viewBox="0 0 {SVG_SIZE} {SVG_SIZE}" style="display:block">
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
          r={cellSize * 0.47}
          fill={cell === 'black' ? '#1a1a1a' : '#f5f5f5'}
          stroke={cell === 'black' ? '#000' : '#ccc'}
          stroke-width="1"
        />
      {/if}
    {/each}
  {/each}
</svg>
