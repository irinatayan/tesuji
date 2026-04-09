<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '$lib/api';
  import { auth, clearAuth } from '$lib/stores/auth.svelte';
  import { resetEcho } from '$lib/echo';
  import LoginView from '$lib/auth/LoginView.svelte';
  import RegisterView from '$lib/auth/RegisterView.svelte';
  import OAuthCallback from '$lib/auth/OAuthCallback.svelte';
  import CreateGameView from '$lib/lobby/CreateGameView.svelte';
  import GameList from '$lib/lobby/GameList.svelte';
  import GameRealtime from '$lib/board/GameRealtime.svelte';

  type View = 'loading' | 'oauth-callback' | 'auth' | 'register' | 'lobby' | 'game';

  let view = $state<View>('loading');
  let activeGameId = $state<number | null>(null);
  let showCreateForm = $state(false);

  const isOAuthCallback = window.location.search.includes('token=');

  onMount(async () => {
    if (isOAuthCallback) {
      view = 'oauth-callback';
      return;
    }
    if (auth.token) {
      try {
        auth.user = await api.me();
        view = 'lobby';
      } catch {
        clearAuth();
        view = 'auth';
      }
    } else {
      view = 'auth';
    }
  });

  async function afterLogin() {
    auth.user = await api.me();
    view = 'lobby';
  }

  function openGame(gameId: number) {
    activeGameId = gameId;
    showCreateForm = false;
    view = 'game';
  }

  function logout() {
    clearAuth();
    resetEcho();
    view = 'auth';
  }
</script>

<main>
  <h1>Tesuji</h1>

  {#if view === 'loading'}
    <p>Загрузка...</p>
  {:else if view === 'oauth-callback'}
    <OAuthCallback onSuccess={afterLogin} onFail={() => (view = 'auth')} />
  {:else if view === 'auth'}
    <LoginView onSuccess={afterLogin} />
    <button onclick={() => (view = 'register')} style="margin-top:8px">Регистрация</button>
  {:else if view === 'register'}
    <RegisterView onSuccess={afterLogin} />
    <button onclick={() => (view = 'auth')} style="margin-top:8px">Уже есть аккаунт</button>
  {:else if view === 'lobby'}
    <div class="lobby-header">
      <span>👤 {auth.user?.name}</span>
      <button onclick={logout}>Выйти</button>
    </div>

    <div class="lobby">
      <GameList onSelect={openGame} />

      <div>
        {#if !showCreateForm}
          <button onclick={() => (showCreateForm = true)} class="new-game-btn">
            + Новая партия
          </button>
        {:else}
          <CreateGameView
            onCreated={(id) => {
              showCreateForm = false;
              openGame(id);
            }}
          />
          <button onclick={() => (showCreateForm = false)} style="margin-top:8px">Отмена</button>
        {/if}
      </div>
    </div>
  {:else if view === 'game' && activeGameId !== null}
    <GameRealtime gameId={activeGameId} onLeave={() => (view = 'lobby')} />
  {/if}
</main>

<style>
  main {
    padding: 24px;
    max-width: 640px;
  }
  .lobby-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 24px;
    font-size: 15px;
  }
  .lobby-header button {
    padding: 4px 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    cursor: pointer;
    background: #fff;
  }
  .lobby {
    display: flex;
    flex-direction: column;
    gap: 24px;
  }
  .new-game-btn {
    padding: 10px 16px;
    background: #1a1a1a;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 15px;
  }
</style>
