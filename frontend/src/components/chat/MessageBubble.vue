<script setup>
  import { ref, computed } from 'vue'
  import { Bot, User, AlertTriangle, ChevronRight } from 'lucide-vue-next'
  import { useSettingsStore } from '@/stores/settings'
  import MessageActions from '@/components/chat/MessageActions.vue'
  import ToolCallDisplay from '@/components/chat/ToolCallDisplay.vue'

  const props = defineProps({
    message: {
      type: Object,
      required: true,
      // { id, role, content, tokens_used, finish_reason, model_version, created_at, attachments, isStreaming, tool_calls }
    },
    isLast: {
      type: Boolean,
      default: false,
    },
  })

  const emit = defineEmits(['edit', 'regenerate', 'delete', 'continue'])

  const settingsStore = useSettingsStore()
  const hovered = ref(false)

  const isUser = computed(() => props.message.role === 'user')
  const isAssistant = computed(() => props.message.role === 'assistant')

  const showTokenCount = computed(
    () => settingsStore.settings?.show_token_counts && props.message.tokens_used
  )

  const finishWarning = computed(() => {
    switch (props.message.finish_reason) {
      case 'length':
        return 'Truncated'
      case 'content_filter':
        return 'Filtered'
      case 'error':
        return 'Error'
      default:
        return null
    }
  })

  const formattedTime = computed(() => {
    if (!props.message.created_at) return ''
    return new Date(props.message.created_at).toLocaleTimeString([], {
      hour: '2-digit',
      minute: '2-digit',
    })
  })

  const imageAttachments = computed(() =>
    (props.message.attachments ?? []).filter((a) => a.mime_type?.startsWith('image/'))
  )

  const fileAttachments = computed(() =>
    (props.message.attachments ?? []).filter((a) => !a.mime_type?.startsWith('image/'))
  )

  function handleEdit() {
    emit('edit', props.message)
  }

  function handleRegenerate() {
    emit('regenerate', props.message)
  }

  function handleDelete() {
    emit('delete', props.message)
  }

  function handleContinue() {
    emit('continue', props.message)
  }
</script>

<template>
  <div
    class="group relative px-4 py-3"
    :class="isUser ? 'flex flex-row-reverse gap-3' : 'flex flex-row gap-3'"
    @mouseenter="hovered = true"
    @mouseleave="hovered = false"
  >
    <!-- Avatar -->
    <div
      class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold"
      :class="
        isUser
          ? 'bg-primary text-primary-foreground dark:bg-primary dark:text-primary-foreground'
          : 'bg-muted text-muted-foreground dark:bg-muted dark:text-muted-foreground'
      "
    >
      <User v-if="isUser" class="h-4 w-4" />
      <Bot v-else class="h-4 w-4" />
    </div>

    <!-- Bubble content -->
    <div
      class="flex min-w-0 max-w-[80%] flex-col gap-1"
      :class="isUser ? 'items-end' : 'items-start'"
    >
      <!-- Image attachments -->
      <div v-if="imageAttachments.length" class="flex flex-wrap gap-2">
        <img
          v-for="att in imageAttachments"
          :key="att.id ?? att.name"
          :src="att.url"
          :alt="att.name"
          class="max-h-48 max-w-xs rounded-lg border border-border object-cover dark:border-border"
        />
      </div>

      <!-- File attachment chips -->
      <div v-if="fileAttachments.length" class="flex flex-wrap gap-1.5">
        <span
          v-for="att in fileAttachments"
          :key="att.id ?? att.name"
          class="inline-flex items-center gap-1 rounded-md border border-border bg-muted px-2 py-1 text-xs text-muted-foreground dark:border-border dark:bg-muted dark:text-muted-foreground"
        >
          {{ att.name }}
        </span>
      </div>

      <!-- Tool calls -->
      <div v-if="message.tool_calls?.length" class="w-full">
        <ToolCallDisplay
          v-for="tc in message.tool_calls"
          :key="tc.id ?? tc.tool_name"
          :tool-call="tc"
        />
      </div>

      <!-- Message bubble -->
      <div
        class="relative rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed"
        :class="
          isUser
            ? 'bg-primary text-primary-foreground dark:bg-primary dark:text-primary-foreground'
            : 'bg-muted text-foreground dark:bg-muted dark:text-foreground'
        "
      >
        <!-- Streaming content with cursor -->
        <template v-if="message.isStreaming">
          <span class="whitespace-pre-wrap break-words">{{ message.content }}</span>
          <span
            class="ml-0.5 inline-block h-4 w-0.5 animate-pulse bg-current align-middle opacity-75"
          />
        </template>

        <!-- Finalized content via markdown renderer slot -->
        <template v-else>
          <div class="prose prose-sm max-w-none dark:prose-invert" v-html="message.content" />
        </template>
      </div>

      <!-- Meta row -->
      <div class="flex flex-wrap items-center gap-2 px-1">
        <!-- Timestamp -->
        <span
          class="text-xs text-muted-foreground/60 opacity-0 transition-opacity group-hover:opacity-100 dark:text-muted-foreground/50"
        >
          {{ formattedTime }}
        </span>

        <!-- Token count badge -->
        <span
          v-if="showTokenCount"
          class="rounded-full bg-muted px-1.5 py-0.5 text-xs text-muted-foreground dark:bg-muted dark:text-muted-foreground"
        >
          {{ message.tokens_used }} tokens
        </span>

        <!-- Model version badge (assistant only) -->
        <span
          v-if="isAssistant && message.model_version && !message.isStreaming"
          class="rounded-full bg-muted px-1.5 py-0.5 text-xs text-muted-foreground dark:bg-muted dark:text-muted-foreground"
        >
          {{ message.model_version }}
        </span>

        <!-- Warning badge -->
        <span
          v-if="finishWarning"
          class="inline-flex items-center gap-1 rounded-full bg-yellow-50 px-1.5 py-0.5 text-xs font-medium text-yellow-700 ring-1 ring-yellow-600/20 dark:bg-yellow-950/30 dark:text-yellow-400 dark:ring-yellow-400/20"
        >
          <AlertTriangle class="h-3 w-3" />
          {{ finishWarning }}
        </span>

        <!-- Continue button for length truncation -->
        <button
          v-if="message.finish_reason === 'length' && isLast"
          class="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary transition-colors hover:bg-primary/20 dark:bg-primary/10 dark:text-primary dark:hover:bg-primary/20"
          @click="handleContinue"
        >
          Continue
          <ChevronRight class="h-3 w-3" />
        </button>
      </div>

      <!-- Hover actions -->
      <div
        v-if="!message.isStreaming && hovered"
        class="mt-0.5"
        :class="isUser ? 'self-end' : 'self-start'"
      >
        <MessageActions
          :message="message"
          :is-last="isLast"
          @edit="handleEdit"
          @regenerate="handleRegenerate"
          @delete="handleDelete"
        />
      </div>
    </div>
  </div>
</template>
