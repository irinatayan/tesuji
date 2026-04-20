<script lang="ts">
  import { onMount } from 'svelte';
  import { _ } from 'svelte-i18n';
  import { api } from '$lib/api';
  import { auth } from '$lib/stores/auth.svelte';
  import OnlineDot from '$lib/ui/OnlineDot.svelte';

  let {
    userId,
    onBack,
    onOpenGame,
  }: { userId: number; onBack: () => void; onOpenGame: (id: number) => void } = $props();

  type Profile = {
    id: number;
    name: string;
    created_at: string;
    stats: { total: number; wins: number; losses: number; win_rate: number };
    telegram_connected?: boolean;
  };

  type GameEntry = {
    id: number;
    mode: string;
    board_size: number;
    result: string;
    started_at: string;
    finished_at: string;
    black_player: { id: number; name: string };
    white_player: { id: number; name: string };
  };

  let profile = $state<Profile | null>(null);
  let games = $state<GameEntry[]>([]);
  let currentPage = $state(1);
  let lastPage = $state(1);
  let loading = $state(true);
  let gamesLoading = $state(false);
  let telegramLoading = $state(false);

  const isOwnProfile = $derived(auth.user?.id === userId);

  async function loadProfile() {
    profile = isOwnProfile ? await api.getMyProfile() : await api.getUserProfile(userId);
    loading = false;
  }

  async function connectTelegram() {
    telegramLoading = true;
    try {
      const { url } = await api.telegramPair();
      window.open(url, '_blank');
    } finally {
      telegramLoading = false;
    }
  }

  async function disconnectTelegram() {
    telegramLoading = true;
    try {
      await api.telegramUnlink();
      if (profile) profile = { ...profile, telegram_connected: false };
    } finally {
      telegramLoading = false;
    }
  }

  async function loadGames(page = 1) {
    gamesLoading = true;
    const res = await api.getUserGames(userId, page);
    games = res.data;
    currentPage = res.current_page;
    lastPage = res.last_page;
    gamesLoading = false;
  }

  onMount(async () => {
    await loadProfile();
    await loadGames();
  });

  function opponent(game: GameEntry): string {
    if (!profile) return '';
    return game.black_player.id === profile.id ? game.white_player.name : game.black_player.name;
  }

  function playerColor(game: GameEntry): string {
    if (!profile) return '';
    return game.black_player.id === profile.id
      ? $_('profile.colorBlack')
      : $_('profile.colorWhite');
  }

  function won(game: GameEntry): boolean {
    if (!profile) return false;
    const r = game.result ?? '';
    return (
      (game.black_player.id === profile.id && r.startsWith('B+')) ||
      (game.white_player.id === profile.id && r.startsWith('W+'))
    );
  }
</script>

<div class="profile">
  <button class="back" onclick={onBack}>{$_('app.back')}</button>

  {#if loading}
    <p>{$_('app.loading')}</p>
  {:else if profile}
    <h2 class="profile-name">
      {profile.name}
      <OnlineDot userId={profile.id} />
    </h2>

    {#if isOwnProfile}
      <div class="telegram-section">
        {#if profile.telegram_connected}
          <span class="telegram-status connected">Telegram connected</span>
          <button class="btn-unlink" onclick={disconnectTelegram} disabled={telegramLoading}>
            Disconnect
          </button>
        {:else}
          <span class="telegram-status">Telegram not connected</span>
          <button class="btn-connect" onclick={connectTelegram} disabled={telegramLoading}>
            Connect Telegram
          </button>
        {/if}
      </div>
    {/if}

    <div class="stats">
      <div class="stat">
        <span class="label">{$_('profile.games')}</span><span>{profile.stats.total}</span>
      </div>
      <div class="stat">
        <span class="label">{$_('profile.wins')}</span><span>{profile.stats.wins}</span>
      </div>
      <div class="stat">
        <span class="label">{$_('profile.losses')}</span><span>{profile.stats.losses}</span>
      </div>
      <div class="stat">
        <span class="label">{$_('profile.winRate')}</span><span>{profile.stats.win_rate}%</span>
      </div>
    </div>

    <h3>{$_('profile.history')}</h3>

    {#if gamesLoading}
      <p>{$_('profile.loadingGames')}</p>
    {:else if games.length === 0}
      <p class="empty">{$_('profile.noGames')}</p>
    {:else}
      <table>
        <thead>
          <tr>
            <th>{$_('profile.opponent')}</th>
            <th>{$_('profile.color')}</th>
            <th>{$_('profile.board')}</th>
            <th>{$_('profile.result')}</th>
            <th>{$_('profile.outcome')}</th>
          </tr>
        </thead>
        <tbody>
          {#each games as game (game.id)}
            <tr class="{won(game) ? 'win' : 'loss'} clickable" onclick={() => onOpenGame(game.id)}>
              <td>{opponent(game)}</td>
              <td>{playerColor(game)}</td>
              <td>{game.board_size}×{game.board_size}</td>
              <td>{game.result}</td>
              <td>{won(game) ? $_('profile.win') : $_('profile.loss')}</td>
            </tr>
          {/each}
        </tbody>
      </table>

      {#if lastPage > 1}
        <div class="pagination">
          <button disabled={currentPage === 1} onclick={() => loadGames(currentPage - 1)}>‹</button>
          <span>{currentPage} / {lastPage}</span>
          <button disabled={currentPage === lastPage} onclick={() => loadGames(currentPage + 1)}
            >›</button
          >
        </div>
      {/if}
    {/if}
  {/if}
</div>

<style>
  .profile {
    max-width: 600px;
  }
  .back {
    background: none;
    border: none;
    cursor: pointer;
    color: #555;
    font-size: 14px;
    padding: 0;
    margin-bottom: 16px;
  }
  h2 {
    margin: 0 0 16px;
  }
  .profile-name {
    display: flex;
    align-items: center;
    gap: 10px;
  }
  .telegram-section {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding: 12px 16px;
    background: rgba(139, 90, 43, 0.1);
    border: 1px solid var(--border-dim);
    border-radius: 6px;
  }
  .telegram-status {
    font-size: 14px;
    color: var(--muted);
    flex: 1;
  }
  .telegram-status.connected {
    color: #4caf50;
  }
  .btn-connect {
    padding: 7px 16px;
    background: #2196f3;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-family: var(--font-serif);
    transition: background 0.2s;
  }
  .btn-connect:hover:not(:disabled) {
    background: #1976d2;
  }
  .btn-connect:disabled {
    opacity: 0.6;
    cursor: default;
  }
  .btn-unlink {
    padding: 7px 16px;
    background: none;
    color: var(--muted);
    border: 1px solid var(--border-dim);
    border-radius: 4px;
    cursor: pointer;
    font-size: 13px;
    font-family: var(--font-serif);
    transition: all 0.2s;
  }
  .btn-unlink:hover:not(:disabled) {
    color: #c00;
    border-color: #c00;
  }
  .btn-unlink:disabled {
    opacity: 0.6;
    cursor: default;
  }
  h3 {
    margin: 24px 0 12px;
    font-size: 16px;
  }
  .stats {
    display: flex;
    gap: 24px;
  }
  .stat {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
  }
  .label {
    font-size: 12px;
    color: #888;
    text-transform: uppercase;
  }
  table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
  }
  th {
    text-align: left;
    padding: 6px 8px;
    border-bottom: 2px solid #eee;
    font-size: 12px;
    text-transform: uppercase;
    color: #888;
  }
  td {
    padding: 8px;
    border-bottom: 1px solid #f0f0f0;
  }
  tr.win td:last-child {
    color: #1a7a1a;
    font-weight: 600;
  }
  tr.loss td:last-child {
    color: #c00;
  }
  tr.clickable {
    cursor: pointer;
    transition: background 0.15s;
  }
  tr.clickable:hover {
    background: rgba(0, 0, 0, 0.04);
  }
  .pagination {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 12px;
    font-size: 14px;
  }
  .pagination button {
    padding: 4px 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    background: #fff;
    cursor: pointer;
  }
  .pagination button:disabled {
    opacity: 0.4;
    cursor: default;
  }
  .empty {
    color: #888;
    font-size: 14px;
  }
</style>
