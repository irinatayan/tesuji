import type { User } from '$lib/api';

export const auth = $state({
  token: localStorage.getItem('token') as string | null,
  user: null as User | null,
});

export function setToken(token: string) {
  localStorage.setItem('token', token);
  auth.token = token;
}

export function clearAuth() {
  localStorage.removeItem('token');
  auth.token = null;
  auth.user = null;
}
