// Server time synchronization. Avoids clock skew between client and server
// by computing serverOffset = server_time - Date.now() on every received message,
// then deriving server-relative time as Date.now() + serverOffset.

export const time = $state({
  offset: 0,
});

export function applyServerTime(serverTimeMs: number) {
  time.offset = serverTimeMs - Date.now();
}

export function serverNow(): number {
  return Date.now() + time.offset;
}
