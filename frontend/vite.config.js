import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { VitePWA } from 'vite-plugin-pwa'
import { compression } from 'vite-plugin-compression2'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
    VitePWA({
      registerType: 'autoUpdate',
      includeAssets: ['favicon.ico', 'apple-touch-icon.png'],
      workbox: {
        globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
        runtimeCaching: [
          {
            urlPattern: /^https?:\/\/.*\/api\/v1\/.*/i,
            handler: 'NetworkFirst',
            options: {
              cacheName: 'api-cache',
              expiration: { maxEntries: 100, maxAgeSeconds: 300 },
              networkTimeoutSeconds: 10,
            },
          },
          {
            urlPattern: /\.(?:png|jpg|jpeg|svg|gif|webp|ico)$/,
            handler: 'CacheFirst',
            options: {
              cacheName: 'images-cache',
              expiration: { maxEntries: 60, maxAgeSeconds: 2592000 },
            },
          },
          {
            urlPattern: /\.(?:woff|woff2|ttf|eot)$/,
            handler: 'StaleWhileRevalidate',
            options: { cacheName: 'fonts-cache' },
          },
        ],
      },
      manifest: {
        name: 'My AI',
        short_name: 'My AI',
        description: 'Personal AI powered by local models',
        theme_color: '#000000',
        background_color: '#000000',
        display: 'standalone',
        start_url: '/c/new',
        scope: '/',
        icons: [
          { src: 'icons/icon-192.png', sizes: '192x192', type: 'image/png' },
          { src: 'icons/icon-512.png', sizes: '512x512', type: 'image/png' },
          {
            src: 'icons/icon-512-maskable.png',
            sizes: '512x512',
            type: 'image/png',
            purpose: 'maskable',
          },
        ],
      },
    }),
    compression({
      algorithm: 'gzip',
      threshold: 1024,
    }),
    compression({
      algorithm: 'brotliCompress',
      threshold: 1024,
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  server: {
    proxy: {
      '/api': {
        target: 'https://ai-platform.test',
        changeOrigin: true,
        secure: false,
      },
      '/broadcasting': {
        target: 'https://ai-platform.test',
        changeOrigin: true,
        secure: false,
      },
      '/sanctum': {
        target: 'https://ai-platform.test',
        changeOrigin: true,
        secure: false,
      },
    },
  },
  build: {
    target: 'esnext',
    minify: 'esbuild',
    cssMinify: true,
    cssCodeSplit: true,
    reportCompressedSize: false,
    chunkSizeWarningLimit: 500,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes('node_modules/vue/')) return 'vue-core'
          if (id.includes('node_modules/vue-router')) return 'vue-router'
          if (id.includes('node_modules/pinia')) return 'pinia'
          if (id.includes('node_modules/@vueuse')) return 'vueuse'
          if (id.includes('node_modules/axios')) return 'axios'
          if (id.includes('node_modules/markdown-it')) return 'markdown'
          if (id.includes('node_modules/shiki')) return 'shiki'
          if (id.includes('node_modules/laravel-echo')) return 'echo'
          if (id.includes('node_modules/pusher-js')) return 'echo'
          if (id.includes('node_modules/lucide-vue-next')) return 'icons'
          if (id.includes('node_modules/radix-vue')) return 'radix'
          if (id.includes('src/components/ui')) return 'ui-components'
          if (id.includes('src/components/admin')) return 'admin'
          if (id.includes('src/components/training')) return 'training'
        },
        entryFileNames: 'assets/[name].[hash].js',
        chunkFileNames: 'assets/[name].[hash].js',
        assetFileNames: 'assets/[name].[hash].[ext]',
      },
    },
  },
})
