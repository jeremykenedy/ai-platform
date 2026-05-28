import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import { useUIStore } from './stores/ui'
import App from './App.vue'
import './assets/main.css'

// Unregister any previously-installed service worker and wipe its caches.
// PWA caching was burning users with stale bundles across deploys.
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.getRegistrations().then((regs) => {
    for (const r of regs) r.unregister()
  })
  if ('caches' in window) {
    caches.keys().then((keys) => keys.forEach((k) => caches.delete(k)))
  }
}

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

app.config.errorHandler = (err, instance, info) => {
  console.error('[App Error]', err, info)

  try {
    const ui = useUIStore()
    ui.addToast({
      title: 'Something went wrong',
      description: err?.message || 'An unexpected error occurred',
      variant: 'error',
      duration: 6000,
    })
  } catch {
    // Store might not be initialized yet
  }
}

app.mount('#app')
