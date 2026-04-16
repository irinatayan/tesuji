<script lang="ts">
  import { _ } from 'svelte-i18n';
  import { api, type GameResponse } from '$lib/api';
  import { auth } from '$lib/stores/auth.svelte';
  import { getEcho } from '$lib/echo';
  import { onMount, onDestroy } from 'svelte';

  let {
    onSelect,
    refresh = $bindable(0),
  }: { onSelect: (gameId: number) => void; refresh?: number } = $props();

  let games = $state<GameResponse[]>([]);
  let loading = $state(true);

  async function load() {
    try {
      const res = await api.getGames();
      games = res.data;
    } finally {
      loading = false;
    }
  }

  onMount(load);

  $effect(() => {
    if (refresh > 0) load();
  });

  let userChannel: ReturnType<ReturnType<typeof getEcho>['private']> | null = null;

  $effect(() => {
    const userId = auth.user?.id;
    if (!userId) return;

    const channel = getEcho().private(`user.${userId}`);
    userChannel = channel;
    channel.listen('.unread.changed', (e: { game_id: number; unread_count: number }) => {
      games = games.map((g) => (g.id === e.game_id ? { ...g, unread_count: e.unread_count } : g));
    });

    return () => {
      try {
        channel.stopListening('.unread.changed');
        getEcho().leave(`user.${userId}`);
      } catch {
        // channel already left
      }
      userChannel = null;
    };
  });

  onDestroy(() => {
    userChannel = null;
  });

  function opponent(game: GameResponse): { name: string; isBot: boolean } {
    if (!auth.user) return { name: '?', isBot: false };
    const opp = game.black_player.id === auth.user.id ? game.white_player : game.black_player;
    return { name: opp.name, isBot: !!opp.is_bot };
  }

  function myColor(game: GameResponse): string {
    if (!auth.user) return '';
    return game.black_player.id === auth.user.id ? '⚫' : '⚪';
  }
</script>

<div class="game-list">
  <h3>{$_('games.active')}</h3>
  {#if loading}
    <p class="empty">{$_('games.loading')}</p>
  {:else if games.length === 0}
    <p class="empty">{$_('games.noActive')}</p>
  {:else}
    <ul>
      {#each games as game}
        <li>
          <button onclick={() => onSelect(game.id)}>
            <span class="game-players">
              {myColor(game)} <span class="vs">{$_('games.vs')}</span>
              {#if opponent(game).isBot}<span class="bot-badge" title="Bot">🤖</span>{/if}
              {opponent(game).name}
              {#if game.unread_count && game.unread_count > 0}
                <span class="unread-badge" title={`${game.unread_count} unread`}>
                  {game.unread_count}
                </span>
              {/if}
            </span>
            <span class="game-meta">
              {game.board_size}×{game.board_size}
              &middot;
              <span class="status {game.status}">{game.status}</span>
            </span>
          </button>
        </li>
      {/each}
    </ul>
  {/if}
</div>

<style>
  .game-list {
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card2) 100%);
    border: 2px solid var(--border);
    border-radius: 8px;
    padding: 20px 24px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5);
  }
  h3 {
    margin: 0 0 16px;
    font-family: var(--font-display);
    font-size: 15px;
    font-weight: 600;
    color: var(--gold);
    letter-spacing: 2px;
    text-transform: uppercase;
    border-bottom: 1px solid var(--border-dim);
    padding-bottom: 10px;
  }
  .empty {
    margin: 0;
    color: var(--muted);
    font-size: 14px;
    font-style: italic;
  }
  ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  li button {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    background: rgba(20, 13, 8, 0.5);
    border: 1px solid var(--border-dim);
    border-radius: 6px;
    cursor: pointer;
    font-family: var(--font-serif);
    font-size: 14px;
    color: var(--cream);
    transition: all 0.2s;
  }
  li button:hover {
    background: rgba(139, 90, 43, 0.15);
    border-color: var(--border);
    color: var(--gold-light);
  }
  .game-players {
    font-weight: 600;
  }
  .vs {
    color: var(--muted);
    font-weight: 400;
    font-style: italic;
    margin: 0 4px;
  }
  .game-meta {
    color: var(--muted);
    font-size: 13px;
  }
  .status {
    font-weight: 600;
  }
  .status.playing {
    color: var(--gold);
  }
  .status.scoring {
    color: #90c0a0;
  }
  .status.finished {
    color: var(--subtle);
  }
  .unread-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 6px;
    margin-left: 8px;
    background: #c0504d;
    color: #fff;
    border-radius: 10px;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.5px;
    line-height: 1;
  }
</style>
