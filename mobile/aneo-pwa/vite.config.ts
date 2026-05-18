import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
  plugins: [
    react(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['aneo-logo.png', 'aneo-icon-192.png', 'aneo-icon-512.png'],
      manifest: {
        id: '/',
        name: 'ANEO Diretoria',
        short_name: 'ANEO',
        description: 'Painel executivo ANEO em formato instalavel para celular e desktop.',
        lang: 'pt-BR',
        theme_color: '#0a1628',
        background_color: '#081628',
        display: 'standalone',
        orientation: 'portrait',
        start_url: '/',
        scope: '/',
        icons: [
          { src: '/aneo-icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: '/aneo-icon-512.png', sizes: '512x512', type: 'image/png' },
          {
            src: '/aneo-icon-512.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'any maskable',
          },
        ],
      },
      workbox: {
        cleanupOutdatedCaches: true,
        clientsClaim: true,
        globPatterns: ['**/*.{js,css,html,ico,png,svg}'],
        skipWaiting: true,
      },
    }),
  ],
});
