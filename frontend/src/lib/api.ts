const BASE = import.meta.env.VITE_API_URL ?? 'http://tesuji.ddev.site';

export class ApiError extends Error {
  constructor(
    public status: number,
    public body: unknown,
  ) {
    super(`API error ${status}`);
  }
}

export interface User {
  id: number;
  name: string;
  email: string;
}

export interface GameResponse {
  id: number;
  board_size: number;
  status: 'playing' | 'scoring' | 'finished';
  current_turn: 'black' | 'white' | null;
  result: string | null;
  black_player: User;
  white_player: User;
  board: (string | null)[][];
  dead_stones: { x: number; y: number }[] | null;
}

async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
  const token = localStorage.getItem('token');
  const res = await fetch(`${BASE}/api${path}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) {
    throw new ApiError(res.status, await res.json().catch(() => null));
  }
  if (res.status === 204) return undefined as T;
  return res.json();
}

export const api = {
  register: (name: string, email: string, password: string) =>
    request<{ token: string }>('POST', '/auth/register', { name, email, password }),

  login: (email: string, password: string) =>
    request<{ token: string }>('POST', '/auth/login', { email, password }),

  logout: () => request<void>('POST', '/auth/logout'),

  me: () => request<User>('GET', '/user'),

  searchUsers: (query: string) =>
    request<{ id: number; name: string }[]>('GET', `/users?search=${encodeURIComponent(query)}`),

  createGame: (params: {
    opponent_id: number;
    board_size: number;
    mode: string;
    time_control_type: string;
    time_control_config: Record<string, number>;
    color: string;
  }) => request<{ data: GameResponse }>('POST', '/games', params),

  getGame: (id: number) => request<{ data: GameResponse }>('GET', `/games/${id}`),

  getGames: () => request<{ data: GameResponse[] }>('GET', '/games'),

  playMove: (id: number, x: number, y: number) =>
    request<{ data: GameResponse }>('POST', `/games/${id}/moves`, { x, y }),

  pass: (id: number) => request<{ data: GameResponse }>('POST', `/games/${id}/pass`),

  resign: (id: number) => request<{ data: GameResponse }>('POST', `/games/${id}/resign`),

  markDead: (id: number, stones: { x: number; y: number }[]) =>
    request<{ data: GameResponse }>('POST', `/games/${id}/dead-stones`, { stones }),

  confirmDead: (id: number) =>
    request<{ data: GameResponse }>('POST', `/games/${id}/dead-stones/confirm`),

  disputeDead: (id: number) =>
    request<{ data: GameResponse }>('POST', `/games/${id}/dead-stones/dispute`),
};
