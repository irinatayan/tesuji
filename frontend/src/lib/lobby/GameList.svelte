<script lang="ts">
  import { _ } from 'svelte-i18n';
  import { api, type GameResponse } from '$lib/api';
  import { auth } from '$lib/stores/auth.svelte';
  import { onMount } from 'svelte';

  let { onSelect }: { onSelect: (gameId: number) => void } = $props();

  let games = $state<GameResponse[]>([]);
  let loading = $state(true);

  onMount(async () => {
    try {
      const res = await api.getGames();
      games = res.data;
    } finally {
      loading = false;
    }
  });

  function opponentName(game: GameResponse): string {
    if (!auth.user) return '?';
    return game.black_player.id === auth.user.id ? game.white_player.name : game.black_player.name;
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
              {myColor(game)} <span class="vs">{$_('games.vs')}</span> {opponentName(game)}
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
    box-shadow: 0 8px 30px rgba(0,0,0,0.5);
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
    background: rgba(20,13,8,0.5);
    border: 1px solid var(--border-dim);
    border-radius: 6px;
    cursor: pointer;
    font-family: var(--font-serif);
    font-size: 14px;
    color: var(--cream);
    transition: all 0.2s;
  }
  li button:hover {
    background: rgba(139,90,43,0.15);
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
  .status { font-weight: 600; }
  .status.playing { color: var(--gold); }
  .status.scoring { color: #90c0a0; }
  .status.finished { color: var(--subtle); }
</style>
