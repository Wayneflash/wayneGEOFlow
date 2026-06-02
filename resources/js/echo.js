import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const runtimeReverb = window.AdminRealtime || {};
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY || runtimeReverb.key;
const reverbHost = import.meta.env.VITE_REVERB_HOST || runtimeReverb.host || window.location.hostname;
const reverbPort = import.meta.env.VITE_REVERB_PORT || runtimeReverb.port || (window.location.protocol === 'https:' ? 443 : 80);
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME || runtimeReverb.scheme || window.location.protocol.replace(':', '');

if (reverbKey) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: reverbHost,
        wsPort: reverbPort,
        wssPort: reverbPort,
        forceTLS: reverbScheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
} else {
    window.Echo = null;
}
