<script setup>
  import { ref, onErrorCaptured } from 'vue'
  import { AlertOctagon, RotateCcw } from 'lucide-vue-next'

  defineProps({
    fallbackMessage: {
      type: String,
      default: 'Something went wrong',
    },
  })

  const error = ref(null)
  const errorInfo = ref(null)

  onErrorCaptured((err, instance, info) => {
    error.value = err
    errorInfo.value = info
    console.error('[ErrorBoundary] Captured error:', err, '\nComponent info:', info)
    return false
  })

  function reset() {
    error.value = null
    errorInfo.value = null
  }
</script>

<template>
  <template v-if="error">
    <slot name="fallback" :error="error" :reset="reset">
      <div
        class="flex flex-col items-center justify-center gap-4 rounded-xl border border-red-200 bg-red-50 p-8 text-center dark:border-red-800 dark:bg-red-950/20"
      >
        <AlertOctagon class="h-10 w-10 text-red-500 dark:text-red-400" />
        <div class="flex flex-col gap-1">
          <p class="text-sm font-semibold text-gray-900 dark:text-gray-50">
            {{ fallbackMessage }}
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ error.message }}
          </p>
        </div>
        <button
          class="inline-flex items-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 dark:bg-red-700 dark:hover:bg-red-600"
          @click="reset"
        >
          <RotateCcw class="h-4 w-4" />
          Try Again
        </button>
      </div>
    </slot>
  </template>
  <template v-else>
    <slot />
  </template>
</template>
