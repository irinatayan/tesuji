<script lang="ts">
  import { _ } from 'svelte-i18n';
  import { api, ApiError } from '$lib/api';
  import UserSearch from './UserSearch.svelte';

  let { onInvited }: { onInvited: () => void } = $props();

  let opponentId = $state<number | null>(null);
  let boardSize = $state(9);
  let color = $state('black');
  let handicap = $state(0);
  let error = $state('');
  let success = $state('');
  let loading = $state(false);

  const maxHandicap = $derived(boardSize === 9 ? 5 : 9);
  const handicapOptions = $derived([
    0,
    ...Array.from({ length: maxHandicap - 1 }, (_, i) => i + 2),
  ]);

  $effect(() => {
    if (handicap > maxHandicap) handicap = 0;
  });

  async function handleSubmit(e: Event) {
    e.preventDefault();
    if (opponentId === null) return;
    error = '';
    success = '';
    loading = true;
    try {
      await api.sendInvitation({
        to_user_id: opponentId,
        board_size: boardSize,
        mode: 'correspondence',
        time_control_type: 'correspondence',
        time_control_config: { days_per_move: 3 },
        proposed_color: color,
        handicap,
        handicap_placement: 'fixed',
      });
      success = $_('invite.sent');
      onInvited();
    } catch (err) {
      if (err instanceof ApiError) {
        error = (err.body as any)?.message ?? `Error: ${err.status}`;
      } else {
        error = $_('invite.sendFailed');
      }
    } finally {
      loading = false;
    }
  }
</script>

<div class="create-game">
  <h3>{$_('invite.title')}</h3>
  <form onsubmit={handleSubmit}>
    <label>
      {$_('invite.opponent')}
      <UserSearch onSelect={(user) => (opponentId = user.id)} />
    </label>
    <label>
      {$_('invite.boardSize')}
      <select bind:value={boardSize}>
        <option value={9}>9×9</option>
        <option value={13}>13×13</option>
        <option value={19}>19×19</option>
      </select>
    </label>
    <label>
      {$_('invite.color')}
      <select bind:value={color}>
        <option value="black">{$_('invite.colorBlack')}</option>
        <option value="white">{$_('invite.colorWhite')}</option>
        <option value="random">{$_('invite.colorRandom')}</option>
      </select>
    </label>
    <label>
      {$_('invite.handicap')}
      <select bind:value={handicap}>
        {#each handicapOptions as n}
          <option value={n}>{n === 0 ? $_('invite.handicapNone') : n}</option>
        {/each}
      </select>
    </label>
    <p class="hint">{$_('invite.colorHint')}</p>
    {#if error}<p class="error">{error}</p>{/if}
    {#if success}<p class="success">{success}</p>{/if}
    <button type="submit" disabled={loading}>
      {loading ? $_('invite.sending') : $_('invite.send')}
    </button>
  </form>
</div>

<style>
  .create-game {
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
  button[type='submit'] {
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
  button[type='submit']:hover:not(:disabled) {
    background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%);
    transform: translateY(-1px);
  }
  button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
  .error {
    color: #ffcccc;
    font-size: 13px;
    margin: 0;
  }
  .hint {
    margin: 0;
    font-size: 12px;
    color: var(--cream);
    opacity: 0.7;
    font-style: italic;
    line-height: 1.4;
  }
  .success {
    color: #a8d5a2;
    font-size: 13px;
    margin: 0;
  }
</style>
