<script setup>
  import { ref, computed } from 'vue'
  import { ChevronDown, ChevronRight, Zap, CheckCircle2, XCircle, Clock } from 'lucide-vue-next'

  const props = defineProps({
    toolCall: {
      type: Object,
      required: true,
      // { integration_name, tool_name, status, duration_ms, input, output }
    },
  })

  const expanded = ref(false)

  const statusConfig = computed(() => {
    switch (props.toolCall.status) {
      case 'success':
        return {
          label: 'Success',
          icon: CheckCircle2,
          classes: 'text-green-600 dark:text-green-400',
          badgeClasses:
            'bg-green-50 text-green-700 ring-green-600/20 dark:bg-green-950/30 dark:text-green-400 dark:ring-green-400/20',
        }
      case 'error':
        return {
          label: 'Error',
          icon: XCircle,
          classes: 'text-red-600 dark:text-red-400',
          badgeClasses:
            'bg-red-50 text-red-700 ring-red-600/20 dark:bg-red-950/30 dark:text-red-400 dark:ring-red-400/20',
        }
      default:
        return {
          label: 'Pending',
          icon: Clock,
          classes: 'text-yellow-600 dark:text-yellow-400',
          badgeClasses:
            'bg-yellow-50 text-yellow-700 ring-yellow-600/20 dark:bg-yellow-950/30 dark:text-yellow-400 dark:ring-yellow-400/20',
        }
    }
  })

  const formattedInput = computed(() => {
    try {
      return JSON.stringify(props.toolCall.input, null, 2)
    } catch {
      return String(props.toolCall.input ?? '')
    }
  })

  const formattedOutput = computed(() => {
    try {
      return JSON.stringify(props.toolCall.output, null, 2)
    } catch {
      return String(props.toolCall.output ?? '')
    }
  })

  const durationLabel = computed(() => {
    if (!props.toolCall.duration_ms) return null
    if (props.toolCall.duration_ms < 1000) return `${props.toolCall.duration_ms}ms`
    return `${(props.toolCall.duration_ms / 1000).toFixed(2)}s`
  })
</script>

<template>
  <div
    class="my-2 overflow-hidden rounded-lg border border-border bg-muted/30 text-sm dark:border-border dark:bg-muted/20"
  >
    <!-- Header -->
    <button
      class="flex w-full items-center gap-2 px-3 py-2 text-left hover:bg-muted/50 dark:hover:bg-muted/30"
      @click="expanded = !expanded"
    >
      <component
        :is="expanded ? ChevronDown : ChevronRight"
        class="h-3.5 w-3.5 shrink-0 text-muted-foreground dark:text-muted-foreground"
      />

      <Zap class="h-3.5 w-3.5 shrink-0 text-muted-foreground dark:text-muted-foreground" />

      <span class="font-medium text-foreground dark:text-foreground">
        {{ toolCall.integration_name }}
      </span>
      <span class="text-muted-foreground dark:text-muted-foreground">/</span>
      <span class="font-mono text-foreground dark:text-foreground">{{ toolCall.tool_name }}</span>

      <span
        class="ml-auto inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset"
        :class="statusConfig.badgeClasses"
      >
        <component :is="statusConfig.icon" class="h-3 w-3" />
        {{ statusConfig.label }}
      </span>

      <span
        v-if="durationLabel"
        class="ml-1 text-xs text-muted-foreground dark:text-muted-foreground"
      >
        {{ durationLabel }}
      </span>
    </button>

    <!-- Expandable body -->
    <div v-if="expanded" class="border-t border-border dark:border-border">
      <!-- Input -->
      <div class="px-3 py-2">
        <p
          class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground dark:text-muted-foreground"
        >
          Input
        </p>
        <pre
          class="overflow-x-auto rounded-md bg-background p-2 text-xs text-foreground dark:bg-background dark:text-foreground"
        ><code>{{ formattedInput }}</code></pre>
      </div>

      <!-- Output -->
      <div
        v-if="toolCall.output !== undefined && toolCall.output !== null"
        class="border-t border-border px-3 py-2 dark:border-border"
      >
        <p
          class="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground dark:text-muted-foreground"
        >
          Output
        </p>
        <pre
          class="overflow-x-auto rounded-md bg-background p-2 text-xs dark:bg-background"
          :class="
            toolCall.status === 'error'
              ? 'text-red-600 dark:text-red-400'
              : 'text-foreground dark:text-foreground'
          "
        ><code>{{ formattedOutput }}</code></pre>
      </div>
    </div>
  </div>
</template>
