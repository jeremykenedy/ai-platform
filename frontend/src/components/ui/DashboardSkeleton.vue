<script setup>
import SkeletonLine from '@/components/ui/SkeletonLine.vue'

const row1 = ['100%', '100%', '100%', '100%']
const row2 = ['100%', '100%', '100%', '100%']

const activityItems = 5
</script>

<template>
  <div class="flex flex-col gap-6 p-6">
    <!-- Stat cards row 1 -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div
        v-for="(_, i) in row1"
        :key="`r1-${i}`"
        class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900"
      >
        <div class="flex items-center justify-between">
          <SkeletonLine width="45%" height="0.75rem" />
          <div class="h-8 w-8 animate-pulse rounded-lg bg-gray-200 dark:bg-gray-700" />
        </div>
        <SkeletonLine width="55%" height="1.5rem" />
        <SkeletonLine width="35%" height="0.625rem" />
      </div>
    </div>

    <!-- Stat cards row 2 -->
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <div
        v-for="(_, i) in row2"
        :key="`r2-${i}`"
        class="flex flex-col gap-3 rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900"
      >
        <div class="flex items-center justify-between">
          <SkeletonLine width="50%" height="0.75rem" />
          <div class="h-8 w-8 animate-pulse rounded-lg bg-gray-200 dark:bg-gray-700" />
        </div>
        <SkeletonLine width="45%" height="1.5rem" />
        <SkeletonLine width="30%" height="0.625rem" />
      </div>
    </div>

    <!-- Activity list -->
    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
      <!-- Header -->
      <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
        <SkeletonLine width="20%" height="1rem" />
      </div>

      <!-- Activity items -->
      <div class="flex flex-col divide-y divide-gray-100 dark:divide-gray-800">
        <div
          v-for="i in activityItems"
          :key="`activity-${i}`"
          class="flex items-center gap-4 px-6 py-4"
        >
          <div class="h-8 w-8 shrink-0 animate-pulse rounded-full bg-gray-200 dark:bg-gray-700" />
          <div class="flex flex-1 flex-col gap-1.5">
            <SkeletonLine
              :width="i % 2 === 0 ? '55%' : '70%'"
              height="0.75rem"
            />
            <SkeletonLine width="30%" height="0.625rem" />
          </div>
          <SkeletonLine width="4rem" height="0.75rem" />
        </div>
      </div>
    </div>
  </div>
</template>
