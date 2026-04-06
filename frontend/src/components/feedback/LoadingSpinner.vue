<script setup>
  import { computed } from 'vue'

  const props = defineProps({
    size: {
      type: String,
      default: 'md',
      validator: (v) => ['sm', 'md', 'lg'].includes(v),
    },
    class: {
      type: String,
      default: '',
    },
  })

  const px = computed(() => ({ sm: 16, md: 24, lg: 32 })[props.size])
</script>

<template>
  <svg
    :width="px"
    :height="px"
    viewBox="0 0 24 24"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    class="animate-spin"
    :class="props.class"
    aria-label="Loading"
    role="status"
  >
    <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-opacity="0.25" />
    <path
      d="M12 2a10 10 0 0 1 10 10"
      stroke="currentColor"
      stroke-width="3"
      stroke-linecap="round"
    />
  </svg>
</template>
