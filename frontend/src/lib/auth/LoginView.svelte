<script lang="ts">
  import { _ } from 'svelte-i18n';
  const GOOGLE_URL = `${import.meta.env.VITE_API_URL}/api/auth/google`;
</script>

<div class="auth-card">
  <div class="card-ornament top"></div>
  <div class="stone-icon">⚫⚪</div>
  <h2>{$_('auth.signIn')}</h2>
  <p class="subtitle">{$_('auth.subtitle')}</p>
  <a href={GOOGLE_URL} class="google-btn">{$_('auth.google')}</a>
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
    max-width: 380px;
    box-shadow: 0 20px 60px rgba(0,0,0,0.7), inset 0 1px 0 rgba(212,165,116,0.2);
    display: flex;
    flex-direction: column;
    align-items: center;
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
    font-size: 44px;
    letter-spacing: -4px;
    margin-bottom: 16px;
    filter: drop-shadow(0 3px 6px rgba(0,0,0,0.8));
  }
  h2 {
    margin: 0 0 6px;
    font-family: var(--font-display);
    font-size: 28px;
    font-weight: 700;
    color: var(--gold);
    letter-spacing: 2px;
  }
  .subtitle {
    margin: 0 0 32px;
    color: var(--muted);
    font-size: 13px;
    letter-spacing: 1px;
  }
  .google-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    width: 100%;
    padding: 14px 24px;
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: var(--bg-dark);
    border: 2px solid var(--cream);
    border-radius: 6px;
    text-align: center;
    text-decoration: none;
    font-family: var(--font-display);
    font-size: 15px;
    font-weight: 700;
    letter-spacing: 2px;
    text-transform: uppercase;
    transition: all 0.2s;
    box-shadow: 0 4px 12px rgba(0,0,0,0.4);
  }
  .google-btn:hover {
    background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(212,165,116,0.3);
  }
</style>
