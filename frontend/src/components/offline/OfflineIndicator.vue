<script setup>
import { ref, watch } from 'vue'
import { useOnline } from '@vueuse/core'
import { WifiOff } from 'lucide-vue-next'

const online = useOnline()
const showOffline = ref(!online.value)
const showBackOnline = ref(false)
let backOnlineTimer = null

watch(online, (isOnline) => {
  if (isOnline) {
    showOffline.value = false
    showBackOnline.value = true
    clearTimeout(backOnlineTimer)
    backOnlineTimer = setTimeout(() => {
      showBackOnline.value = false
    }, 3000)
  } else {
    showBackOnline.value = false
    clearTimeout(backOnlineTimer)
    showOffline.value = true
  }
})
</script>

<template>
  <Teleport to="body">
    <Transition name="slide-down">
      <div
        v-if="showOffline"
        class="fixed left-0 right-0 top-0 z-50 flex items-center justify-center gap-2 bg-yellow-400 px-4 py-2 text-center text-sm font-medium text-black dark:bg-yellow-500 dark:text-black"
        role="status"
        aria-live="polite"
      >
        <WifiOff class="h-4 w-4 shrink-0" />
        <span>
          You are offline. Cached conversations are available read-only. New messages will be sent
          when you reconnect.
        </span>
      </div>
    </Transition>

    <Transition name="slide-down">
      <div
        v-if="showBackOnline"
        class="fixed left-0 right-0 top-0 z-50 flex items-center justify-center gap-2 bg-green-500 px-4 py-2 text-center text-sm font-medium text-white dark:bg-green-600 dark:text-white"
        role="status"
        aria-live="polite"
      >
        <span>Back online</span>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.slide-down-enter-active {
  transition: transform 0.3s ease, opacity 0.3s ease;
}
.slide-down-leave-active {
  transition: transform 0.25s ease, opacity 0.25s ease;
}
.slide-down-enter-from {
  transform: translateY(-100%);
  opacity: 0;
}
.slide-down-leave-to {
  transform: translateY(-100%);
  opacity: 0;
}
</style>
