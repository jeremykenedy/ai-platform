<script setup>
  import { cn } from '@/lib/utils'
  import { useMobile } from '@/composables/useMobile'
  import { useKeyboard } from '@/composables/useKeyboard'
  import AppSidebar from '@/components/sidebar/AppSidebar.vue'
  import { useUIStore } from '@/stores/ui'

  const ui = useUIStore()
  useMobile()
  useKeyboard({
    'mod+b': () => ui.toggleSidebar(),
    'mod+k': () => {
      /* open search - placeholder */
    },
  })
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-background">
    <AppSidebar />
    <main
      :class="
        cn(
          'flex flex-1 flex-col overflow-hidden transition-all duration-200',
          ui.sidebarOpen ? 'md:ml-0' : ''
        )
      "
    >
      <slot>
        <router-view />
      </slot>
    </main>
  </div>
</template>
