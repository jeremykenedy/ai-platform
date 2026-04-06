<script setup>
  defineProps({
    width: {
      type: String,
      default: '100%',
    },
    height: {
      type: String,
      default: '1rem',
    },
  })
</script>

<template>
  <div class="animate-pulse rounded bg-gray-200 dark:bg-gray-700" :style="{ width, height }" />
</template>
