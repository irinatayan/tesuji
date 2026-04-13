import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
  interface Window {
    Pusher: typeof Pusher;
  }
}

window.Pusher = Pusher;

let echoInstance: Echo | null = null;

export function getEcho(): Echo {
  if (!echoInstance) {
    const tls = import.meta.env.VITE_REVERB_SCHEME === 'https';
    echoInstance = new Echo({
      broadcaster: 'reverb',
      key: import.meta.env.VITE_REVERB_APP_KEY,
      wsHost: import.meta.env.VITE_REVERB_HOST,
      wsPort: Number(import.meta.env.VITE_REVERB_PORT),
      wssPort: Number(import.meta.env.VITE_REVERB_PORT),
      forceTLS: tls,
      enabledTransports: tls ? ['wss'] : ['ws'],
      authEndpoint: `${import.meta.env.VITE_API_URL}/broadcasting/auth`,
      auth: {
        headers: {
          get Authorization() {
            return `Bearer ${localStorage.getItem('token')}`;
          },
        },
      },
    });
  }
  return echoInstance;
}

export function resetEcho(): void {
  echoInstance?.disconnect();
  echoInstance = null;
}
