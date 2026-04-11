<script lang="ts">
  import { toasts, dismissToast, type Toast } from './toasts.svelte';
</script>

{#if toasts.items.length > 0}
  <div class="toast-container">
    {#each toasts.items as toast (toast.id)}
      <div class="toast" class:toast-info={toast.type === 'info'} class:toast-invite={toast.type === 'invite'}>
        <div class="toast-body">
          <p class="toast-msg">{toast.message}</p>
          {#if toast.actions}
            <div class="toast-actions">
              {#each toast.actions as action}
                <button
                  class="toast-btn"
                  class:toast-btn-primary={action.style === 'primary'}
                  class:toast-btn-danger={action.style === 'danger'}
                  onclick={() => { action.handler(); dismissToast(toast.id); }}
                >{action.label}</button>
              {/each}
            </div>
          {/if}
        </div>
        <button class="toast-close" onclick={() => dismissToast(toast.id)}>✕</button>
      </div>
    {/each}
  </div>
{/if}

<style>
  .toast-container {
    position: fixed;
    top: 16px;
    right: 16px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 380px;
  }

  .toast {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card2) 100%);
    border: 2px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 8px 30px rgba(0,0,0,0.6);
    animation: slideIn 0.3s ease-out;
  }

  .toast-invite {
    border-color: var(--gold);
  }

  .toast-body {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .toast-msg {
    margin: 0;
    font-size: 14px;
    color: var(--cream);
    line-height: 1.4;
  }

  .toast-actions {
    display: flex;
    gap: 8px;
  }

  .toast-btn {
    padding: 5px 14px;
    border-radius: 4px;
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    transition: all 0.2s;
  }

  .toast-btn-primary {
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: var(--bg-dark);
    border: 1px solid var(--cream);
  }
  .toast-btn-primary:hover {
    background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%);
  }

  .toast-btn-danger {
    background: transparent;
    color: #e07070;
    border: 1px solid rgba(200,100,100,0.4);
  }
  .toast-btn-danger:hover {
    border-color: #e07070;
    background: rgba(200,100,100,0.1);
  }

  .toast-close {
    background: none;
    border: none;
    color: var(--muted);
    cursor: pointer;
    font-size: 14px;
    padding: 0;
    line-height: 1;
    transition: color 0.2s;
  }
  .toast-close:hover {
    color: var(--cream);
  }

  @keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
  }
</style>
