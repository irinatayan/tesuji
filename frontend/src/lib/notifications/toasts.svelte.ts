export interface ToastAction {
  label: string;
  style: 'primary' | 'danger';
  handler: () => void;
}

export interface Toast {
  id: number;
  type: 'info' | 'invite';
  message: string;
  actions?: ToastAction[];
}

let nextId = 1;

export const toasts = $state({ items: [] as Toast[] });

export function addToast(toast: Omit<Toast, 'id'>, durationMs = 15000): number {
  const id = nextId++;
  toasts.items = [...toasts.items, { ...toast, id }];
  if (durationMs > 0) {
    setTimeout(() => dismissToast(id), durationMs);
  }
  return id;
}

export function dismissToast(id: number) {
  toasts.items = toasts.items.filter((t) => t.id !== id);
}
