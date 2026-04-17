<script lang="ts">
  import { onMount } from 'svelte';
  import { _ } from 'svelte-i18n';
  import { api, type GameResponse } from '$lib/api';
  import { Board } from '$lib/game/Board';
  import type { Position, Stone } from '$lib/game/types';
  import GoBoard from './GoBoard.svelte';

  let { gameId, onLeave }: { gameId: number; onLeave: () => void } = $props();

  let game = $state<GameResponse | null>(null);
  let loading = $state(true);
  let error = $state('');
  let currentStep = $state(0);

  type MoveEntry = NonNullable<GameResponse['moves']>[number];

  interface Snapshot {
    board: Board;
    lastMove: Position | null;
    captures: { black: number; white: number };
  }

  let snapshots = $state<Snapshot[]>([]);

  const totalMoves = $derived(game?.moves?.length ?? 0);
  const snapshot = $derived(snapshots[currentStep] ?? null);

  function buildSnapshots(g: GameResponse): Snapshot[] {
    const moves = g.moves ?? [];
    let board = Board.empty(g.board_size);
    let captures = { black: 0, white: 0 };

    const result: Snapshot[] = [{ board, lastMove: null, captures: { ...captures } }];

    for (const move of moves) {
      if (move.type === 'resign') break;

      if (move.type === 'play' && move.x !== null && move.y !== null) {
        for (const cap of move.captures) {
          board = board.removeStone(cap);
        }
        board = board.placeStone({ x: move.x, y: move.y }, move.color as Stone);
        captures = {
          black: captures.black + (move.color === 'black' ? move.captures.length : 0),
          white: captures.white + (move.color === 'white' ? move.captures.length : 0),
        };
        result.push({ board, lastMove: { x: move.x, y: move.y }, captures: { ...captures } });
      } else {
        result.push({ board, lastMove: null, captures: { ...captures } });
      }
    }

    return result;
  }

  function goToStart() {
    currentStep = 0;
  }
  function goBack() {
    currentStep = Math.max(0, currentStep - 1);
  }
  function goForward() {
    currentStep = Math.min(totalMoves, currentStep + 1);
  }
  function goToEnd() {
    currentStep = totalMoves;
  }

  function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'ArrowLeft') {
      e.preventDefault();
      goBack();
    } else if (e.key === 'ArrowRight') {
      e.preventDefault();
      goForward();
    } else if (e.key === 'Home') {
      e.preventDefault();
      goToStart();
    } else if (e.key === 'End') {
      e.preventDefault();
      goToEnd();
    }
  }

  function downloadSgf() {
    const token = localStorage.getItem('token');
    const base = import.meta.env.VITE_API_URL ?? '';
    const url = `${base}/api/games/${gameId}/sgf`;
    const a = document.createElement('a');
    a.href = url;
    a.download = `game-${gameId}.sgf`;

    fetch(url, { headers: { Authorization: `Bearer ${token}` } })
      .then((r) => r.blob())
      .then((blob) => {
        a.href = URL.createObjectURL(blob);
        a.click();
        URL.revokeObjectURL(a.href);
      });
  }

  onMount(async () => {
    try {
      const res = await api.getGame(gameId);
      game = res.data;
      snapshots = buildSnapshots(game);
      currentStep = totalMoves;
    } catch {
      error = 'Failed to load game';
    } finally {
      loading = false;
    }
  });
</script>

<svelte:window onkeydown={handleKeydown} />

{#if loading}
  <p>{$_('app.loading')}</p>
{:else if error}
  <p class="error">{error}</p>
{:else if game && snapshot}
  <div class="game-replay">
    <div class="game-header">
      <button onclick={onLeave} class="leave" aria-label={$_('app.back')}>
        <span class="leave-arrow">←</span>
        <span class="leave-text">{$_('app.back')}</span>
      </button>
      <div class="players-strip">
        <div class="player-row">
          <span class="stone">⚫</span>
          <span class="player-name">{game.black_player.name}</span>
          <span class="captures">×{snapshot.captures.black}</span>
        </div>
        <div class="player-row">
          <span class="stone">⚪</span>
          <span class="player-name">{game.white_player.name}</span>
          <span class="captures">×{snapshot.captures.white}</span>
        </div>
      </div>
      <button class="sgf-btn" onclick={downloadSgf} aria-label={$_('replay.download')}>
        ⬇ SGF
      </button>
    </div>

    <div class="game-layout">
      <div class="game-body">
        <div class="status-bar">
          <strong>{$_('replay.title')}</strong>
          —
          {$_('replay.move', { values: { n: currentStep, total: totalMoves } })}
          {#if game.result}
            — <strong>{game.result}</strong>
          {/if}
        </div>

        <GoBoard
          board={snapshot.board}
          size={game.board_size}
          currentTurn={'black'}
          lastMove={snapshot.lastMove}
        />

        <div class="controls">
          <div class="nav-buttons">
            <button
              onclick={goToStart}
              disabled={currentStep === 0}
              aria-label={$_('replay.start')}
            >
              |◁
            </button>
            <button onclick={goBack} disabled={currentStep === 0} aria-label={$_('replay.prev')}>
              ◁
            </button>
            <button
              onclick={goForward}
              disabled={currentStep >= totalMoves}
              aria-label={$_('replay.next')}
            >
              ▷
            </button>
            <button
              onclick={goToEnd}
              disabled={currentStep >= totalMoves}
              aria-label={$_('replay.end')}
            >
              ▷|
            </button>
          </div>
          <input type="range" min="0" max={totalMoves} bind:value={currentStep} class="slider" />
        </div>
      </div>
    </div>
  </div>
{/if}

<style>
  .game-replay {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    height: 100vh;
    height: 100dvh;
    background: var(--bg-dark);
    color: var(--cream);
    overflow: hidden;
  }

  .game-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 16px;
    background: rgba(0, 0, 0, 0.35);
    border-bottom: 1px solid var(--border-dim);
    flex-shrink: 0;
  }

  .leave {
    background: none;
    border: 1px solid rgba(139, 90, 43, 0.3);
    border-radius: 6px;
    color: var(--cream);
    font-size: 14px;
    cursor: pointer;
    padding: 5px 10px;
    transition: border-color 0.2s;
  }
  .leave:hover {
    border-color: var(--gold);
  }
  .leave-text {
    display: inline;
  }

  .players-strip {
    display: flex;
    gap: 16px;
    flex: 1;
    min-width: 0;
  }
  .player-row {
    display: flex;
    align-items: center;
    gap: 4px;
    min-width: 0;
  }
  .stone {
    font-size: 13px;
    flex-shrink: 0;
  }
  .player-name {
    font-family: var(--font-serif);
    font-size: 13px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  .captures {
    font-size: 12px;
    opacity: 0.5;
    flex-shrink: 0;
  }

  .sgf-btn {
    background: none;
    border: 1px solid rgba(139, 90, 43, 0.3);
    border-radius: 6px;
    color: var(--cream);
    font-size: 12px;
    padding: 5px 10px;
    cursor: pointer;
    white-space: nowrap;
    transition: all 0.2s;
  }
  .sgf-btn:hover {
    border-color: var(--gold);
    color: var(--gold);
  }

  .game-layout {
    flex: 1;
    display: flex;
    overflow: hidden;
  }
  .game-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 8px;
    overflow: hidden;
  }

  .status-bar {
    font-family: var(--font-serif);
    font-size: 14px;
    text-align: center;
    padding: 4px 12px;
    opacity: 0.85;
  }

  .controls {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    width: 100%;
    max-width: 400px;
    padding: 0 8px;
  }

  .nav-buttons {
    display: flex;
    gap: 8px;
  }
  .nav-buttons button {
    background: rgba(139, 90, 43, 0.08);
    border: 1px solid rgba(139, 90, 43, 0.3);
    border-radius: 8px;
    color: var(--cream);
    font-size: 18px;
    width: 52px;
    height: 44px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
  }
  .nav-buttons button:hover:not(:disabled) {
    border-color: var(--gold);
    color: var(--gold);
    background: rgba(139, 90, 43, 0.15);
  }
  .nav-buttons button:disabled {
    opacity: 0.3;
    cursor: not-allowed;
  }

  .slider {
    width: 100%;
    accent-color: var(--gold);
    cursor: pointer;
  }

  .error {
    color: #ffaaaa;
    font-size: 13px;
    text-align: center;
    padding: 20px;
  }

  @media (max-width: 480px) {
    .leave-text {
      display: none;
    }
    .players-strip {
      gap: 8px;
    }
    .nav-buttons button {
      width: 48px;
      height: 44px;
      font-size: 16px;
    }
  }
</style>
