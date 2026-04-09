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

<div class="auth-form">
  <h2>Sign in</h2>

  <form onsubmit={handleSubmit}>
    <label>
      Email
      <input type="email" bind:value={email} required />
    </label>
    <label>
      Password
      <input type="password" bind:value={password} required />
    </label>
    {#if error}<p class="error">{error}</p>{/if}
    <button type="submit" disabled={loading}>
      {loading ? 'Signing in...' : 'Sign in'}
    </button>
  </form>

  <a href={GOOGLE_URL} class="google-btn">Sign in with Google</a>
</div>

<style>
  .auth-form {
    display: flex;
    flex-direction: column;
    gap: 16px;
    max-width: 320px;
  }
  form {
    display: flex;
    flex-direction: column;
    gap: 12px;
  }
  label {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 14px;
  }
  input {
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 16px;
  }
  button {
    padding: 10px;
    background: #1a1a1a;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
  }
  button:disabled {
    opacity: 0.6;
  }
  .google-btn {
    display: block;
    padding: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
    text-align: center;
    text-decoration: none;
    color: #333;
  }
  .error {
    color: #c00;
    font-size: 14px;
    margin: 0;
  }
</style>
