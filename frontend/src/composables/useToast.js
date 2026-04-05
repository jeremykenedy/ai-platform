import { computed } from 'vue'
import { useUIStore } from '@/stores/ui'

export function useToast() {
  const ui = useUIStore()
  const toasts = computed(() => ui.toasts)

  function toast({ title, description, variant = 'default', duration = 4000 }) {
    ui.addToast({ title, description, variant, duration })
  }

  function dismiss(id) {
    ui.removeToast(id)
  }

  return { toasts, toast, dismiss }
}
