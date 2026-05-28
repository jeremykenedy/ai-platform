<script setup>
  import { ref, onMounted, onUnmounted } from 'vue'

  const elapsed = ref(0)
  let timer = null

  onMounted(() => {
    const start = Date.now()
    timer = setInterval(() => {
      elapsed.value = Math.floor((Date.now() - start) / 1000)
    }, 1000)
  })

  onUnmounted(() => {
    if (timer) clearInterval(timer)
  })
</script>

<template>
  <div
    class="mx-4 my-3 flex items-center gap-3 rounded-lg border border-border bg-muted/60 px-4 py-3 dark:border-border dark:bg-muted/40"
    aria-live="polite"
  >
    <div class="flex items-end gap-1">
      <span
        class="streaming-dot block h-2 w-2 rounded-full bg-primary dark:bg-primary"
      />
      <span
        class="streaming-dot streaming-dot--delay-1 block h-2 w-2 rounded-full bg-primary dark:bg-primary"
      />
      <span
        class="streaming-dot streaming-dot--delay-2 block h-2 w-2 rounded-full bg-primary dark:bg-primary"
      />
    </div>
    <span class="text-sm font-medium text-foreground dark:text-foreground">
      Generating response…
    </span>
    <span class="ml-auto text-xs tabular-nums text-muted-foreground dark:text-muted-foreground">
      {{ elapsed }}s
    </span>
  </div>
</template>

<style scoped>
  .streaming-dot {
    animation: streaming-pulse 1.4s ease-in-out infinite;
  }

  .streaming-dot--delay-1 {
    animation-delay: 0.2s;
  }

  .streaming-dot--delay-2 {
    animation-delay: 0.4s;
  }

  @keyframes streaming-pulse {
    0%,
    80%,
    100% {
      transform: scale(0.6);
      opacity: 0.4;
    }

    40% {
      transform: scale(1);
      opacity: 1;
    }
  }
</style>
