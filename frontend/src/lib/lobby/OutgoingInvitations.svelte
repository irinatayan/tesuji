<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '$lib/api';

  type OutgoingInvitation = {
    id: number;
    to_user: { id: number; name: string };
    board_size: number;
    mode: string;
    status: string;
  };

  let invitations = $state<OutgoingInvitation[]>([]);

  async function load() {
    try {
      invitations = await api.getOutgoingInvitations();
    } catch {
      // ignore
    }
  }

  onMount(load);
</script>

{#if invitations.length > 0}
  <div class="outgoing">
    <h3>Waiting for response</h3>
    {#each invitations as inv (inv.id)}
      <div class="invitation">
        <span>
          Sent to <strong>{inv.to_user.name}</strong>
          — {inv.board_size}×{inv.board_size}
          ({inv.mode})
        </span>
        <span class="status">pending</span>
      </div>
    {/each}
  </div>
{/if}

<style>
  .outgoing {
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card2) 100%);
    border: 2px solid var(--border-dim);
    border-radius: 8px;
    padding: 20px 24px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.5);
  }
  h3 {
    margin: 0 0 16px;
    font-family: var(--font-display);
    font-size: 14px;
    font-weight: 600;
    color: var(--muted);
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
    border-top: 1px solid rgba(139,90,43,0.15);
    font-size: 14px;
    color: var(--cream);
  }
  .invitation strong { color: var(--gold); }
  .status {
    font-size: 12px;
    color: var(--muted);
    font-style: italic;
  }
</style>
