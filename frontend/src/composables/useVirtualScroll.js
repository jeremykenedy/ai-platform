import { ref } from 'vue'
import { useVirtualizer } from '@tanstack/vue-virtual'

export function useVirtualScroll(items, options = {}) {
  const scrollRef = ref(null)

  const virtualizer = useVirtualizer({
    get count() {
      return items.value?.length || 0
    },
    getScrollElement: () => scrollRef.value,
    estimateSize: (index) => {
      const item = items.value?.[index]
      if (!item) return 120
      return (item.content?.length || 0) > 500 ? 240 : 120
    },
    overscan: 5,
    ...options,
  })

  return { scrollRef, virtualizer }
}
