import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'

// Where the API runs in development. The browser only ever talks to Vite, so the
// session and XSRF cookies stay first-party.
const BACKEND = process.env.BACKEND_URL ?? 'http://localhost:8080'
const PORT = Number(process.env.PORT ?? 5173)

export default defineConfig({
  plugins: [
    vue(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['favicon.svg', 'favicon.ico', 'apple-touch-icon-180x180.png'],
      manifest: {
        name: 'OpenWhen',
        short_name: 'OpenWhen',
        description: 'The driver app for distributors that learns when every shop is really open.',
        theme_color: '#0b1530',
        background_color: '#f4f6fa',
        display: 'standalone',
        start_url: '/',
        icons: [
          { src: 'pwa-64x64.png', sizes: '64x64', type: 'image/png' },
          { src: 'pwa-192x192.png', sizes: '192x192', type: 'image/png' },
          { src: 'pwa-512x512.png', sizes: '512x512', type: 'image/png' },
          { src: 'maskable-icon-512x512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
        ],
      },
      workbox: {
        navigateFallbackDenylist: [/^\/api\//],
        globPatterns: ['**/*.{js,css,html,svg,png,woff2}'],
      },
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    port: PORT,
    strictPort: true,
    proxy: {
      '/api': { target: BACKEND, changeOrigin: false },
      '/sanctum': { target: BACKEND, changeOrigin: false },
    },
  },
  preview: {
    port: PORT,
    proxy: {
      '/api': { target: BACKEND, changeOrigin: false },
    },
  },
})
