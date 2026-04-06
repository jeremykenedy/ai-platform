<script setup>
  import SkeletonLine from '@/components/ui/SkeletonLine.vue'

  const messages = [
    { role: 'user', lines: [{ w: '55%' }, { w: '40%' }] },
    { role: 'assistant', lines: [{ w: '75%' }, { w: '65%' }, { w: '50%' }] },
    { role: 'user', lines: [{ w: '35%' }] },
    { role: 'assistant', lines: [{ w: '70%' }, { w: '55%' }, { w: '30%' }] },
  ]
</script>

<template>
  <div class="flex flex-col gap-4 px-4 py-6">
    <div
      v-for="(msg, i) in messages"
      :key="i"
      class="flex gap-3"
      :class="msg.role === 'user' ? 'flex-row-reverse' : 'flex-row'"
    >
      <!-- Avatar circle -->
      <div
        class="mt-0.5 h-7 w-7 shrink-0 animate-pulse rounded-full bg-gray-200 dark:bg-gray-700"
      />

      <!-- Content block -->
      <div
        class="flex max-w-[70%] flex-col gap-2 rounded-2xl px-3.5 py-2.5"
        :class="
          msg.role === 'user' ? 'bg-gray-200 dark:bg-gray-700' : 'bg-gray-100 dark:bg-gray-800'
        "
      >
        <SkeletonLine v-for="(line, li) in msg.lines" :key="li" :width="line.w" height="0.75rem" />
      </div>
    </div>
  </div>
</template>
