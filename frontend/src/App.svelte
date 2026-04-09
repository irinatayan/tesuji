<script lang="ts">
  import { onMount } from 'svelte';
  import { api } from '$lib/api';
  import { auth, setToken, clearAuth } from '$lib/stores/auth';
  import LoginView from '$lib/auth/LoginView.svelte';
  import RegisterView from '$lib/auth/RegisterView.svelte';
  import OAuthCallback from '$lib/auth/OAuthCallback.svelte';

  type View = 'loading' | 'oauth-callback' | 'auth' | 'register' | 'lobby' | 'game';

  let view = $state<View>('loading');
  let activeGameId = $state<number | null>(null);

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
    <p>Привет, {auth.user?.name}!</p>
    <button
      onclick={() => {
        clearAuth();
        view = 'auth';
      }}>Выйти</button
    >
    <p><em>Лобби — Шаг 13.5</em></p>
  {/if}
</main>
