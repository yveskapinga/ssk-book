import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      includeAssets: ['logo.png', 'favicon-32.png', 'apple-touch-icon.png', 'icon-192.png', 'icon-512.png', 'icon-512-maskable.png'],
      manifest: {
        name: 'SSK Book',
        short_name: 'SSK Book',
        description: 'Lecture interactive, questions et quiz autour du livre du SSK.',
        theme_color: '#0b4aa2',
        background_color: '#ffffff',
        display: 'standalone',
        start_url: '/',
        icons: [
          { src: '/icon-192.png', sizes: '192x192', type: 'image/png', purpose: 'any' },
          { src: '/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'any' },
          { src: '/icon-512-maskable.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
      workbox: {
        skipWaiting: true,
        clientsClaim: true,
        cleanupOutdatedCaches: true,
        cacheId: 'ssk-book-20260924-install',
        globPatterns: ['**/*.{js,css,html,svg,png,woff2}'],
        runtimeCaching: [
          {
            urlPattern: /\/api\/(library|me\/dashboard|books\/[^/?]+)$/,
            handler: 'NetworkFirst',
            options: { cacheName: 'ssk-published-book', networkTimeoutSeconds: 5 },
          },
        ],
      },
    }),
  ],
  server: {
    host: '0.0.0.0',
    proxy: {
      '/api': {
        // En Docker Compose : http://api:8080 (voir compose.yaml).
        // En local hors Docker : http://localhost:8080.
        target: process.env.VITE_PROXY_TARGET ?? 'http://localhost:8080',
        changeOrigin: true,
      },
    },
  },
})
