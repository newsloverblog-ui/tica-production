var CACHE_STATIC_NAME = 'tica-scanner-static-v27';
var CACHE_SHELL_NAME = 'tica-scanner-shell-v27';
var SCANNER_ASSETS = [
    '/offline',
    '/assets/front/images/offline.png',
    '/assets/front/img/static/offline-breadcrumb.jpeg',
    '/assets/pwa_scanner/bootstrap.min.css',
    '/assets/pwa_scanner/html5-qrcode.min.js',
    '/assets/pwa_scanner/jquery.min.js',
    '/assets/pwa_scanner/pwa.js',
    '/assets/pwa_scanner/script.js',
    '/assets/pwa_scanner/style.css',
    '/assets/admin/js/sweetalert.min.js',
    '/assets/front/css/toastr.css'
];

self.addEventListener('install', function(event) {
    event.waitUntil(
      caches.open(CACHE_STATIC_NAME)
        .then(function(cache) {
          console.log('[Service Worker] Precaching App Shell');
          return cache.addAll(SCANNER_ASSETS);
        })
    )
    self.skipWaiting();
});

self.addEventListener('activate', function(event) {
    console.log('[Service Worker] Activating Service Worker ....', event);
    event.waitUntil(
      caches.keys()
        .then(function(keyList) {
          return Promise.all(keyList.map(function(key) {
            if (key !== CACHE_STATIC_NAME && key !== CACHE_SHELL_NAME) {
              console.log('[Service Worker] Removing old cache.', key);
              return caches.delete(key);
            }
          }));
        })
    );
    return self.clients.claim();
});

self.addEventListener('fetch', function(event) {
    if (event.request.method !== 'GET') {
      event.respondWith(fetch(event.request));
      return;
    }

    var requestUrl = new URL(event.request.url);
    var scannerNavigation = event.request.mode === 'navigate' &&
      (requestUrl.pathname === '/organizer/pwa' || requestUrl.pathname === '/scanner/workspace');

    if (scannerNavigation) {
      event.respondWith(
        fetch(event.request).then(function(response) {
          if (response.ok) {
            var copy = response.clone();
            caches.open(CACHE_SHELL_NAME).then(function(cache) { cache.put('/scanner-offline-shell', copy); });
          }
          return response;
        }).catch(function() {
          return caches.open(CACHE_SHELL_NAME).then(function(cache) {
            return cache.match('/scanner-offline-shell');
          }).then(function(shell) {
            return shell || caches.match('/offline');
          });
        })
      );
      return;
    }

    var staticAsset = requestUrl.origin === self.location.origin &&
      (requestUrl.pathname.indexOf('/assets/pwa_scanner/') === 0 || SCANNER_ASSETS.indexOf(requestUrl.pathname) !== -1);
    if (staticAsset) {
      event.respondWith(caches.match(event.request).then(function(cached) {
        return cached || fetch(event.request);
      }));
      return;
    }

    event.respondWith(fetch(event.request));
});

self.addEventListener('push', function(e) {
    if (!(self.Notification && self.Notification.permission === 'granted')) {
        //notifications aren't supported or permission not granted!
        return;
    }

    if (e.data) {
        var msg = e.data.json();
        console.log(msg)
        var options = {
            body: msg.body,
            icon: msg.icon
        };
        if (msg.actions && msg.actions.length > 0) {
            options.actions = msg.actions;
        }
        e.waitUntil(self.registration.showNotification(msg.title, options));
    }
});


self.addEventListener('notificationclick', function(e) {
    if (e.action.length > 0) {
        self.clients.openWindow(e.action);
    }
});
