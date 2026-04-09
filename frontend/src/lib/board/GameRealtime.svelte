<script lang="ts">
  import { onMount, onDestroy } from 'svelte';
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

    try {
      await api.playMove(gameId, pos.x, pos.y);
    } catch (err) {
      board = before;
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
        board = applyMovePlayed(board, event);
        if (game) {
          game = {
            ...game,
            current_turn: event.color === 'black' ? 'white' : 'black',
          };
        }
      })
      .listen('.game.move.passed', (event: { color: Stone }) => {
        if (game) {
          game = { ...game, current_turn: event.color === 'black' ? 'white' : 'black' };
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
  <p>Loading...</p>
{:else if error}
  <p class="error">{error}</p>
{:else if game}
  <div class="game-realtime">
    <div class="header">
      <span>⚫ {game.black_player.name}</span>
      <span class="vs">vs</span>
      <span>⚪ {game.white_player.name}</span>
      <button onclick={onLeave} class="leave">← Back</button>
    </div>

    <div class="status">
      {#if game.status === 'playing'}
        {#if isMyTurn}
          <strong>Your turn</strong>
        {:else}
          Opponent's turn...
        {/if}
      {:else if game.status === 'finished'}
        Game over: <strong>{game.result}</strong>
      {:else}
        {game.status}
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
    />

    {#if game.status === 'playing'}
      <div class="actions">
        <button onclick={handlePass} disabled={!isMyTurn}>Pass</button>
        <button onclick={handleResign} class="resign">Resign</button>
      </div>
    {:else if game.status === 'scoring'}
      <div class="actions">
        <button onclick={submitDeadStones} disabled={selectedDead.length === 0}>
          Mark dead ({selectedDead.length})
        </button>
        <button onclick={handleConfirmDead} disabled={game.dead_stones === null}>Confirm</button>
        <button onclick={handleDisputeDead} class="resign">Dispute</button>
      </div>
    {/if}
  </div>
{/if}

<style>
  .game-realtime {
    display: inline-flex;
    flex-direction: column;
    gap: 12px;
  }
  .header {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 15px;
  }
  .vs {
    color: #999;
  }
  .leave {
    margin-left: auto;
    background: none;
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 4px 10px;
    cursor: pointer;
  }
  .status {
    font-size: 15px;
    min-height: 24px;
  }
  .actions {
    display: flex;
    gap: 8px;
  }
  .actions button {
    padding: 8px 16px;
    border: 1px solid #ccc;
    border-radius: 4px;
    cursor: pointer;
    background: #fff;
  }
  .actions button:disabled {
    opacity: 0.4;
  }
  .resign {
    color: #c00;
    border-color: #fcc !important;
  }
  .error {
    color: #c00;
    font-size: 14px;
    margin: 0;
  }
</style>
