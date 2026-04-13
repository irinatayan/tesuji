<script lang="ts">
  import { onMount } from 'svelte';
  import { _ } from 'svelte-i18n';
  import { api, ApiError } from '$lib/api';

  let {
    onAccepted,
    refresh = $bindable(0),
  }: { onAccepted: (gameId: number) => void; refresh?: number } = $props();

  type Invitation = {
    id: number;
    from_user: { id: number; name: string };
    board_size: number;
    mode: string;
    time_control_type: string;
  };

  let invitations = $state<Invitation[]>([]);
  let loading = $state(false);

  async function load() {
    loading = true;
    try {
      invitations = await api.getInvitations();
    } finally {
      loading = false;
    }
  }

  async function accept(id: number) {
    try {
      const res = await api.acceptInvitation(id);
      invitations = invitations.filter((i) => i.id !== id);
      onAccepted(res.game_id);
    } catch (err) {
      alert(err instanceof ApiError ? (err.body?.message ?? 'Error') : 'Error');
    }
  }

  async function decline(id: number) {
    try {
      await api.declineInvitation(id);
      invitations = invitations.filter((i) => i.id !== id);
    } catch (err) {
      alert(err instanceof ApiError ? (err.body?.message ?? 'Error') : 'Error');
    }
  }

  onMount(load);

  $effect(() => {
    if (refresh > 0) load();
  });
</script>

{#if invitations.length > 0}
  <div class="invitations">
    <h3>{$_('invitations.incoming', { values: { count: invitations.length } })}</h3>
    {#each invitations as inv (inv.id)}
      <div class="invitation">
        <span>
          <strong>{inv.from_user.name}</strong>
          — {inv.board_size}×{inv.board_size}
          ({inv.mode})
        </span>
        <div class="actions">
          <button onclick={() => accept(inv.id)} class="accept">{$_('invitations.accept')}</button>
          <button onclick={() => decline(inv.id)} class="decline"
            >{$_('invitations.decline')}</button
          >
        </div>
      </div>
    {/each}
  </div>
{/if}

<style>
  .invitations {
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
  .invitation {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 0;
    border-top: 1px solid rgba(139, 90, 43, 0.2);
    font-size: 14px;
    color: var(--cream);
  }
  .invitation strong {
    color: var(--gold);
  }
  .actions {
    display: flex;
    gap: 8px;
  }
  .accept {
    padding: 6px 16px;
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: var(--bg-dark);
    border: 1px solid var(--cream);
    border-radius: 4px;
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    transition: all 0.2s;
  }
  .accept:hover {
    background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%);
  }
  .decline {
    padding: 6px 16px;
    background: transparent;
    color: #e07070;
    border: 1px solid rgba(200, 100, 100, 0.4);
    border-radius: 4px;
    cursor: pointer;
    font-family: var(--font-serif);
    font-size: 12px;
    transition: all 0.2s;
  }
  .decline:hover {
    border-color: #e07070;
    background: rgba(200, 100, 100, 0.1);
  }
</style>
