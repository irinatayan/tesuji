export type Route =
  | { name: 'loading' }
  | { name: 'auth' }
  | { name: 'oauth-callback' }
  | { name: 'lobby' }
  | { name: 'game'; id: number }
  | { name: 'profile'; userId: number }

export const router = $state<{ current: Route }>({ current: { name: 'loading' } });

function parsePath(path: string, search: string): Route {
  if (search.includes('token=')) return { name: 'oauth-callback' };

  const gameMatch = path.match(/^\/game\/(\d+)$/);
  if (gameMatch) return { name: 'game', id: parseInt(gameMatch[1], 10) };

  const profileMatch = path.match(/^\/profile\/(\d+)$/);
  if (profileMatch) return { name: 'profile', userId: parseInt(profileMatch[1], 10) };

  if (path === '/login') return { name: 'auth' };

  return { name: 'lobby' };
}

export function routeToPath(route: Route): string {
  switch (route.name) {
    case 'auth':
      return '/login';
    case 'game':
      return `/game/${route.id}`;
    case 'profile':
      return `/profile/${route.userId}`;
    default:
      return '/';
  }
}

export function navigate(to: Route): void {
  router.current = to;
  if (to.name !== 'loading' && to.name !== 'oauth-callback') {
    history.pushState(null, '', routeToPath(to));
  }
}

export function initRouter(): Route {
  window.addEventListener('popstate', () => {
    router.current = parsePath(window.location.pathname, window.location.search);
  });
  return parsePath(window.location.pathname, window.location.search);
}
