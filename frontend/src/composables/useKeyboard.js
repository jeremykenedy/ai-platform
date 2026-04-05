import { onMounted, onUnmounted } from 'vue'

export function useKeyboard(shortcuts = {}) {
  function handler(e) {
    const key = []
    if (e.metaKey || e.ctrlKey) key.push('mod')
    if (e.shiftKey) key.push('shift')
    if (e.altKey) key.push('alt')
    key.push(e.key.toLowerCase())

    const combo = key.join('+')
    if (shortcuts[combo]) {
      e.preventDefault()
      shortcuts[combo](e)
    }
  }

  onMounted(() => window.addEventListener('keydown', handler))
  onUnmounted(() => window.removeEventListener('keydown', handler))
}
