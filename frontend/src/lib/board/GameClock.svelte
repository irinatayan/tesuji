<script lang="ts">
  import type { GameClock } from '$lib/api';
  import { time } from '$lib/stores/time.svelte';

  let {
    timeControlType,
    clock,
    turnStartedAt,
    expiresAt,
    isActive,
  }: {
    timeControlType: 'absolute' | 'byoyomi' | 'correspondence';
    clock: GameClock | null;
    turnStartedAt: number | null;
    expiresAt: string | null;
    isActive: boolean;
  } = $props();

  let display = $state('');
  let isLow = $state(false);

  $effect(() => {
    if (timeControlType === 'absolute') {
      tickAbsolute();
      if (isActive && turnStartedAt !== null) {
        const interval = setInterval(tickAbsolute, 250);
        return () => clearInterval(interval);
      }
    } else if (timeControlType === 'correspondence') {
      updateCorrespondence();
    }
  });

  function tickAbsolute() {
    const snapshot = clock?.remaining_ms ?? 0;
    let remainingMs: number;

    if (isActive && turnStartedAt !== null) {
      const serverNow = Date.now() + time.offset;
      const elapsed = Math.max(0, serverNow - turnStartedAt);
      remainingMs = Math.max(0, snapshot - elapsed);
    } else {
      remainingMs = snapshot;
    }

    const totalSec = Math.floor(remainingMs / 1000);
    const min = Math.floor(totalSec / 60);
    const sec = totalSec % 60;
    display = `${min}:${sec.toString().padStart(2, '0')}`;
    isLow = remainingMs < 30_000;
  }

  function updateCorrespondence() {
    if (!expiresAt || !isActive) {
      display = '';
      return;
    }
    const diffMs = new Date(expiresAt).getTime() - Date.now();
    if (diffMs <= 0) {
      display = '0d';
      return;
    }
    const totalHours = Math.floor(diffMs / 3_600_000);
    const days = Math.floor(totalHours / 24);
    const hours = totalHours % 24;
    display = days > 0 ? `${days}d ${hours}h` : `${hours}h`;
    isLow = diffMs < 86_400_000;
  }
</script>

{#if display}
  <span class="clock" class:active={isActive} class:low={isLow}>
    {display}
  </span>
{/if}

<style>
  .clock {
    font-family: var(--font-display);
    font-size: 13px;
    color: var(--cream);
    opacity: 0.6;
    letter-spacing: 1px;
    transition: color 0.3s;
  }
  .clock.active {
    opacity: 1;
    color: var(--gold);
  }
  .clock.low {
    color: #ff6b6b;
    animation: pulse 1s ease-in-out infinite;
  }
  @keyframes pulse {
    0%,
    100% {
      opacity: 1;
    }
    50% {
      opacity: 0.5;
    }
  }
</style>
