<script lang="ts">
  import { tick } from 'svelte';
  import { api, type ChatMessage } from '$lib/api';

  interface Props {
    gameId: number;
    currentUserId: number;
    channel: any;
    collapsed?: boolean;
    onUncollapse?: () => void;
    onCollapse?: () => void;
  }

  let {
    gameId,
    currentUserId,
    channel,
    collapsed = false,
    onUncollapse,
    onCollapse,
  }: Props = $props();

  let messages = $state<ChatMessage[]>([]);
  let text = $state('');
  let sending = $state(false);
  let unread = $state(0);
  let scrollEl = $state<HTMLElement | null>(null);

  function isAtBottom(): boolean {
    if (!scrollEl) return true;
    return scrollEl.scrollHeight - scrollEl.scrollTop - scrollEl.clientHeight < 60;
  }

  async function scrollToBottom() {
    await tick();
    if (scrollEl) scrollEl.scrollTop = scrollEl.scrollHeight;
  }

  function addMessage(msg: ChatMessage) {
    const wasAtBottom = isAtBottom();
    messages = [...messages, msg];
    if (collapsed) {
      unread += 1;
    } else if (wasAtBottom) {
      scrollToBottom();
    }
  }

  let historyLoaded = false;

  $effect(() => {
    if (!channel) return;

    if (!historyLoaded) {
      historyLoaded = true;
      api.getMessages(gameId).then((res) => {
        messages = res.data;
        scrollToBottom();
      });
    }

    channel.listen('.game.message.sent', (e: ChatMessage) => {
      addMessage(e);
    });

    channel.on('pusher:subscription_succeeded', async () => {
      const lastId = messages.at(-1)?.id;
      if (lastId !== undefined) {
        const res = await api.getMessages(gameId, lastId);
        if (res.data.length > 0) {
          const wasAtBottom = isAtBottom();
          messages = [...messages, ...res.data];
          if (!collapsed && wasAtBottom) scrollToBottom();
          if (collapsed) unread += res.data.length;
        }
      }
    });
  });

  async function handleSend() {
    const trimmed = text.trim();
    if (!trimmed || sending) return;
    sending = true;
    text = '';
    try {
      await api.sendMessage(gameId, trimmed);
    } catch {
      text = trimmed;
    } finally {
      sending = false;
    }
  }

  function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSend();
    }
  }

  function handleExpand() {
    unread = 0;
    onUncollapse?.();
    scrollToBottom();
  }

  function formatTime(iso: string): string {
    const d = new Date(iso);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
</script>

{#if collapsed}
  <button class="chat-toggle" onclick={handleExpand}>
    💬 Chat{#if unread > 0}<span class="badge">{unread}</span>{/if}
  </button>
{:else}
  <div class="chat">
    <div class="chat-header">
      <span>💬 Chat</span>
      <button class="chat-close" onclick={() => onCollapse?.()} aria-label="Close chat">✕</button>
    </div>
    <div class="messages" bind:this={scrollEl}>
      {#if messages.length === 0}
        <p class="empty">No messages yet</p>
      {:else}
        {#each messages as msg (msg.id)}
          <div class="message {msg.user_id === currentUserId ? 'mine' : 'theirs'}">
            {#if msg.user_id !== currentUserId}
              <span class="name">{msg.user_name}</span>
            {/if}
            <div class="bubble">
              <span class="msg-text">{msg.text}</span>
              <span class="time">{formatTime(msg.created_at)}</span>
            </div>
          </div>
        {/each}
      {/if}
    </div>
    <div class="input-row">
      <textarea
        bind:value={text}
        onkeydown={handleKeydown}
        placeholder="Message…"
        rows="1"
        maxlength="500"
        disabled={sending}
      ></textarea>
      <button onclick={handleSend} disabled={!text.trim() || sending}>Send</button>
    </div>
  </div>
{/if}

<style>
  .chat-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 18px;
    background: rgba(20, 13, 8, 0.8);
    border: 2px solid var(--border);
    border-radius: 6px;
    color: var(--gold);
    font-family: var(--font-display);
    font-size: 13px;
    letter-spacing: 1px;
    cursor: pointer;
    transition: all 0.2s;
    width: 100%;
  }
  .chat-toggle:hover {
    background: rgba(139, 90, 43, 0.2);
    border-color: var(--gold);
  }

  .badge {
    background: #c0392b;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 1px 7px;
    border-radius: 10px;
    font-family: var(--font-display);
    line-height: 1.4;
  }

  .chat {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    background: rgba(14, 9, 5, 0.85);
    border: 2px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
  }

  .chat-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 2px;
    color: var(--gold);
    text-transform: uppercase;
    border-bottom: 1px solid var(--border-dim);
    flex-shrink: 0;
  }

  .chat-close {
    display: none;
    background: none;
    border: none;
    color: var(--muted);
    font-size: 14px;
    cursor: pointer;
    padding: 2px 4px;
    line-height: 1;
    transition: color 0.2s;
  }
  .chat-close:hover {
    color: var(--cream);
  }

  @media (max-width: 719px) {
    .chat-toggle {
      border-radius: 0;
      border-left: none;
      border-right: none;
      border-bottom: none;
    }

    .chat {
      height: 55vh;
      border-radius: 12px 12px 0 0;
      border-bottom: none;
      background: rgba(14, 9, 5, 0.97);
      backdrop-filter: blur(8px);
    }

    .chat-close {
      display: block;
    }
  }

  .messages {
    flex: 1;
    overflow-y: auto;
    padding: 12px 12px 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 0;
    scrollbar-width: thin;
    scrollbar-color: var(--border-dim) transparent;
  }

  .empty {
    color: var(--muted);
    font-size: 13px;
    font-style: italic;
    text-align: center;
    margin: auto;
  }

  .message {
    display: flex;
    flex-direction: column;
    gap: 2px;
    max-width: 85%;
  }

  .message.mine {
    align-self: flex-end;
    align-items: flex-end;
  }

  .message.theirs {
    align-self: flex-start;
    align-items: flex-start;
  }

  .name {
    font-size: 11px;
    color: var(--muted);
    font-family: var(--font-serif);
    padding: 0 6px;
  }

  .bubble {
    display: flex;
    align-items: flex-end;
    gap: 6px;
    padding: 7px 10px;
    border-radius: 12px;
    font-family: var(--font-serif);
    font-size: 14px;
    line-height: 1.4;
    word-break: break-word;
  }

  .mine .bubble {
    background: rgba(139, 90, 43, 0.35);
    border: 1px solid rgba(139, 90, 43, 0.5);
    color: var(--cream);
    border-bottom-right-radius: 3px;
  }

  .theirs .bubble {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid var(--border-dim);
    color: var(--cream);
    border-bottom-left-radius: 3px;
  }

  .msg-text {
    flex: 1;
  }

  .time {
    font-size: 10px;
    color: var(--muted);
    white-space: nowrap;
    flex-shrink: 0;
    line-height: 1.6;
  }

  .input-row {
    display: flex;
    gap: 8px;
    padding: 10px 12px;
    border-top: 1px solid var(--border-dim);
    flex-shrink: 0;
  }

  textarea {
    flex: 1;
    resize: none;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-dim);
    border-radius: 6px;
    color: var(--cream);
    font-family: var(--font-serif);
    font-size: 14px;
    padding: 7px 10px;
    line-height: 1.4;
    outline: none;
    transition: border-color 0.2s;
    overflow-y: hidden;
  }
  textarea:focus {
    border-color: var(--gold);
  }
  textarea::placeholder {
    color: var(--muted);
  }

  .input-row button {
    padding: 7px 14px;
    background: linear-gradient(135deg, var(--gold) 0%, var(--gold-dark) 100%);
    color: var(--bg-dark);
    border: none;
    border-radius: 6px;
    font-family: var(--font-display);
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 1px;
    cursor: pointer;
    transition: all 0.2s;
    white-space: nowrap;
    align-self: flex-end;
  }
  .input-row button:hover:not(:disabled) {
    background: linear-gradient(135deg, var(--gold-light) 0%, var(--gold) 100%);
  }
  .input-row button:disabled {
    opacity: 0.35;
    cursor: not-allowed;
  }
</style>
