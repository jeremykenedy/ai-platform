import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { compression } from 'vite-plugin-compression2'
import { fileURLToPath, URL } from 'node:url'

// PWA / service worker is intentionally disabled. The cached SW bundle
// was causing users to see fixed bugs as still-present across deploys.
// Re-enable once the app is stable.

export default defineConfig({
  plugins: [
    vue(),
    tailwindcss(),
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
