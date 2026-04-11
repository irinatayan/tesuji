<script lang="ts">
  import type { Board } from '$lib/game/Board';
  import { isLegal } from '$lib/game/legality';
  import type { Position, Stone } from '$lib/game/types';

  const STAR_POINTS: Record<number, [number, number][]> = {
    9:  [[2,2],[6,2],[4,4],[2,6],[6,6]],
    13: [[3,3],[9,3],[6,6],[3,9],[9,9]],
    19: [[3,3],[9,3],[15,3],[3,9],[9,9],[15,9],[3,15],[9,15],[15,15]],
  };

  const COL_LABELS = 'ABCDEFGHJKLMNOPQRST'.split('');

  let {
    size = 9,
    board,
    currentTurn = 'black',
    onmove,
    deadStones = [],
    lastMove = null,
  }: {
    size: number;
    board: Board;
    currentTurn?: Stone;
    onmove?: (pos: Position) => void;
    deadStones?: Position[];
    lastMove?: Position | null;
  } = $props();

  const deadSet = $derived(new Set(deadStones.map((p) => `${p.x},${p.y}`)));

  const OUTER  = 504;
  const G_OFF  = 36;
  const G_SPAN = OUTER - 2 * G_OFF;

  const cellSize = $derived(G_SPAN / (size - 1));
  const stoneR   = $derived(cellSize * 0.468);
  const lines    = $derived(Array.from({ length: size }, (_, i) => i));
  const stars    = $derived(STAR_POINTS[size] ?? []);

  function px(i: number): number { return G_OFF + i * cellSize; }
  function rowLabel(y: number): string { return String(size - y); }
  function colLabel(x: number): string { return COL_LABELS[x] ?? ''; }

  const cells = $derived(board.toArray());
  let hoveredPos = $state<Position | null>(null);

  const gid = `go-${Math.random().toString(36).slice(2, 8)}`;
</script>

<div class="board-wrap">
  <!-- svelte-ignore a11y_no_static_element_interactions -->
  <svg
    viewBox="0 0 {OUTER} {OUTER}"
    width="100%"
    style="display:block"
    role="img"
    aria-label="Go board"
    onmouseleave={() => (hoveredPos = null)}
  >
    <defs>

      <!-- Black stone: dark with subtle highlight -->
      <radialGradient id="{gid}-black" cx="0.38" cy="0.32" r="0.65">
        <stop offset="0%"   stop-color="#555"/>
        <stop offset="40%"  stop-color="#222"/>
        <stop offset="100%" stop-color="#0a0a0a"/>
      </radialGradient>

      <!-- White stone: pronounced 3D highlight -->
      <radialGradient id="{gid}-white" cx="0.36" cy="0.30" r="0.68">
        <stop offset="0%"   stop-color="#ffffff"/>
        <stop offset="55%"  stop-color="#e8e8e8"/>
        <stop offset="100%" stop-color="#aaaaaa"/>
      </radialGradient>

      <filter id="{gid}-sh" x="-30%" y="-30%" width="160%" height="160%">
        <feDropShadow dx="1" dy="1.5" stdDeviation="1.8"
          flood-color="#000" flood-opacity="0.55"/>
      </filter>
    </defs>

    <!-- Board background -->
    <rect width={OUTER} height={OUTER} fill="#c49a4e"/>

    <!-- Grid lines (black, classic style) -->
    {#each lines as i}
      <line x1={px(0)} y1={px(i)} x2={px(size-1)} y2={px(i)} stroke="#000" stroke-width="1"/>
      <line x1={px(i)} y1={px(0)} x2={px(i)} y2={px(size-1)} stroke="#000" stroke-width="1"/>
    {/each}

    <!-- Star points -->
    {#each stars as [sx, sy]}
      <circle cx={px(sx)} cy={px(sy)} r={Math.max(2.5, cellSize * 0.1)} fill="#000"/>
    {/each}

    <!-- Coordinate labels -->
    {#each lines as i}
      {@const fs = Math.max(8, Math.min(13, cellSize * 0.42))}
      <text x={px(i)} y={G_OFF - 14} text-anchor="middle" dominant-baseline="middle"
        font-size={fs} font-weight="bold" fill="#222" font-family="sans-serif">{colLabel(i)}</text>
      <text x={px(i)} y={G_OFF + G_SPAN + 14} text-anchor="middle" dominant-baseline="middle"
        font-size={fs} font-weight="bold" fill="#222" font-family="sans-serif">{colLabel(i)}</text>
      <text x={G_OFF - 17} y={px(i)} text-anchor="middle" dominant-baseline="middle"
        font-size={fs} font-weight="bold" fill="#222" font-family="sans-serif">{rowLabel(i)}</text>
      <text x={G_OFF + G_SPAN + 17} y={px(i)} text-anchor="middle" dominant-baseline="middle"
        font-size={fs} font-weight="bold" fill="#222" font-family="sans-serif">{rowLabel(i)}</text>
    {/each}

    <!-- Stones -->
    {#each cells as row, y}
      {#each row as cell, x}
        {#if cell !== null}
          {@const dead  = deadSet.has(`${x},${y}`)}
          {@const isLast = lastMove?.x === x && lastMove?.y === y}
          <circle
            cx={px(x)} cy={px(y)} r={stoneR}
            fill={cell === 'black' ? `url(#${gid}-black)` : `url(#${gid}-white)`}
            stroke={cell === 'black' ? '#000' : '#999'}
            stroke-width={stoneR * 0.04}
            opacity={dead ? 0.35 : 1}
            shape-rendering="geometricPrecision"
            filter={dead ? undefined : `url(#${gid}-sh)`}
          />
          {#if isLast && !dead}
            <circle
              cx={px(x)} cy={px(y)} r={stoneR * 0.38}
              fill="none"
              stroke={cell === 'black' ? 'rgba(255,255,255,0.65)' : 'rgba(0,0,0,0.45)'}
              stroke-width={stoneR * 0.13}
              pointer-events="none"
            />
          {/if}
          {#if dead}
            {@const dr = stoneR * 0.44}
            <line x1={px(x)-dr} y1={px(y)-dr} x2={px(x)+dr} y2={px(y)+dr}
              stroke="#cc2222" stroke-width={Math.max(1.5, stoneR*0.14)}
              stroke-linecap="round" pointer-events="none"/>
            <line x1={px(x)+dr} y1={px(y)-dr} x2={px(x)-dr} y2={px(y)+dr}
              stroke="#cc2222" stroke-width={Math.max(1.5, stoneR*0.14)}
              stroke-linecap="round" pointer-events="none"/>
          {/if}
        {/if}
      {/each}
    {/each}

    <!-- Hover preview -->
    {#if hoveredPos && onmove && isLegal(board, hoveredPos, currentTurn)}
      <circle
        cx={px(hoveredPos.x)} cy={px(hoveredPos.y)} r={stoneR}
        fill={currentTurn === 'black' ? `url(#${gid}-black)` : `url(#${gid}-white)`}
        stroke={currentTurn === 'black' ? '#000' : '#999'}
        stroke-width={stoneR * 0.04}
        opacity="0.55"
        pointer-events="none"
      />
    {/if}

    <!-- Hit areas -->
    {#each lines as cy}
      {#each lines as cx}
        <!-- svelte-ignore a11y_no_static_element_interactions a11y_click_events_have_key_events -->
        <rect
          x={px(cx) - cellSize/2} y={px(cy) - cellSize/2}
          width={cellSize} height={cellSize}
          fill="transparent"
          style={onmove ? 'cursor:pointer' : ''}
          onmouseenter={() => { if (onmove) hoveredPos = {x: cx, y: cy}; }}
          onclick={() => onmove?.({x: cx, y: cy})}
        />
      {/each}
    {/each}
  </svg>
</div>

<style>
  .board-wrap {
    width: 100%;
    max-width: min(820px, 97vw, 91vh);
    margin: 0 auto;
  }
</style>
