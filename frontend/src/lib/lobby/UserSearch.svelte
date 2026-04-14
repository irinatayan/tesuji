<script lang="ts">
  import { _ } from 'svelte-i18n';
  import { api } from '$lib/api';
  import { tick } from 'svelte';

  let { onSelect }: { onSelect: (user: { id: number; name: string }) => void } = $props();

  type UserItem = { id: number; name: string };

  let query = $state('');
  let users = $state<UserItem[]>([]);
  let selected = $state<UserItem | null>(null);
  let open = $state(false);
  let page = $state(1);
  let lastPage = $state(1);
  let loading = $state(false);
  let rootEl: HTMLDivElement | undefined = $state();
  let listEl: HTMLUListElement | undefined = $state();
  let debounceTimer: ReturnType<typeof setTimeout> | null = null;
  let seq = 0;

  async function load(reset: boolean) {
    if (loading) return;
    if (!reset && page >= lastPage) return;

    loading = true;
    const nextPage = reset ? 1 : page + 1;
    const mySeq = ++seq;
    try {
      const res = await api.listUsers({ search: query.trim() || undefined, page: nextPage });
      if (mySeq !== seq) return;
      users = reset ? res.data : [...users, ...res.data];
      page = res.meta.current_page;
      lastPage = res.meta.last_page;
    } finally {
      if (mySeq === seq) loading = false;
    }
  }

  function handleInput() {
    selected = null;
    open = true;
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => load(true), 200);
  }

  async function handleFocus() {
    open = true;
    if (users.length === 0) await load(true);
  }

  function handleScroll() {
    if (!listEl || loading || page >= lastPage) return;
    const nearBottom = listEl.scrollTop + listEl.clientHeight >= listEl.scrollHeight - 40;
    if (nearBottom) load(false);
  }

  function pick(user: UserItem) {
    selected = user;
    query = user.name;
    open = false;
    onSelect(user);
  }

  function handleDocClick(e: MouseEvent) {
    if (rootEl && !rootEl.contains(e.target as Node)) open = false;
  }

  function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Escape') open = false;
  }

  $effect(() => {
    document.addEventListener('mousedown', handleDocClick);
    document.addEventListener('keydown', handleKeydown);
    return () => {
      document.removeEventListener('mousedown', handleDocClick);
      document.removeEventListener('keydown', handleKeydown);
    };
  });

  $effect(() => {
    if (open) {
      tick().then(() => listEl?.focus?.());
    }
  });
</script>

<div class="user-search" bind:this={rootEl}>
  <input
    type="text"
    placeholder={$_('invite.searchPlayer')}
    bind:value={query}
    oninput={handleInput}
    onfocus={handleFocus}
    autocomplete="off"
  />
  {#if open}
    <ul class="results" bind:this={listEl} onscroll={handleScroll} role="listbox" tabindex="-1">
      {#if users.length === 0 && !loading}
        <li class="empty">{$_('invite.noUsersFound')}</li>
      {:else}
        {#each users as user (user.id)}
          <li>
            <button type="button" onclick={() => pick(user)}>{user.name}</button>
          </li>
        {/each}
        {#if loading}
          <li class="loading">…</li>
        {/if}
      {/if}
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
    max-height: 240px;
    overflow-y: auto;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.6);
    outline: none;
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
  .empty,
  .loading {
    padding: 10px 14px;
    color: var(--muted);
    font-family: var(--font-serif);
    font-size: 13px;
    font-style: italic;
    text-align: center;
  }
</style>
