<script setup>
  import LoadingSpinner from '@/components/feedback/LoadingSpinner.vue'
  import { Bot } from 'lucide-vue-next'

  defineProps({
    message: {
      type: String,
      default: 'Loading...',
    },
  })
</script>

<template>
  <div
    class="fixed inset-0 z-50 flex flex-col items-center justify-center gap-6 bg-background/90 backdrop-blur-sm dark:bg-background/90"
  >
    <!-- App icon -->
    <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary shadow-lg">
      <Bot class="h-9 w-9 text-primary-foreground" />
    </div>

    <!-- Spinner -->
    <LoadingSpinner size="lg" class="text-primary" />

    <!-- Message -->
    <p class="text-sm font-medium text-muted-foreground dark:text-muted-foreground">
      {{ message }}
    </p>
  </div>
</template>
