<script lang="ts">
  import { api, ApiError } from '$lib/api';
  import { setToken } from '$lib/stores/auth.svelte';

  let { onSuccess }: { onSuccess: () => void } = $props();

  let email = $state('');
  let password = $state('');
  let error = $state('');
  let loading = $state(false);

  const GOOGLE_URL = `${import.meta.env.VITE_API_URL}/api/auth/google`;

  async function handleSubmit(e: Event) {
    e.preventDefault();
    error = '';
    loading = true;
    try {
      const { token } = await api.login(email, password);
      setToken(token);
      onSuccess();
    } catch (err) {
      error =
        err instanceof ApiError && err.status === 422
          ? 'Invalid email or password'
          : 'Login failed';
    } finally {
      loading = false;
    }
  }
</script>

<div class="auth-card">
  <div class="card-ornament top"></div>
  <div class="stone-icon">⚫</div>
  <h2>Sign In</h2>
  <p class="subtitle">Enter the Way of Stones</p>

  {#if error}
    <div class="error-box"><span>⚠</span> {error}</div>
  {/if}

  <form onsubmit={handleSubmit}>
    <div class="field">
      <label for="email">Email</label>
      <input id="email" type="email" bind:value={email} required disabled={loading} placeholder="your@email.com" />
    </div>
    <div class="field">
      <label for="password">Password</label>
      <input id="password" type="password" bind:value={password} required disabled={loading} placeholder="••••••••" />
    </div>
    <button type="submit" class="btn-submit" disabled={loading}>
      {loading ? 'Signing in…' : 'Sign In'}
    </button>
  </form>

  <div class="divider"><span>or</span></div>
  <a href={GOOGLE_URL} class="google-btn">Continue with Google</a>
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
  form {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  .field {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  label {
    color: var(--gold);
    font-size: 13px;
    font-weight: 600;
    letter-spacing: 0.5px;
  }
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
  input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(212,165,116,0.1);
  }
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
  .divider {
    margin: 24px 0 16px;
    text-align: center;
    position: relative;
    height: 1px;
    background: linear-gradient(90deg, transparent, var(--border-dim), transparent);
  }
  .divider span {
    position: relative;
    top: -10px;
    background: linear-gradient(135deg, var(--bg-card) 0%, var(--bg-card2) 100%);
    padding: 0 16px;
    color: var(--muted);
    font-size: 12px;
    letter-spacing: 1px;
    text-transform: uppercase;
  }
  .google-btn {
    display: block;
    padding: 12px;
    background: transparent;
    border: 2px solid var(--border-dim);
    border-radius: 6px;
    text-align: center;
    text-decoration: none;
    color: var(--cream);
    font-family: var(--font-serif);
    font-size: 14px;
    transition: all 0.2s;
  }
  .google-btn:hover {
    border-color: var(--border);
    background: rgba(139,90,43,0.15);
  }
</style>
