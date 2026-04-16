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
  import Chat from './Chat.svelte';

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
  let chatCollapsed = $state(window.innerWidth < 720);
  let chatUnread = $state(0);

  const myColor = $derived.by<Stone | null>(() => {
    if (!game || !auth.user) return null;
    if (game.black_player.id === auth.user.id) return 'black';
    if (game.white_player.id === auth.user.id) return 'white';
    return null;
  });

  const isSpectator = $derived(myColor === null);
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

  let channel = $state<
    ReturnType<typeof getEcho>['private'] extends (...args: unknown[]) => infer R ? R : never
  >(null as any);

  onMount(async () => {
    await loadGame();

    channel = getEcho().private(`game.${gameId}`);

    channel
      .listen('.game.move.played', (event: MovePlayed) => {
        console.log('[WS] game.move.played', JSON.stringify(event));
        board = applyMovePlayed(board, event);
        lastMove = { x: event.x, y: event.y };
        if (game) {
          const captured = event.captures.length;
          game = {
            ...game,
            current_turn: event.color === 'black' ? 'white' : 'black',
            captures: {
              black: game.captures.black + (event.color === 'black' ? captured : 0),
              white: game.captures.white + (event.color === 'white' ? captured : 0),
            },
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
      <button onclick={onLeave} class="leave" aria-label={$_('app.back')}>
        <span class="leave-arrow">←</span>
        <span class="leave-text">{$_('app.back')}</span>
      </button>
      <div class="players-strip">
        <div class="player-row">
          <span class="stone">⚫</span>
          <span class="player-name">{game.black_player.name}</span>
          <span class="captures">×{game.captures.black}</span>
        </div>
        <div class="player-row">
          <span class="stone">⚪</span>
          <span class="player-name">{game.white_player.name}</span>
          <span class="captures">×{game.captures.white}</span>
        </div>
      </div>
      {#if !isSpectator}
        <button
          class="chat-open-btn"
          onclick={() => (chatCollapsed = false)}
          aria-label="Open chat"
        >
          💬{#if chatUnread > 0}<span class="chat-badge">{chatUnread}</span>{/if}
        </button>
      {/if}
    </div>

    <div class="game-layout">
      <div class="game-body">
        <div class="status-bar">
          {#if isSpectator}
            <em>{$_('game.spectating')}</em>
            {#if game.status === 'playing'}
              — {game.current_turn === 'black' ? game.black_player.name : game.white_player.name}
            {:else if game.status === 'scoring'}
              — {$_('game.scoring')}
            {:else if game.status === 'finished'}
              — {$_('game.gameOver')} — <strong>{game.result}</strong>
            {/if}
          {:else if game.status === 'playing'}
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
          currentTurn={myColor ?? (game.current_turn as Stone)}
          onmove={isSpectator
            ? undefined
            : game.status === 'playing' && isMyTurn
              ? handleMove
              : game.status === 'scoring'
                ? handleToggleDead
                : undefined}
          deadStones={[...(game.dead_stones ?? []), ...selectedDead]}
          {lastMove}
        />

        {#if !isSpectator}
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
        {/if}
      </div>

      {#if !isSpectator}
        {#if !chatCollapsed}
          <button
            class="chat-backdrop"
            onclick={() => (chatCollapsed = true)}
            aria-label="Close chat"
          ></button>
        {/if}

        <div class="chat-panel" class:chat-panel--collapsed={chatCollapsed}>
          <button class="chat-strip" onclick={() => (chatCollapsed = false)} aria-label="Open chat">
            💬{#if chatUnread > 0}<span class="chat-strip-badge">{chatUnread}</span>{/if}
          </button>
          <div class="chat-handle"></div>
          <div class="chat-content">
            <Chat
              {gameId}
              currentUserId={auth.user?.id ?? 0}
              {channel}
              collapsed={chatCollapsed}
              onCollapse={() => (chatCollapsed = true)}
              onUnreadChange={(n) => (chatUnread = n)}
            />
          </div>
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
    height: 100vh;
    position: relative;
  }

  .game-layout {
    display: flex;
    flex: 1;
    gap: 0;
    align-items: stretch;
    min-height: 0;
  }

  .game-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    padding: 24px 8px 32px;
    min-width: 0;
    overflow-y: auto;
  }

  /* ── Chat panel — desktop ────────────────────── */
  .chat-panel {
    width: 280px;
    flex-shrink: 0;
    padding: 16px 16px 16px 0;
    display: flex;
    flex-direction: column;
    transition: width 0.25s ease;
    overflow: hidden;
    min-height: 0;
  }

  .chat-panel--collapsed {
    width: 44px;
    padding: 12px 0;
    cursor: default;
  }

  .chat-content {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
  }

  /* Desktop collapsed strip */
  .chat-strip {
    display: none;
    width: 44px;
    flex-direction: column;
    align-items: center;
    padding: 10px 0;
    background: none;
    border: none;
    color: var(--gold);
    font-size: 18px;
    cursor: pointer;
    position: relative;
    flex-shrink: 0;
    transition: color 0.2s;
  }
  .chat-strip:hover {
    color: var(--gold-light);
  }
  .chat-panel--collapsed .chat-strip {
    display: flex;
  }

  .chat-strip-badge {
    position: absolute;
    top: 6px;
    right: 6px;
    background: #c0392b;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 8px;
    font-family: var(--font-display);
    line-height: 1.4;
  }

  /* Drag handle — hidden on desktop */
  .chat-handle {
    display: none;
  }

  /* Backdrop — hidden on desktop */
  .chat-backdrop {
    display: none;
  }

  /* ── Chat panel — mobile ─────────────────────── */
  @media (max-width: 719px) {
    .chat-panel {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      width: 100%;
      max-width: 100vw;
      box-sizing: border-box;
      height: 58vh;
      padding: 0;
      z-index: 200;
      border-radius: 16px 16px 0 0;
      background: rgba(14, 9, 5, 0.97);
      backdrop-filter: blur(8px);
      border-top: 1px solid var(--border);
      transform: translateY(0);
      transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
      overflow: hidden;
    }

    .chat-panel--collapsed {
      width: auto;
      padding: 0;
      transform: translateY(105%);
      pointer-events: none;
    }

    .chat-strip {
      display: none !important;
    }

    .chat-handle {
      display: flex;
      justify-content: center;
      padding: 10px 0 6px;
      flex-shrink: 0;
    }
    .chat-handle::before {
      content: '';
      width: 36px;
      height: 4px;
      background: var(--border);
      border-radius: 2px;
    }

    .chat-backdrop {
      display: block;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.45);
      z-index: 199;
      animation: fadeIn 0.2s ease;
      border: none;
      padding: 0;
      cursor: default;
    }
    @keyframes fadeIn {
      from {
        opacity: 0;
      }
      to {
        opacity: 1;
      }
    }
  }

  .game-header {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 24px;
    background: linear-gradient(180deg, rgba(20, 12, 8, 0.95) 0%, rgba(30, 18, 10, 0.9) 100%);
    border-bottom: 2px solid var(--border);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
    box-sizing: border-box;
    position: relative;
    z-index: 10;
  }

  .chat-open-btn {
    display: none;
    position: relative;
    padding: 6px 10px;
    background: transparent;
    color: var(--gold);
    border: 2px solid var(--border);
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
    flex-shrink: 0;
    transition: all 0.2s;
  }
  .chat-open-btn:hover {
    background: rgba(139, 90, 43, 0.2);
    border-color: var(--gold);
  }

  .chat-badge {
    position: absolute;
    top: -6px;
    right: -6px;
    background: #c0392b;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 8px;
    font-family: var(--font-display);
    line-height: 1.4;
  }

  .players-strip {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
  }

  .player-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-serif);
    font-size: 14px;
    font-weight: 600;
    color: var(--cream);
    min-width: 0;
    line-height: 1.2;
  }

  .player-name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .captures {
    flex-shrink: 0;
    font-family: var(--font-display);
    font-size: 13px;
    color: var(--gold);
    letter-spacing: 0.5px;
  }

  .stone {
    flex-shrink: 0;
  }

  .leave {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    background: transparent;
    color: var(--gold);
    border: 2px solid var(--border);
    border-radius: 4px;
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 12px;
    letter-spacing: 1px;
    line-height: 1;
    flex-shrink: 0;
    transition: all 0.2s;
  }
  .leave:hover {
    background: rgba(139, 90, 43, 0.2);
    border-color: var(--gold);
  }
  .leave-arrow {
    font-size: 16px;
    line-height: 1;
  }

  @media (max-width: 719px) {
    .game-header {
      padding: 10px 12px;
      gap: 8px;
    }
    .chat-open-btn {
      display: flex;
      align-items: center;
    }
    .leave {
      padding: 6px 8px;
    }
    .leave-text {
      display: none;
    }
    .player-row {
      font-size: 13px;
      gap: 6px;
    }
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
    background: rgba(139, 90, 43, 0.1);
  }
  .actions button:disabled {
    opacity: 0.3;
    cursor: not-allowed;
  }

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
    border-color: rgba(200, 100, 100, 0.4) !important;
  }
  .resign:hover {
    border-color: #e07070 !important;
    background: rgba(200, 100, 100, 0.1) !important;
    color: #e07070 !important;
  }

  .error {
    color: #ffaaaa;
    font-size: 13px;
    margin: 0;
  }
</style>
