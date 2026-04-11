<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
  import { _ } from 'svelte-i18n';
  import { api, ApiError, type GameResponse } from '$lib/api';
  import { auth } from '$lib/stores/auth.svelte';
  import { getEcho } from '$lib/echo';
  import { Board } from '$lib/game/Board';
  import { applyMovePlayed, type MovePlayed } from '$lib/game/events';
  import { isLegal } from '$lib/game/legality';
  import type { Position, Stone } from '$lib/game/types';
  import GoBoard from './GoBoard.svelte';

  interface DeadStonesMarked {
    by: Stone;
    stones: Position[];
  }

  let { gameId, onLeave }: { gameId: number; onLeave: () => void } = $props();

  let game = $state<GameResponse | null>(null);
  let board = $state(Board.empty(9));
  let loading = $state(true);
  let error = $state('');
  let moveError = $state('');
  let selectedDead = $state<Position[]>([]);
  let lastMove = $state<Position | null>(null);

  const myColor = $derived.by<Stone | null>(() => {
    if (!game || !auth.user) return null;
    if (game.black_player.id === auth.user.id) return 'black';
    if (game.white_player.id === auth.user.id) return 'white';
    return null;
  });

  const isMyTurn = $derived(game?.current_turn === myColor && game?.status === 'playing');

  function boardFromGame(g: GameResponse): Board {
    let b = Board.empty(g.board_size);
    for (let y = 0; y < g.board_size; y++) {
      for (let x = 0; x < g.board_size; x++) {
        const cell = g.board[y][x] as Stone | null;
        if (cell !== null) {
          b = b.set({ x, y }, cell);
        }
      }
    }
    return b;
  }

  async function loadGame() {
    try {
      const res = await api.getGame(gameId);
      game = res.data;
      board = boardFromGame(res.data);
    } catch {
      error = 'Failed to load game';
    } finally {
      loading = false;
    }
  }

  async function handleMove(pos: Position) {
    if (!isMyTurn) return;
    if (!isLegal(board, pos, myColor!)) return;

    moveError = '';
    const before = board;
    board = board.set(pos, myColor!);
    lastMove = pos;

    try {
      const res = await api.playMove(gameId, pos.x, pos.y);
      game = res.data;
      board = boardFromGame(res.data);
    } catch (err) {
      board = before;
      lastMove = null;
      moveError =
        err instanceof ApiError && err.status === 422
          ? (err.body as { message: string }).message
          : 'Illegal move';
    }
  }

  async function handlePass() {
    if (!isMyTurn) return;
    moveError = '';
    try {
      await api.pass(gameId);
    } catch {
      moveError = 'Error';
    }
  }

  async function handleResign() {
    if (!game || game.status !== 'playing') return;
    if (!confirm('Resign?')) return;
    try {
      await api.resign(gameId);
    } catch {
      moveError = 'Error';
    }
  }

  function handleToggleDead(pos: Position) {
    if (!board.get(pos)) return;
    const group = board.group(pos);
    const alreadySelected = selectedDead.some((p) => p.x === pos.x && p.y === pos.y);
    if (alreadySelected) {
      selectedDead = selectedDead.filter((p) => !group.some((g) => g.x === p.x && g.y === p.y));
    } else {
      selectedDead = [...selectedDead, ...group];
    }
  }

  async function submitDeadStones() {
    if (selectedDead.length === 0) return;
    moveError = '';
    try {
      await api.markDead(gameId, selectedDead);
      selectedDead = [];
    } catch {
      moveError = 'Failed to submit';
    }
  }

  async function handleConfirmDead() {
    moveError = '';
    try {
      await api.confirmDead(gameId);
    } catch {
      moveError = 'Error';
    }
  }

  async function handleDisputeDead() {
    moveError = '';
    try {
      await api.disputeDead(gameId);
      selectedDead = [];
    } catch {
      moveError = 'Error';
    }
  }

  let channel: ReturnType<typeof getEcho>['private'] extends (...args: unknown[]) => infer R
    ? R
    : never;

  onMount(async () => {
    await loadGame();

    channel = getEcho().private(`game.${gameId}`);

    channel
      .listen('.game.move.played', (event: MovePlayed) => {
        console.log('[WS] game.move.played', JSON.stringify(event));
        board = applyMovePlayed(board, event);
        lastMove = { x: event.x, y: event.y };
        if (game) {
          game = {
            ...game,
            current_turn: event.color === 'black' ? 'white' : 'black',
          };
        }
      })
      .listen('.game.move.passed', (event: { color: Stone; status: string }) => {
        if (game) {
          game = {
            ...game,
            current_turn: event.color === 'black' ? 'white' : 'black',
            status: event.status as GameResponse['status'],
          };
        }
      })
      .listen('.game.player.resigned', (event: { color: Stone }) => {
        if (game) {
          const winner = event.color === 'black' ? 'white' : 'black';
          game = { ...game, status: 'finished', result: `${winner[0].toUpperCase()}+R` };
        }
      })
      .listen('.game.dead.marked', (event: DeadStonesMarked) => {
        if (game) {
          game = { ...game, status: 'scoring', dead_stones: event.stones };
        }
      })
      .listen('.game.finished', (event: { result: string }) => {
        if (game) {
          game = { ...game, status: 'finished', result: event.result };
          selectedDead = [];
        }
      });
  });

  onDestroy(() => {
    getEcho().leave(`game.${gameId}`);
  });
</script>

{#if loading}
  <p>{$_('app.loading')}</p>
{:else if error}
  <p class="error">{error}</p>
{:else if game}
  <div class="game-realtime">
    <div class="game-header">
      <div class="players">
        <span>⚫ {game.black_player.name}</span>
        <span class="vs">{$_('games.vs')}</span>
        <span>⚪ {game.white_player.name}</span>
      </div>
      <button onclick={onLeave} class="leave">{$_('app.back')}</button>
    </div>

    <div class="game-body">
      <div class="status-bar">
        {#if game.status === 'playing'}
          {#if isMyTurn}
            <strong>{$_('game.yourTurn')}</strong>
          {:else}
            {$_('game.opponentTurn')}
          {/if}
        {:else if game.status === 'scoring'}
          <strong>{$_('game.scoring')}</strong> — {$_('game.markDead')}
        {:else if game.status === 'finished'}
          {$_('game.gameOver')} — <strong>{game.result}</strong>
        {/if}
      </div>

      {#if moveError}
        <p class="error">{moveError}</p>
      {/if}

      <GoBoard
        {board}
        size={game.board_size}
        currentTurn={myColor ?? 'black'}
        onmove={game.status === 'playing' && isMyTurn
          ? handleMove
          : game.status === 'scoring'
            ? handleToggleDead
            : undefined}
        deadStones={[...(game.dead_stones ?? []), ...selectedDead]}
        {lastMove}
      />

      {#if game.status === 'playing'}
        <div class="actions">
          <button onclick={handlePass} disabled={!isMyTurn}>{$_('game.pass')}</button>
          <button onclick={handleResign} class="resign">{$_('game.resign')}</button>
        </div>
      {:else if game.status === 'scoring'}
        <div class="actions">
          <button onclick={submitDeadStones} disabled={selectedDead.length === 0}>
            {$_('game.markDeadCount', { values: { count: selectedDead.length } })}
          </button>
          <button onclick={handleConfirmDead} class="btn-confirm">{$_('game.confirm')}</button>
          <button onclick={handleDisputeDead} class="resign">{$_('game.dispute')}</button>
        </div>
      {/if}
    </div>
  </div>
{/if}

<style>
  .game-realtime {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    min-height: 100vh;
    padding-bottom: 32px;
  }

  .game-header {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 32px;
    background: linear-gradient(180deg, rgba(20,12,8,0.95) 0%, rgba(30,18,10,0.9) 100%);
    border-bottom: 2px solid var(--border);
    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    box-sizing: border-box;
  }

  .players {
    display: flex;
    align-items: center;
    gap: 16px;
    font-family: var(--font-serif);
    font-size: 15px;
    font-weight: 600;
    color: var(--cream);
  }

  .vs {
    color: var(--muted);
    font-style: italic;
    font-weight: 400;
    font-size: 13px;
  }

  .leave {
    padding: 8px 18px;
    background: transparent;
    color: var(--gold);
    border: 2px solid var(--border);
    border-radius: 4px;
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 12px;
    letter-spacing: 1px;
    transition: all 0.2s;
  }
  .leave:hover {
    background: rgba(139,90,43,0.2);
    border-color: var(--gold);
  }

  .game-body {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    padding: 24px 8px 0;
    width: 100%;
    box-sizing: border-box;
  }

  .status-bar {
    font-family: var(--font-serif);
    font-size: 15px;
    color: var(--muted);
    min-height: 22px;
    letter-spacing: 0.5px;
  }
  .status-bar strong {
    color: var(--gold);
    font-weight: 600;
  }

  .actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    justify-content: center;
  }

  .actions button {
    padding: 10px 22px;
    background: transparent;
    color: var(--cream);
    border: 2px solid var(--border-dim);
    border-radius: 4px;
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 13px;
    letter-spacing: 1px;
    text-transform: uppercase;
    transition: all 0.2s;
  }
  .actions button:hover:not(:disabled) {
    border-color: var(--gold);
    color: var(--gold);
    background: rgba(139,90,43,0.1);
  }
  .actions button:disabled { opacity: 0.3; cursor: not-allowed; }

  .actions button.btn-confirm {
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: var(--bg-dark);
    border-color: var(--cream);
    font-weight: 700;
  }
  .actions button.btn-confirm:hover {
    background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%);
    transform: translateY(-1px);
  }

  .resign {
    color: #e07070 !important;
    border-color: rgba(200,100,100,0.4) !important;
  }
  .resign:hover {
    border-color: #e07070 !important;
    background: rgba(200,100,100,0.1) !important;
    color: #e07070 !important;
  }

  .error {
    color: #ffaaaa;
    font-size: 13px;
    margin: 0;
  }
</style>
