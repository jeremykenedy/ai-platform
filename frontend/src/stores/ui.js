import { ref, computed } from 'vue'
import { defineStore } from 'pinia'

const THEME_KEY = 'ai-platform-theme'

export const useUiStore = defineStore('ui', () => {
  const sidebarOpen = ref(true)
  const theme = ref(localStorage.getItem(THEME_KEY) ?? 'system')
  const toasts = ref([])
  const isMobile = ref(false)

  const isDark = computed(() => {
    if (theme.value === 'dark') return true
    if (theme.value === 'light') return false
    return window.matchMedia('(prefers-color-scheme: dark)').matches
  })

  function toggleSidebar() {
    sidebarOpen.value = !sidebarOpen.value
  }

  function setTheme(value) {
    theme.value = value
    localStorage.setItem(THEME_KEY, value)
    applyTheme(value)
  }

  function applyTheme(value) {
    const root = document.documentElement
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches
    const dark = value === 'dark' || (value === 'system' && prefersDark)
    root.classList.toggle('dark', dark)
  }

  function addToast(toast) {
    const id = toast.id ?? `toast-${Date.now()}-${Math.random().toString(36).slice(2)}`
    toasts.value.push({ ...toast, id })
    if (toast.duration !== 0) {
      setTimeout(() => removeToast(id), toast.duration ?? 4000)
    }
    return id
  }

  function removeToast(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
  }

  function setMobile(value) {
    isMobile.value = value
    if (value) sidebarOpen.value = false
  }

  // Initialize theme on store creation
  applyTheme(theme.value)

  // React to system preference changes when theme is 'system'
  window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (theme.value === 'system') applyTheme('system')
  })

  return {
    sidebarOpen,
    theme,
    toasts,
    isMobile,
    isDark,
    toggleSidebar,
    setTheme,
    addToast,
    removeToast,
    setMobile,
  }
})
