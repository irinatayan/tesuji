<script lang="ts">
  import { _ } from 'svelte-i18n';
  import { api, ApiError } from '$lib/api';
  import { setToken } from '$lib/stores/auth.svelte';

  let { onSuccess }: { onSuccess: () => void } = $props();

  let name = $state('');
  let email = $state('');
  let password = $state('');
  let error = $state('');
  let loading = $state(false);

  async function handleSubmit(e: Event) {
    e.preventDefault();
    error = '';
    loading = true;
    try {
      const { token } = await api.register(name, email, password);
      setToken(token);
      onSuccess();
    } catch (err) {
      error =
        err instanceof ApiError && err.status === 422
          ? $_('auth.checkFields')
          : $_('auth.registerFailed');
    } finally {
      loading = false;
    }
  }
</script>

<div class="auth-card">
  <div class="card-ornament top"></div>
  <div class="stone-icon">⚪</div>
  <h2>{$_('auth.register')}</h2>
  <p class="subtitle">{$_('auth.registerSubtitle')}</p>

  {#if error}
    <div class="error-box"><span>⚠</span> {error}</div>
  {/if}

  <form onsubmit={handleSubmit}>
    <div class="field">
      <label for="name">{$_('auth.name')}</label>
      <input id="name" type="text" bind:value={name} required disabled={loading} placeholder={$_('auth.namePlaceholder')} />
    </div>
    <div class="field">
      <label for="email">{$_('auth.email')}</label>
      <input id="email" type="email" bind:value={email} required disabled={loading} placeholder={$_('auth.emailPlaceholder')} />
    </div>
    <div class="field">
      <label for="password">{$_('auth.password')}</label>
      <input id="password" type="password" bind:value={password} minlength="8" required disabled={loading} placeholder={$_('auth.passwordPlaceholder')} />
    </div>
    <button type="submit" class="btn-submit" disabled={loading}>
      {loading ? $_('auth.registering') : $_('auth.register')}
    </button>
  </form>
  <div class="card-ornament bottom"></div>
</div>

<style>
  .auth-card {
    position: relative;
    z-index: 1;
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card2) 100%);
    border: 2px solid var(--border);
    border-radius: 10px;
    padding: 48px 52px;
    width: 100%;
    max-width: 420px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.7), inset 0 1px 0 rgba(212,165,116,0.2);
    display: flex;
    flex-direction: column;
    gap: 0;
    box-sizing: border-box;
  }
  .card-ornament {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    width: 120px;
    height: 2px;
    background: linear-gradient(90deg, transparent 0%, var(--gold) 50%, transparent 100%);
  }
  .card-ornament.top  { top: 16px; }
  .card-ornament.bottom { bottom: 16px; }
  .stone-icon {
    font-size: 48px;
    text-align: center;
    filter: drop-shadow(0 3px 6px rgba(0,0,0,0.8));
    margin-bottom: 12px;
  }
  h2 {
    margin: 0 0 4px;
    text-align: center;
    font-family: var(--font-display);
    font-size: 28px;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: 2px;
  }
  .subtitle {
    margin: 0 0 28px;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
    letter-spacing: 1px;
  }
  .error-box {
    background: rgba(139,0,0,0.25);
    border: 1px solid rgba(200,100,100,0.5);
    border-radius: 6px;
    color: #ffcccc;
    padding: 12px 16px;
    font-size: 14px;
    margin-bottom: 16px;
    display: flex;
    gap: 8px;
    align-items: center;
  }
  form { display: flex; flex-direction: column; gap: 16px; }
  .field { display: flex; flex-direction: column; gap: 6px; }
  label { color: var(--gold); font-size: 13px; font-weight: 600; letter-spacing: 0.5px; }
  input {
    width: 100%;
    padding: 12px 16px;
    background: var(--bg-input);
    border: 2px solid var(--border-dim);
    border-radius: 6px;
    color: var(--cream);
    font-family: var(--font-serif);
    font-size: 15px;
    box-sizing: border-box;
    transition: border-color 0.2s, box-shadow 0.2s;
  }
  input::placeholder { color: var(--subtle); }
  input:focus { outline: none; border-color: var(--gold); box-shadow: 0 0 0 3px rgba(212,165,116,0.1); }
  input:disabled { opacity: 0.5; cursor: not-allowed; }
  .btn-submit {
    width: 100%;
    padding: 14px;
    margin-top: 4px;
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: var(--bg-dark);
    border: 2px solid var(--cream);
    border-radius: 6px;
    cursor: pointer;
    font-family: var(--font-display);
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
  }
  .btn-submit:hover:not(:disabled) {
    background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%);
    transform: translateY(-1px);
  }
  .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
