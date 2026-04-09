<script lang="ts">
  import { Board } from '$lib/game/Board';
  import { isLegal } from '$lib/game/legality';
  import type { Position, Stone } from '$lib/game/types';
  import GoBoard from './GoBoard.svelte';

  let { size = 9 }: { size: number } = $props();

  let board = $state(Board.empty(size));
  let currentTurn = $state<Stone>('black');
  let moveCount = $state(0);
  let passCount = $state(0);
  let phase = $state<'playing' | 'finished'>('playing');

  function handleMove(pos: Position) {
    if (phase !== 'playing') return;
    if (!isLegal(board, pos, currentTurn)) return;

    board = board.set(pos, currentTurn);
    currentTurn = currentTurn === 'black' ? 'white' : 'black';
    moveCount++;
    passCount = 0;
  }

  function handlePass() {
    if (phase !== 'playing') return;
    passCount++;
    currentTurn = currentTurn === 'black' ? 'white' : 'black';
    if (passCount >= 2) {
      phase = 'finished';
    }
  }

  function handleReset() {
    board = Board.empty(size);
    currentTurn = 'black';
    moveCount = 0;
    passCount = 0;
    phase = 'playing';
  }
</script>

<div class="game-local">
  <div class="controls">
    {#if phase === 'playing'}
      <span class="turn">
        {currentTurn === 'black' ? '⚫' : '⚪'} ход {currentTurn === 'black' ? 'чёрных' : 'белых'}
      </span>
      <button onclick={handlePass}>Пас</button>
    {:else}
      <span class="finished">Партия завершена</span>
    {/if}
    <button onclick={handleReset}>Новая партия</button>
    <span class="count">Ходов: {moveCount}</span>
  </div>

  <GoBoard {board} {size} {currentTurn} onmove={handleMove} />
</div>

<style>
  .game-local {
    display: inline-flex;
    flex-direction: column;
    gap: 12px;
    align-items: flex-start;
  }

  .controls {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 16px;
  }

  button {
    padding: 6px 14px;
    border: 1px solid #ccc;
    border-radius: 4px;
    cursor: pointer;
    background: #fff;
  }

  button:hover {
    background: #f5f5f5;
  }

  .finished {
    color: #666;
    font-style: italic;
  }

  .count {
    color: #888;
    font-size: 14px;
  }
</style>
