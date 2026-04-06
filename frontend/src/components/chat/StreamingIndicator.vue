<script setup>
  // No props or logic needed — pure CSS animation
</script>

<template>
  <div class="flex items-center gap-1 px-4 py-3">
    <div class="flex items-end gap-1">
      <span
        class="streaming-dot block h-2 w-2 rounded-full bg-muted-foreground/60 dark:bg-muted-foreground/50"
      />
      <span
        class="streaming-dot streaming-dot--delay-1 block h-2 w-2 rounded-full bg-muted-foreground/60 dark:bg-muted-foreground/50"
      />
      <span
        class="streaming-dot streaming-dot--delay-2 block h-2 w-2 rounded-full bg-muted-foreground/60 dark:bg-muted-foreground/50"
      />
    </div>
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
