"use strict";

/**
 * PROMAFI ERP — Service Worker (PWA)
 *
 * Objetivo: offline mínimo + actualización limpia.
 * No cachea HTML autenticado ni XHR/API (evita lentitud y datos viejos).
 * Solo intercepta navegaciones GET; el resto lo deja el navegador.
 *
 * Bump CACHE_NAME al cambiar estrategia para purgar cachés viejas.
 */
const CACHE_NAME = "promafi-offline-v2";
const OFFLINE_URL = "/offline.html";

const filesToCache = [OFFLINE_URL];

self.addEventListener("install", (event) => {
    event.waitUntil(
        caches
            .open(CACHE_NAME)
            .then((cache) => cache.addAll(filesToCache))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener("activate", (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((cacheNames) =>
                Promise.all(
                    cacheNames.map((cacheName) => {
                        if (cacheName !== CACHE_NAME) {
                            return caches.delete(cacheName);
                        }
                        return undefined;
                    })
                )
            )
            .then(() => self.clients.claim())
    );
});

self.addEventListener("fetch", (event) => {
    const request = event.request;

    // Solo navegaciones document GET: red primero; si no hay red → offline.html
    if (request.mode !== "navigate" || request.method !== "GET") {
        return;
    }

    event.respondWith(
        fetch(request).catch(() =>
            caches.match(OFFLINE_URL).then((cached) => {
                return (
                    cached ||
                    new Response("Sin conexión", {
                        status: 503,
                        statusText: "Offline",
                        headers: { "Content-Type": "text/plain; charset=utf-8" },
                    })
                );
            })
        )
    );
});
