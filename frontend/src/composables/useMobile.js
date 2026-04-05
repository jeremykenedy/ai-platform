import { onMounted, onUnmounted, ref } from 'vue'
import { useUIStore } from '@/stores/ui'

export function useMobile(breakpoint = 768) {
  const isMobile = ref(false)
  const ui = useUIStore()

  function check() {
    const mobile = window.innerWidth < breakpoint
    isMobile.value = mobile
    ui.setMobile(mobile)
  }

  onMounted(() => {
    check()
    window.addEventListener('resize', check)
  })

  onUnmounted(() => {
    window.removeEventListener('resize', check)
  })

  return { isMobile }
}
