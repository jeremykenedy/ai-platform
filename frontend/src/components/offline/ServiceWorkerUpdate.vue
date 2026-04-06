<script setup>
  import { useRegisterSW } from 'virtual:pwa-register/vue'
  import { RefreshCw, X } from 'lucide-vue-next'

  const { needRefresh, updateSW } = useRegisterSW()

  function reload() {
    updateSW(true)
  }

  function dismiss() {
    needRefresh.value = false
  }
</script>

<template>
  <Teleport to="body">
    <Transition name="slide-up">
      <div
        v-if="needRefresh"
        class="fixed bottom-4 right-4 z-50 w-80 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 shadow-lg dark:border-blue-800 dark:bg-blue-950"
        role="status"
        aria-live="polite"
      >
        <div class="flex items-start gap-3">
          <RefreshCw class="mt-0.5 h-4 w-4 shrink-0 text-blue-600 dark:text-blue-400" />
          <div class="flex-1">
            <p class="text-sm font-semibold text-blue-900 dark:text-blue-100">
              A new version is available
            </p>
            <p class="mt-0.5 text-xs text-blue-700 dark:text-blue-300">
              Reload to apply the update.
            </p>
          </div>
          <button
            class="shrink-0 rounded p-0.5 text-blue-400 transition-colors hover:text-blue-600 dark:text-blue-500 dark:hover:text-blue-300"
            aria-label="Dismiss update notification"
            @click="dismiss"
          >
            <X class="h-4 w-4" />
          </button>
        </div>
        <div class="mt-3 flex gap-2">
          <button
            class="flex-1 rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
            @click="reload"
          >
            Reload
          </button>
          <button
            class="flex-1 rounded-lg border border-blue-300 px-3 py-1.5 text-sm font-medium text-blue-700 transition-colors hover:bg-blue-100 dark:border-blue-700 dark:text-blue-300 dark:hover:bg-blue-900"
            @click="dismiss"
          >
            Dismiss
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
  .slide-up-enter-active {
    transition:
      transform 0.3s ease,
      opacity 0.3s ease;
  }

  .slide-up-leave-active {
    transition:
      transform 0.25s ease,
      opacity 0.25s ease;
  }

  .slide-up-enter-from {
    transform: translateY(100%);
    opacity: 0;
  }

  .slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
  }
</style>
