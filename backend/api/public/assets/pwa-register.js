/*
 * PWA bootstrap file.
 * Registers the service worker in secure contexts and avoids registration
 * on unsupported/legacy browsers.
 */
(function registerPwaServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    const isLocalhost = window.location.hostname === '127.0.0.1'
        || window.location.hostname === 'localhost';
    const isSecureContext = window.location.protocol === 'https:' || isLocalhost;
    if (!isSecureContext) {
        return;
    }

    window.addEventListener('load', function onWindowLoad() {
        navigator.serviceWorker.register('/service-worker.js', { scope: '/' })
            .catch(function onServiceWorkerError() {
                // Fail silently to avoid exposing internals on client logs.
            });
    });
})();
