<script lang="ts">
  import { onMount } from 'svelte';
  import { _ } from 'svelte-i18n';
  import { setToken } from '$lib/stores/auth.svelte';

  let { onSuccess, onFail }: { onSuccess: () => void; onFail: () => void } = $props();

  onMount(() => {
    const params = new URLSearchParams(window.location.search);
    const token = params.get('token');
    if (token) {
      setToken(token);
      history.replaceState({}, '', '/');
      onSuccess();
    } else {
      onFail();
    }
  });
</script>

<p>{$_('auth.authorizing')}</p>
