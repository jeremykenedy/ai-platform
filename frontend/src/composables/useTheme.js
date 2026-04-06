import { computed } from 'vue'
import { useUIStore } from '@/stores/ui'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

export function useTheme() {
  const ui = useUIStore()
  const auth = useAuthStore()

  const isDark = computed(() => ui.isDark)
  const theme = computed(() => ui.theme)

  function setTheme(value) {
    ui.setTheme(value)
    if (auth.isAuthenticated) {
      syncToServer(value)
    }
  }

  function toggle() {
    setTheme(ui.isDark ? 'light' : 'dark')
  }

  async function syncToServer(value) {
    try {
      await api.patch('/api/v1/settings', { theme: value ?? ui.theme.value })
    } catch {
      // Non-critical; ignore sync failures silently
    }
  }

  async function initFromServer() {
    if (!auth.isAuthenticated) return
    try {
      const response = await api.get('/api/v1/settings')
      const serverTheme = response.data?.data?.theme ?? response.data?.theme
      if (serverTheme) {
        ui.setTheme(serverTheme)
      }
    } catch {
      // Fall back to locally stored theme
    }
  }

  return { isDark, theme, setTheme, toggle, syncToServer, initFromServer }
}
