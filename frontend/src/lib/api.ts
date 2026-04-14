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
  unread_count?: number;
}

async function request<T>(method: string, path: string, body?: unknown): Promise<T> {
  const token = localStorage.getItem('token');
  const res = await fetch(`${BASE}/api${path}`, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'Accept-Language': localStorage.getItem('locale') ?? 'en',
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

  listUsers: (params: { search?: string; page?: number } = {}) => {
    const qs = new URLSearchParams();
    if (params.search) qs.set('search', params.search);
    if (params.page) qs.set('page', String(params.page));
    const query = qs.toString();
    return request<{
      data: { id: number; name: string }[];
      meta: { current_page: number; last_page: number; total: number };
    }>('GET', `/users${query ? `?${query}` : ''}`);
  },

  getUserProfile: (id: number) => request<any>('GET', `/users/${id}`),

  getUserGames: (id: number, page = 1) => request<any>('GET', `/users/${id}/games?page=${page}`),

  getInvitations: () => request<any[]>('GET', '/invitations/incoming'),

  getOutgoingInvitations: () => request<any[]>('GET', '/invitations/outgoing'),

  sendInvitation: (params: {
    to_user_id: number;
    board_size: number;
    mode: string;
    time_control_type: string;
    time_control_config: Record<string, number>;
    proposed_color: string;
  }) => request<any>('POST', '/invitations', params),

  acceptInvitation: (id: number) =>
    request<{ game_id: number }>('POST', `/invitations/${id}/accept`),

  declineInvitation: (id: number) => request<void>('POST', `/invitations/${id}/decline`),

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

  getMessages: (gameId: number, after?: number) =>
    request<{ data: ChatMessage[] }>(
      'GET',
      `/games/${gameId}/messages${after !== undefined ? `?after=${after}` : ''}`,
    ),

  sendMessage: (gameId: number, text: string) =>
    request<{ data: ChatMessage }>('POST', `/games/${gameId}/messages`, { text }),

  markMessagesRead: (gameId: number, lastReadId: number) =>
    request<void>('POST', `/games/${gameId}/messages/read`, { last_read_id: lastReadId }),
};

export interface ChatMessage {
  id: number;
  user_id: number;
  user_name: string;
  text: string;
  created_at: string;
}
