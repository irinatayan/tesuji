<script lang="ts">
  import { onMount } from 'svelte';
  import { _, locale, isLoading } from 'svelte-i18n';
  import { api, ApiError } from '$lib/api';
  import { auth, clearAuth } from '$lib/stores/auth.svelte';
  import { resetEcho } from '$lib/echo';
  import LoginView from '$lib/auth/LoginView.svelte';
  import OAuthCallback from '$lib/auth/OAuthCallback.svelte';
  import CreateGameView from '$lib/lobby/CreateGameView.svelte';
  import PlayVsBotView from '$lib/lobby/PlayVsBotView.svelte';
  import GameList from '$lib/lobby/GameList.svelte';
  import LiveGames from '$lib/lobby/LiveGames.svelte';
  import InvitationList from '$lib/lobby/InvitationList.svelte';
  import OutgoingInvitations from '$lib/lobby/OutgoingInvitations.svelte';
  import ProfileView from '$lib/profile/ProfileView.svelte';
  import GameRealtime from '$lib/board/GameRealtime.svelte';
  import ToastContainer from '$lib/notifications/ToastContainer.svelte';
  import { addToast } from '$lib/notifications/toasts.svelte';
  import {
    invitationStore,
    loadIncoming,
    removeIncoming,
  } from '$lib/notifications/invitations.svelte';
  import { getEcho } from '$lib/echo';
  import { router, navigate, initRouter, routeToPath, type Route } from '$lib/router.svelte';

  let showCreateForm = $state(false);
  let showBotForm = $state(false);
  let invitationRefresh = $state(0);
  let outgoingRefresh = $state(0);
  let gamesRefresh = $state(0);

  const gameId = $derived(router.current.name === 'game' ? router.current.id : null);
  const profileUserId = $derived(router.current.name === 'profile' ? router.current.userId : null);

  onMount(async () => {
    const initial = initRouter();

    if (initial.name === 'oauth-callback') {
      router.current = initial;
      return;
    }

    if (!auth.token) {
      saveRedirect(initial);
      navigate({ name: 'auth' });
      return;
    }

    try {
      auth.user = await api.me();
      navigate(initial.name === 'auth' ? { name: 'lobby' } : initial);
    } catch {
      clearAuth();
      saveRedirect(initial);
      navigate({ name: 'auth' });
    }
  });

  function saveRedirect(route: Route) {
    if (route.name !== 'auth' && route.name !== 'lobby' && route.name !== 'loading') {
      sessionStorage.setItem('redirectAfter', JSON.stringify(route));
    }
  }

  async function afterLogin() {
    auth.user = await api.me();
    const redirectJson = sessionStorage.getItem('redirectAfter');
    sessionStorage.removeItem('redirectAfter');
    const redirect: Route = redirectJson ? JSON.parse(redirectJson) : { name: 'lobby' };
    navigate(redirect);
  }

  function openGame(id: number) {
    showCreateForm = false;
    navigate({ name: 'game', id });
  }

  function openProfile(userId: number) {
    navigate({ name: 'profile', userId });
  }

  function logout() {
    clearAuth();
    resetEcho();
    navigate({ name: 'auth' });
  }

  async function handleAcceptFromToast(invId: number) {
    try {
      const res = await api.acceptInvitation(invId);
      removeIncoming(invId);
      invitationRefresh++;
      openGame(res.game_id);
    } catch (err) {
      const msg = err instanceof ApiError ? ((err.body as any)?.message ?? 'Error') : 'Error';
      addToast({ type: 'info', message: msg });
    }
  }

  async function handleDeclineFromToast(invId: number) {
    try {
      await api.declineInvitation(invId);
      removeIncoming(invId);
      invitationRefresh++;
    } catch {
      // ignore
    }
  }

  $effect(() => {
    const user = auth.user;
    if (!user) return;

    loadIncoming();

    const channel = getEcho().private(`user.${user.id}`);

    channel
      .listen('.invitation.received', (e: any) => {
        loadIncoming();
        invitationRefresh++;
        const from = e.from?.name ?? 'Someone';
        const size = e.boardSize ?? '?';
        const invId = e.invitationId;
        addToast({
          type: 'invite',
          message: $_('toast.inviteReceived', { values: { name: from, size } }),
          actions: [
            {
              label: $_('invitations.accept'),
              style: 'primary',
              handler: () => handleAcceptFromToast(invId),
            },
            {
              label: $_('invitations.decline'),
              style: 'danger',
              handler: () => handleDeclineFromToast(invId),
            },
          ],
        });
      })
      .listen('.invitation.accepted', (e: { game_id: number }) => {
        outgoingRefresh++;
        gamesRefresh++;
        addToast({
          type: 'info',
          message: $_('toast.inviteAccepted'),
          actions: [
            { label: $_('toast.open'), style: 'primary', handler: () => openGame(e.game_id) },
          ],
        });
      });

    return () => {
      getEcho().leave(`user.${user.id}`);
    };
  });
</script>

<div class="app">
  <div class="wood-grain"></div>

  {#if $isLoading || router.current.name === 'loading'}
    <div class="splash"><span class="splash-title">TESUJI</span></div>
  {:else if router.current.name === 'oauth-callback'}
    <OAuthCallback onSuccess={afterLogin} onFail={() => navigate({ name: 'auth' })} />
  {:else if router.current.name === 'auth'}
    <div class="auth-wrap">
      <div class="board-pattern"></div>
      <div class="auth-brand">
        <span class="brand-title">{$_('app.title')}</span>
        <span class="brand-sub">{$_('app.subtitle')}</span>
      </div>
      <LoginView />
    </div>
  {:else if router.current.name === 'lobby'}
    <header class="site-header">
      <span class="site-title">{$_('app.title')}</span>
      <nav>
        <select
          class="lang-select"
          value={$locale}
          onchange={(e) => {
            const v = (e.target as HTMLSelectElement).value;
            locale.set(v);
            localStorage.setItem('locale', v);
          }}
        >
          <option value="en">EN</option>
          <option value="uk">UK</option>
          <option value="ru">RU</option>
        </select>
        <button class="user-btn" onclick={() => auth.user && openProfile(auth.user.id)}>
          ⚫ {auth.user?.name}
        </button>
        <button class="btn-outline" onclick={logout}>{$_('app.signOut')}</button>
      </nav>
    </header>

    <main class="lobby">
      <InvitationList onAccepted={openGame} bind:refresh={invitationRefresh} />
      <OutgoingInvitations bind:refresh={outgoingRefresh} />
      <GameList onSelect={openGame} bind:refresh={gamesRefresh} />
      <LiveGames onSelect={openGame} />

      <div class="create-section">
        {#if !showCreateForm && !showBotForm}
          <div class="lobby-buttons">
            <button class="btn-primary" onclick={() => (showCreateForm = true)}>
              {$_('lobby.newGame')}
            </button>
            <button class="btn-bot" onclick={() => (showBotForm = true)}>
              {$_('lobby.playBot')}
            </button>
          </div>
        {:else if showBotForm}
          <PlayVsBotView
            onCreated={(id) => {
              showBotForm = false;
              openGame(id);
            }}
          />
          <button class="btn-ghost" onclick={() => (showBotForm = false)}
            >{$_('lobby.cancel')}</button
          >
        {:else}
          <CreateGameView
            onInvited={() => {
              showCreateForm = false;
              outgoingRefresh++;
            }}
          />
          <button class="btn-ghost" onclick={() => (showCreateForm = false)}
            >{$_('lobby.cancel')}</button
          >
        {/if}
      </div>
    </main>
  {:else if router.current.name === 'game' && gameId !== null}
    <GameRealtime {gameId} onLeave={() => navigate({ name: 'lobby' })} />
  {:else if router.current.name === 'profile' && profileUserId !== null}
    <header class="site-header">
      <span class="site-title">{$_('app.title')}</span>
      <nav>
        {#if invitationStore.incoming.length > 0}
          <button class="badge-btn" onclick={() => navigate({ name: 'lobby' })}>
            ✉ <span class="badge">{invitationStore.incoming.length}</span>
          </button>
        {/if}
        <button class="btn-outline" onclick={() => navigate({ name: 'lobby' })}
          >← {$_('app.lobby')}</button
        >
        <button class="btn-outline" onclick={logout}>{$_('app.signOut')}</button>
      </nav>
    </header>
    <main class="lobby">
      <ProfileView userId={profileUserId} onBack={() => navigate({ name: 'lobby' })} />
    </main>
  {/if}
</div>

<ToastContainer />

<style>
  .app {
    min-height: 100vh;
    position: relative;
  }

  .wood-grain {
    position: fixed;
    inset: 0;
    background:
      repeating-linear-gradient(
        90deg,
        rgba(139, 90, 43, 0.025) 0px,
        rgba(160, 102, 50, 0.025) 2px,
        rgba(139, 90, 43, 0.025) 4px
      ),
      linear-gradient(
        180deg,
        rgba(101, 67, 33, 0.35) 0%,
        rgba(139, 90, 43, 0.25) 50%,
        rgba(101, 67, 33, 0.35) 100%
      );
    pointer-events: none;
    z-index: 0;
  }

  /* ── Auth layout ─────────────────────────────── */
  .auth-wrap {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    gap: 12px;
  }

  .board-pattern {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 600px;
    height: 600px;
    background:
      linear-gradient(
        0deg,
        transparent 49%,
        rgba(139, 90, 43, 0.08) 49%,
        rgba(139, 90, 43, 0.08) 51%,
        transparent 51%
      ),
      linear-gradient(
        90deg,
        transparent 49%,
        rgba(139, 90, 43, 0.08) 49%,
        rgba(139, 90, 43, 0.08) 51%,
        transparent 51%
      );
    background-size: 55px 55px;
    pointer-events: none;
    z-index: 0;
  }

  .auth-brand {
    text-align: center;
    margin-bottom: 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
  }

  .brand-title {
    font-family: var(--font-display);
    font-size: 52px;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: 10px;
    text-shadow: 2px 2px 8px rgba(0, 0, 0, 0.8);
  }

  .brand-sub {
    font-family: var(--font-display);
    font-size: 13px;
    color: var(--muted);
    letter-spacing: 4px;
    text-transform: uppercase;
  }

  /* ── Splash ──────────────────────────────────── */
  .splash {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .splash-title {
    font-family: var(--font-display);
    font-size: 64px;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: 12px;
    text-shadow: 2px 2px 12px rgba(0, 0, 0, 0.9);
  }

  /* ── Site header ─────────────────────────────── */
  .site-header {
    position: relative;
    z-index: 10;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 32px;
    background: linear-gradient(180deg, rgba(20, 12, 8, 0.95) 0%, rgba(30, 18, 10, 0.9) 100%);
    border-bottom: 2px solid var(--border);
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5);
  }

  .site-title {
    font-family: var(--font-display);
    font-size: 28px;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: 6px;
    text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.8);
  }

  nav {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .lang-select {
    background: rgba(20, 13, 8, 0.6);
    border: 1px solid var(--border-dim);
    border-radius: 4px;
    color: var(--gold);
    font-family: var(--font-display);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1px;
    padding: 5px 8px;
    cursor: pointer;
  }
  .lang-select:focus {
    outline: none;
    border-color: var(--gold);
  }
  .lang-select option {
    background: #2c1810;
    color: var(--cream);
  }

  .badge-btn {
    position: relative;
    background: none;
    border: 2px solid var(--gold);
    border-radius: 4px;
    color: var(--gold);
    font-size: 16px;
    cursor: pointer;
    padding: 5px 12px;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .badge-btn:hover {
    background: rgba(139, 90, 43, 0.2);
  }
  .badge {
    background: #c0392b;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 10px;
    font-family: var(--font-display);
    line-height: 1.4;
  }

  .user-btn {
    background: none;
    border: none;
    color: var(--gold);
    font-family: var(--font-serif);
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    padding: 6px 0;
    transition: color 0.2s;
  }
  .user-btn:hover {
    color: var(--gold-light);
  }

  .btn-outline {
    padding: 8px 20px;
    background: transparent;
    color: var(--gold);
    border: 2px solid var(--border);
    border-radius: 4px;
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 13px;
    letter-spacing: 1px;
    transition: all 0.2s;
  }
  .btn-outline:hover {
    background: rgba(139, 90, 43, 0.2);
    border-color: var(--gold);
  }

  /* ── Lobby ───────────────────────────────────── */
  .lobby {
    position: relative;
    z-index: 1;
    max-width: 680px;
    margin: 0 auto;
    padding: 32px 24px;
    display: flex;
    flex-direction: column;
    gap: 24px;
  }

  .create-section {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .btn-primary {
    padding: 14px 32px;
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: var(--bg-dark);
    border: 2px solid var(--cream);
    border-radius: 4px;
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    align-self: flex-start;
  }
  .btn-primary:hover {
    background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(212, 165, 116, 0.3);
  }

  .btn-ghost {
    padding: 8px 20px;
    background: transparent;
    color: var(--muted);
    border: 1px solid var(--border-dim);
    border-radius: 4px;
    cursor: pointer;
    font-family: var(--font-serif);
    font-size: 14px;
    transition: all 0.2s;
    align-self: flex-start;
  }
  .btn-ghost:hover {
    color: var(--cream);
    border-color: var(--border);
  }

  .lobby-buttons {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
  }

  .btn-bot {
    padding: 14px 32px;
    background: linear-gradient(135deg, #5a7a5a 0%, #3d5c3d 100%);
    color: var(--cream);
    border: 2px solid #7a9a7a;
    border-radius: 4px;
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
  }
  .btn-bot:hover {
    background: linear-gradient(135deg, #6a8a6a 0%, #4d6c4d 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(90, 122, 90, 0.3);
  }
</style>
