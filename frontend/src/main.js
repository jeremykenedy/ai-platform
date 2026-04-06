import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import { cancelAllRequests } from './services/api'
import { useUIStore } from './stores/ui'
import App from './App.vue'
import './assets/main.css'

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

router.beforeEach(() => {
  cancelAllRequests()
})

app.mount('#app')
