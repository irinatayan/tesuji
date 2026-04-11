import { api } from '$lib/api';

export interface PendingInvitation {
  id: number;
  from_user: { id: number; name: string };
  board_size: number;
  mode: string;
  time_control_type: string;
}

export const invitationStore = $state({
  incoming: [] as PendingInvitation[],
  loaded: false,
});

export async function loadIncoming() {
  try {
    invitationStore.incoming = await api.getInvitations();
    invitationStore.loaded = true;
  } catch {
    // ignore
  }
}

export function removeIncoming(id: number) {
  invitationStore.incoming = invitationStore.incoming.filter((i) => i.id !== id);
}

export function incomingCount(): number {
  return invitationStore.incoming.length;
}
