<script lang="ts">
  import { onMount } from 'svelte';
  import { _ } from 'svelte-i18n';
  import { api } from '$lib/api';
  import GameRealtime from './GameRealtime.svelte';
  import GameReplay from './GameReplay.svelte';

  let { gameId, onLeave }: { gameId: number; onLeave: () => void } = $props();

  let status = $state<string | null>(null);
  let loading = $state(true);

  onMount(async () => {
    try {
      const res = await api.getGame(gameId);
      status = res.data.status;
    } catch {
      status = 'playing';
    } finally {
      loading = false;
    }
  });
</script>

{#if loading}
  <div class="loading">
    <p>{$_('app.loading')}</p>
  </div>
{:else if status === 'finished'}
  <GameReplay {gameId} {onLeave} />
{:else}
  <GameRealtime {gameId} {onLeave} />
{/if}

<style>
  .loading {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100vh;
    color: var(--cream);
    font-family: var(--font-serif);
  }
</style>
