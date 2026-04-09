<script lang="ts">
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
  <h3>Active games</h3>
  {#if loading}
    <p>Loading...</p>
  {:else if games.length === 0}
    <p>No active games</p>
  {:else}
    <ul>
      {#each games as game}
        <li>
          <button onclick={() => onSelect(game.id)}>
            {myColor(game)} vs {opponentName(game)}
            — {game.board_size}×{game.board_size}
            — <span class="status">{game.status}</span>
          </button>
        </li>
      {/each}
    </ul>
  {/if}
</div>

<style>
  .game-list {
    max-width: 400px;
  }
  ul {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  li button {
    width: 100%;
    text-align: left;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
    font-size: 14px;
  }
  li button:hover {
    background: #f5f5f5;
  }
  .status {
    color: #888;
  }
</style>
