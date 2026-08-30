import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';
export default defineConfig({
    plugins: [
        react(),
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: ['favicon.png', 'offline.html', 'pwa-192x192.png', 'pwa-512x512.png', 'maskable-512x512.png'],
            manifest: {
                name: 'Coffee Cafe',
                short_name: 'Coffee',
                description: 'Coffee customer ordering PWA.',
                theme_color: '#04764e',
                background_color: '#f6f1eb',
                display: 'standalone',
                start_url: '/',
                icons: [
                    {
                        src: '/pwa-192x192.png',
                        sizes: '192x192',
                        type: 'image/png'
                    },
                    {
                        src: '/pwa-512x512.png',
                        sizes: '512x512',
                        type: 'image/png'
                    },
                    {
                        src: '/maskable-512x512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable'
                    }
                ]
            },
            workbox: {
                cleanupOutdatedCaches: true,
                clientsClaim: true,
                skipWaiting: true,
                navigateFallback: '/index.html',
                navigateFallbackDenylist: [/^\/api\//],
                globPatterns: ['**/*.{js,css,html,ico,png,svg,jpg,jpeg,webp,woff,woff2}'],
                runtimeCaching: [
                    {
                        urlPattern: function (_a) {
                            var request = _a.request, url = _a.url;
                            return request.destination === 'image' && url.pathname.startsWith('/assets/');
                        },
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'coffee-static-images',
                            expiration: {
                                maxEntries: 32,
                                maxAgeSeconds: 60 * 60 * 24 * 14
                            }
                        }
                    },
                    {
                        urlPattern: function (_a) {
                            var request = _a.request;
                            return request.destination === 'font';
                        },
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'coffee-static-fonts',
                            expiration: {
                                maxEntries: 10,
                                maxAgeSeconds: 60 * 60 * 24 * 30
                            }
                        }
                    }
                ]
            }
        })
    ],
    server: {
        host: '0.0.0.0',
        port: 4173
    }
});
