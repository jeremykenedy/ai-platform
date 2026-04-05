import { computed } from 'vue'
import { useUIStore } from '@/stores/ui'

export function useTheme() {
  const ui = useUIStore()

  const isDark = computed(() => ui.isDark)
  const theme = computed(() => ui.theme)

  function setTheme(value) {
    ui.setTheme(value)
  }

  function toggle() {
    setTheme(ui.isDark ? 'light' : 'dark')
  }

  return { isDark, theme, setTheme, toggle }
}
