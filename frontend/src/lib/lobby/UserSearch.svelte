<script lang="ts">
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
    placeholder="Search player..."
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
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 15px;
    box-sizing: border-box;
  }
  .results {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    margin: 0;
    padding: 0;
    list-style: none;
    border: 1px solid #ccc;
    border-top: none;
    border-radius: 0 0 4px 4px;
    background: #fff;
    z-index: 10;
    max-height: 200px;
    overflow-y: auto;
  }
  .results li button {
    width: 100%;
    text-align: left;
    padding: 8px 12px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 14px;
  }
  .results li button:hover {
    background: #f5f5f5;
  }
</style>
