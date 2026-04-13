<script lang="ts">
  import { _ } from 'svelte-i18n';
  import { api } from '$lib/api';

  let { onSelect }: { onSelect: (user: { id: number; name: string }) => void } = $props();

  let query = $state('');
  let results = $state<{ id: number; name: string }[]>([]);
  let selected = $state<{ id: number; name: string } | null>(null);
  let timer: ReturnType<typeof setTimeout> | null = null;

  function handleInput() {
    selected = null;
    if (timer) clearTimeout(timer);
    if (query.length < 2) {
      results = [];
      return;
    }
    timer = setTimeout(async () => {
      results = await api.searchUsers(query);
    }, 300);
  }

  function pick(user: { id: number; name: string }) {
    selected = user;
    query = user.name;
    results = [];
    onSelect(user);
  }
</script>

<div class="user-search">
  <input
    type="text"
    placeholder={$_('invite.searchPlayer')}
    bind:value={query}
    oninput={handleInput}
    autocomplete="off"
  />
  {#if results.length > 0}
    <ul class="results">
      {#each results as user (user.id)}
        <li>
          <button type="button" onclick={() => pick(user)}>{user.name}</button>
        </li>
      {/each}
    </ul>
  {/if}
</div>

<style>
  .user-search {
    position: relative;
  }
  input {
    width: 100%;
    padding: 10px 14px;
    background: var(--bg-input);
    border: 2px solid var(--border-dim);
    border-radius: 6px;
    color: var(--cream);
    font-family: var(--font-serif);
    font-size: 14px;
    box-sizing: border-box;
    transition: border-color 0.2s;
  }
  input::placeholder {
    color: var(--subtle);
  }
  input:focus {
    outline: none;
    border-color: var(--gold);
  }
  .results {
    position: absolute;
    top: calc(100% + 2px);
    left: 0;
    right: 0;
    margin: 0;
    padding: 0;
    list-style: none;
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card2) 100%);
    border: 2px solid var(--border);
    border-radius: 6px;
    z-index: 20;
    max-height: 200px;
    overflow-y: auto;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.6);
  }
  .results li button {
    width: 100%;
    text-align: left;
    padding: 10px 14px;
    background: none;
    border: none;
    border-bottom: 1px solid rgba(139, 90, 43, 0.2);
    cursor: pointer;
    font-family: var(--font-serif);
    font-size: 14px;
    color: var(--cream);
    transition: background 0.15s;
  }
  .results li:last-child button {
    border-bottom: none;
  }
  .results li button:hover {
    background: rgba(139, 90, 43, 0.2);
    color: var(--gold-light);
  }
</style>
