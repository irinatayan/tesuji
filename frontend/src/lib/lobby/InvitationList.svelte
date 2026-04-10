<script lang="ts">
  import { onMount } from 'svelte';
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
      alert(err instanceof ApiError ? err.body?.message ?? 'Error' : 'Error');
    }
  }

  async function decline(id: number) {
    try {
      await api.declineInvitation(id);
      invitations = invitations.filter((i) => i.id !== id);
    } catch (err) {
      alert(err instanceof ApiError ? err.body?.message ?? 'Error' : 'Error');
    }
  }

  onMount(load);

  $effect(() => {
    if (refresh > 0) load();
  });
</script>

{#if invitations.length > 0}
  <div class="invitations">
    <h3>Incoming invitations ({invitations.length})</h3>
    {#each invitations as inv (inv.id)}
      <div class="invitation">
        <span>
          <strong>{inv.from_user.name}</strong>
          — {inv.board_size}×{inv.board_size}
          ({inv.mode})
        </span>
        <div class="actions">
          <button onclick={() => accept(inv.id)} class="accept">Accept</button>
          <button onclick={() => decline(inv.id)} class="decline">Decline</button>
        </div>
      </div>
    {/each}
  </div>
{/if}

<style>
  .invitations {
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 12px 16px;
  }
  h3 {
    margin: 0 0 12px;
    font-size: 15px;
  }
  .invitation {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 0;
    border-top: 1px solid #f0f0f0;
    font-size: 14px;
  }
  .actions {
    display: flex;
    gap: 8px;
  }
  .accept {
    padding: 4px 12px;
    background: #1a1a1a;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
  }
  .decline {
    padding: 4px 12px;
    background: #fff;
    color: #c00;
    border: 1px solid #c00;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
  }
</style>
