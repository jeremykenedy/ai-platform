<script setup>
import { onMounted } from 'vue'
import { useOnline } from '@vueuse/core'
import { useMobile } from '@/composables/useMobile'
import { useTheme } from '@/composables/useTheme'

const online = useOnline()
useMobile()
useTheme()

onMounted(() => {
  document.documentElement.classList.add('antialiased')
})
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <div
      v-if="!online"
      class="fixed top-0 left-0 right-0 z-50 bg-yellow-500 px-4 py-2 text-center text-sm text-black dark:bg-yellow-600"
    >
      You are offline. Cached conversations are available read-only. New messages will be sent when
      you reconnect.
    </div>
    <router-view />
  </div>
</template>
