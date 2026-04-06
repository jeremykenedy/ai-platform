<script setup>
  import { computed, ref, watch } from 'vue'
  import { CheckCircle, XCircle, AlertTriangle, Info, Bell, X } from 'lucide-vue-next'
  import { useToast } from '@/composables/useToast'

  const { toasts, dismiss } = useToast()

  const visibleToasts = computed(() => toasts.value.slice(-3))

  const variantConfig = {
    success: {
      container: 'bg-white dark:bg-gray-900 border-green-200 dark:border-green-800',
      iconClass: 'text-green-500 dark:text-green-400',
      bar: 'bg-green-500 dark:bg-green-400',
      title: 'text-gray-900 dark:text-gray-50',
      desc: 'text-gray-500 dark:text-gray-400',
    },
    error: {
      container: 'bg-white dark:bg-gray-900 border-red-200 dark:border-red-800',
      iconClass: 'text-red-500 dark:text-red-400',
      bar: 'bg-red-500 dark:bg-red-400',
      title: 'text-gray-900 dark:text-gray-50',
      desc: 'text-gray-500 dark:text-gray-400',
    },
    warning: {
      container: 'bg-white dark:bg-gray-900 border-yellow-200 dark:border-yellow-800',
      iconClass: 'text-yellow-500 dark:text-yellow-400',
      bar: 'bg-yellow-500 dark:bg-yellow-400',
      title: 'text-gray-900 dark:text-gray-50',
      desc: 'text-gray-500 dark:text-gray-400',
    },
    info: {
      container: 'bg-white dark:bg-gray-900 border-blue-200 dark:border-blue-800',
      iconClass: 'text-blue-500 dark:text-blue-400',
      bar: 'bg-blue-500 dark:bg-blue-400',
      title: 'text-gray-900 dark:text-gray-50',
      desc: 'text-gray-500 dark:text-gray-400',
    },
    default: {
      container: 'bg-white dark:bg-gray-900 border-gray-200 dark:border-gray-700',
      iconClass: 'text-gray-500 dark:text-gray-400',
      bar: 'bg-gray-500 dark:bg-gray-400',
      title: 'text-gray-900 dark:text-gray-50',
      desc: 'text-gray-500 dark:text-gray-400',
    },
  }

  const iconComponents = {
    success: CheckCircle,
    error: XCircle,
    warning: AlertTriangle,
    info: Info,
    default: Bell,
  }

  const progressMap = ref({})

  function getConfig(variant) {
    return variantConfig[variant] ?? variantConfig.default
  }

  function getIcon(variant) {
    return iconComponents[variant] ?? iconComponents.default
  }

  function startProgress(toast) {
    if (!toast.duration || toast.duration === 0) return
    const start = Date.now()
    progressMap.value[toast.id] = 100

    const tick = () => {
      const elapsed = Date.now() - start
      const remaining = Math.max(0, 100 - (elapsed / toast.duration) * 100)
      progressMap.value[toast.id] = remaining
      if (remaining > 0) {
        requestAnimationFrame(tick)
      }
    }
    requestAnimationFrame(tick)
  }

  watch(
    () => toasts.value,
    (next, prev) => {
      const prevIds = new Set((prev ?? []).map((t) => t.id))
      for (const t of next) {
        if (!prevIds.has(t.id)) {
          startProgress(t)
        }
      }
    },
    { deep: true }
  )
</script>

<template>
  <Teleport to="body">
    <div class="fixed bottom-4 right-4 z-50 flex flex-col gap-2">
      <TransitionGroup name="toast" tag="div" class="flex flex-col gap-2">
        <div
          v-for="toast in visibleToasts"
          :key="toast.id"
          class="relative flex w-80 overflow-hidden rounded-lg border shadow-lg"
          :class="getConfig(toast.variant ?? 'default').container"
        >
          <div class="flex w-full gap-3 px-4 py-3">
            <!-- Icon -->
            <component
              :is="getIcon(toast.variant ?? 'default')"
              class="mt-0.5 h-5 w-5 shrink-0"
              :class="getConfig(toast.variant ?? 'default').iconClass"
            />

            <!-- Text -->
            <div class="flex min-w-0 flex-1 flex-col gap-0.5">
              <p
                v-if="toast.title"
                class="text-sm font-semibold leading-tight"
                :class="getConfig(toast.variant ?? 'default').title"
              >
                {{ toast.title }}
              </p>
              <p
                v-if="toast.description"
                class="text-sm"
                :class="getConfig(toast.variant ?? 'default').desc"
              >
                {{ toast.description }}
              </p>
            </div>

            <!-- Close button -->
            <button
              class="ml-1 shrink-0 rounded p-0.5 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
              aria-label="Dismiss"
              @click="dismiss(toast.id)"
            >
              <X class="h-4 w-4" />
            </button>
          </div>

          <!-- Countdown bar -->
          <div
            v-if="toast.duration && toast.duration !== 0"
            class="absolute bottom-0 left-0 h-0.5 transition-none"
            :class="getConfig(toast.variant ?? 'default').bar"
            :style="{ width: (progressMap[toast.id] ?? 100) + '%' }"
          />
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
  .toast-enter-active {
    transition: all 0.3s ease;
  }

  .toast-leave-active {
    transition: all 0.25s ease;
  }

  .toast-enter-from {
    opacity: 0;
    transform: translateX(100%);
  }

  .toast-leave-to {
    opacity: 0;
    transform: translateX(100%);
  }

  .toast-move {
    transition: transform 0.2s ease;
  }
</style>
