<script lang="ts">
  import { onMount } from 'svelte';
  import { _ } from 'svelte-i18n';
  import { api, type GameResponse } from '$lib/api';
  import { auth } from '$lib/stores/auth.svelte';

  let { onSelect }: { onSelect: (id: number) => void } = $props();

  let games = $state<GameResponse[]>([]);
  let loading = $state(true);

  async function load() {
    try {
      const res = await api.getLiveGames();
      games = res.data.filter(
        (g) =>
          auth.user && g.black_player.id !== auth.user.id && g.white_player.id !== auth.user.id,
      );
    } catch {
      // silent
    } finally {
      loading = false;
    }
  }

  onMount(load);
</script>

{#if loading}
  <p class="empty">{$_('games.loading')}</p>
{:else if games.length > 0}
  <div class="live-games">
    <h3>{$_('games.live')}</h3>
    <ul>
      {#each games as game}
        <li>
          <button onclick={() => onSelect(game.id)}>
            <span class="game-players">
              ⚫ {game.black_player.name}
              <span class="vs">{$_('games.vs')}</span>
              ⚪ {game.white_player.name}
            </span>
            <span class="game-meta">
              {game.board_size}×{game.board_size}
              &middot;
              <span class="status">{$_('games.spectate')}</span>
            </span>
          </button>
        </li>
      {/each}
    </ul>
  </div>
{/if}

<style>
  .live-games {
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
  ul {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }
  li button {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: rgba(139, 90, 43, 0.08);
    border: 1px solid var(--border-dim);
    border-radius: 6px;
    color: var(--cream);
    font-family: var(--font-serif);
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
  }
  li button:hover {
    background: rgba(139, 90, 43, 0.18);
    border-color: var(--gold);
    transform: translateY(-1px);
  }
  .game-players {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .vs {
    opacity: 0.5;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  .game-meta {
    font-size: 12px;
    opacity: 0.6;
  }
  .status {
    color: #7acf7a;
  }
  .empty {
    color: var(--cream);
    opacity: 0.5;
    font-size: 14px;
    text-align: center;
    font-style: italic;
  }
</style>
