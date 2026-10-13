import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Live updates on the deployed site use polling (partials/notification-live), because Vercel has no socket server.
// Echo is only started when a real Pusher-compatible key is configured, so pages do not keep trying to open a
// WebSocket to a port that is not there.
window.Pusher = Pusher;

if (import.meta.env.VITE_PUSHER_APP_KEY) {
    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
        forceTLS: window.location.protocol === 'https:',
        wsHost: import.meta.env.VITE_PUSHER_HOST || window.location.hostname,
        wsPort: Number(import.meta.env.VITE_PUSHER_PORT || 6001),
        wssPort: Number(import.meta.env.VITE_PUSHER_PORT || 6001),
        disableStats: true,
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
        },
    });
}
