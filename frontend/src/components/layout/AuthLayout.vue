<script setup>
  import { cn } from '@/lib/utils'
  import { MessageSquare } from 'lucide-vue-next'

  defineProps({
    title: {
      type: String,
      default: '',
    },
    subtitle: {
      type: String,
      default: '',
    },
  })
</script>

<template>
  <div
    :class="cn('flex min-h-screen items-center justify-center p-4', 'bg-gray-50 dark:bg-gray-950')"
  >
    <div
      :class="
        cn(
          'w-full max-w-md rounded-2xl shadow-lg',
          'bg-white dark:bg-gray-900',
          'border border-gray-200 dark:border-gray-800',
          'p-8'
        )
      "
    >
      <div class="mb-8 flex flex-col items-center gap-2 text-center">
        <div
          :class="
            cn(
              'flex h-12 w-12 items-center justify-center rounded-xl',
              'bg-primary text-primary-foreground'
            )
          "
        >
          <MessageSquare class="h-6 w-6" />
        </div>
        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-gray-50">
          {{ title || 'AI Platform' }}
        </h1>
        <p v-if="subtitle" class="text-sm text-gray-500 dark:text-gray-400">
          {{ subtitle }}
        </p>
      </div>

      <slot />
    </div>
  </div>
</template>
