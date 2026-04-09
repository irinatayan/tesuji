<script lang="ts">
  import { api, ApiError } from '$lib/api';

  let { onCreated }: { onCreated: (gameId: number) => void } = $props();

  let opponentId = $state('');
  let boardSize = $state(9);
  let color = $state('black');
  let error = $state('');
  let loading = $state(false);

  async function handleSubmit(e: Event) {
    e.preventDefault();
    error = '';
    loading = true;
    try {
      const res = await api.createGame({
        opponent_id: Number(opponentId),
        board_size: boardSize,
        mode: 'realtime',
        time_control_type: 'absolute',
        time_control_config: { seconds: 600 },
        color,
      });
      onCreated(res.data.id);
    } catch (err) {
      error = err instanceof ApiError ? `Error: ${err.status}` : 'Failed to create game';
    } finally {
      loading = false;
    }
  }
</script>

<div class="create-game">
  <h3>New game</h3>
  <form onsubmit={handleSubmit}>
    <label>
      Opponent ID
      <input type="number" bind:value={opponentId} required min="1" />
    </label>
    <label>
      Board size
      <select bind:value={boardSize}>
        <option value={9}>9×9</option>
        <option value={13}>13×13</option>
        <option value={19}>19×19</option>
      </select>
    </label>
    <label>
      Color
      <select bind:value={color}>
        <option value="black">Black</option>
        <option value="white">White</option>
        <option value="random">Random</option>
      </select>
    </label>
    {#if error}<p class="error">{error}</p>{/if}
    <button type="submit" disabled={loading}>
      {loading ? 'Creating...' : 'Create'}
    </button>
  </form>
</div>

<style>
  .create-game {
    max-width: 300px;
  }
  form {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  label {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 14px;
  }
  input,
  select {
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 15px;
  }
  button {
    padding: 10px;
    background: #1a1a1a;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
  }
  button:disabled {
    opacity: 0.6;
  }
  .error {
    color: #c00;
    font-size: 14px;
    margin: 0;
  }
</style>
