<script setup>
import SkeletonLine from '@/components/ui/SkeletonLine.vue'
</script>

<template>
  <div class="flex flex-col gap-1 px-2 py-2">
    <div
      v-for="i in 8"
      :key="i"
      class="flex items-start gap-2 rounded-lg px-3 py-2"
    >
      <!-- Icon placeholder -->
      <div class="mt-0.5 h-4 w-4 shrink-0 animate-pulse rounded bg-gray-200 dark:bg-gray-700" />

      <!-- Text lines -->
      <div class="flex flex-1 flex-col gap-1.5">
        <SkeletonLine
          :width="i % 3 === 0 ? '60%' : i % 2 === 0 ? '80%' : '70%'"
          height="0.75rem"
        />
        <SkeletonLine width="40%" height="0.625rem" />
      </div>
    </div>
  </div>
</template>
