<script lang="ts">
  import { api, ApiError } from '$lib/api';
  import UserSearch from './UserSearch.svelte';

  let { onCreated }: { onCreated: (gameId: number) => void } = $props();

  let opponentId = $state<number | null>(null);
  let boardSize = $state(9);
  let color = $state('black');
  let error = $state('');
  let loading = $state(false);

  async function handleSubmit(e: Event) {
    e.preventDefault();
    if (opponentId === null) return;
    error = '';
    loading = true;
    try {
      const res = await api.createGame({
        opponent_id: opponentId,
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
  <h3>New Game</h3>
  <form onsubmit={handleSubmit}>
    <label>
      Opponent
      <UserSearch onSelect={(user) => (opponentId = user.id)} />
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
  form {
    display: flex;
    flex-direction: column;
    gap: 14px;
  }
  label {
    display: flex;
    flex-direction: column;
    gap: 6px;
    font-size: 13px;
    color: var(--gold);
    font-weight: 600;
    letter-spacing: 0.5px;
  }
  select {
    padding: 10px 14px;
    background: var(--bg-input);
    border: 2px solid var(--border-dim);
    border-radius: 6px;
    color: var(--cream);
    font-family: var(--font-serif);
    font-size: 14px;
    cursor: pointer;
    transition: border-color 0.2s;
  }
  select:focus {
    outline: none;
    border-color: var(--gold);
  }
  select option {
    background: #2c1810;
    color: var(--cream);
  }
  button[type="submit"] {
    padding: 12px;
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: var(--bg-dark);
    border: 2px solid var(--cream);
    border-radius: 6px;
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    transition: all 0.2s;
    margin-top: 4px;
  }
  button[type="submit"]:hover:not(:disabled) {
    background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%);
    transform: translateY(-1px);
  }
  button:disabled { opacity: 0.5; cursor: not-allowed; }
  .error {
    color: #ffcccc;
    font-size: 13px;
    margin: 0;
  }
</style>
