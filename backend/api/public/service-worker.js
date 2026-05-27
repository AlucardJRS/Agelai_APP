/*
 * Club Agelai service worker.
 * Security-oriented strategy:
 * - Cache only static public assets required for shell/offline UX.
 * - Never cache API responses or authenticated dashboard HTML.
 */

const CACHE_NAME = 'agelai-pwa-v1';
const STATIC_ASSETS = [
    '/offline.html',
    '/manifest.webmanifest',
    '/assets/styles.css',
    '/assets/brand/agelai-logo.png',
    '/assets/brand/icon-192.png',
    '/assets/brand/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then((cache) => cache.addAll(STATIC_ASSETS))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(
            keys
                .filter((key) => key !== CACHE_NAME)
                .map((key) => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(request.url);
    const sameOrigin = requestUrl.origin === self.location.origin;
    if (!sameOrigin) {
        return;
    }

    const pathname = requestUrl.pathname;
    const isApiRequest = pathname.startsWith('/api/');
    const isDashboardRequest = pathname.startsWith('/dashboard');

    // Never cache sensitive/private dynamic endpoints.
    if (isApiRequest || isDashboardRequest) {
        event.respondWith(
            fetch(request).catch(() => caches.match('/offline.html'))
        );
        return;
    }

    // Static assets: cache-first with network fallback.
    event.respondWith(
        caches.match(request).then((cached) => {
            if (cached) {
                return cached;
            }
            return fetch(request).then((response) => {
                if (!response || response.status !== 200 || response.type !== 'basic') {
                    return response;
                }
                const cloned = response.clone();
                caches.open(CACHE_NAME).then((cache) => {
                    cache.put(request, cloned);
                });
                return response;
            }).catch(() => caches.match('/offline.html'));
        })
    );
});
