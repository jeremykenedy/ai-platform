import { onMounted, onUnmounted } from 'vue'

const TYPING_TAGS = new Set(['INPUT', 'TEXTAREA', 'SELECT'])

function isTyping() {
  const el = document.activeElement
  if (!el) return false
  if (TYPING_TAGS.has(el.tagName)) return true
  if (el.isContentEditable) return true
  return false
}

const registeredShortcuts = new Map()

export function useKeyboard(shortcuts = {}) {
  function handler(e) {
    const key = []
    if (e.metaKey || e.ctrlKey) key.push('mod')
    if (e.shiftKey) key.push('shift')
    if (e.altKey) key.push('alt')
    key.push(e.key.toLowerCase())

    const combo = key.join('+')
    const fn = shortcuts[combo]
    if (!fn) return

    // Escape always fires, even in inputs
    const isEscape = e.key === 'Escape'
    if (!isEscape && isTyping()) return

    e.preventDefault()
    fn(e)
  }

  // Track shortcuts for getShortcuts()
  for (const [combo, fn] of Object.entries(shortcuts)) {
    registeredShortcuts.set(combo, fn)
  }

  onMounted(() => window.addEventListener('keydown', handler))
  onUnmounted(() => {
    window.removeEventListener('keydown', handler)
    for (const combo of Object.keys(shortcuts)) {
      registeredShortcuts.delete(combo)
    }
  })
}

export function getShortcuts() {
  return Array.from(registeredShortcuts.entries()).map(([combo, fn]) => ({
    combo,
    fn,
  }))
}
