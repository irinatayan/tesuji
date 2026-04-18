<script lang="ts">
  import { onlineUsers } from '$lib/stores/online';

  let { userId }: { userId: number } = $props();
</script>

{#if onlineUsers.has(userId)}
  <span class="online-dot" title="Online"></span>
{/if}

<style>
  .online-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #4caf50;
    flex-shrink: 0;
  }
</style>
