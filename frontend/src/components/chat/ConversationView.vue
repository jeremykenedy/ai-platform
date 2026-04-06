<script setup>
  import { ref, computed, watch, nextTick, onMounted } from 'vue'
  import { ChevronDown, MessageSquare, Sparkles } from 'lucide-vue-next'
  import { useMessagesStore } from '@/stores/messages'
  import { useVirtualScroll } from '@/composables/useVirtualScroll'
  import MessageBubble from '@/components/chat/MessageBubble.vue'
  import StreamingIndicator from '@/components/chat/StreamingIndicator.vue'

  const props = defineProps({
    conversationId: {
      type: String,
      required: true,
    },
  })

  const emit = defineEmits([
    'suggest',
    'edit-message',
    'regenerate',
    'delete-message',
    'continue-message',
    'load-older',
  ])

  const messagesStore = useMessagesStore()

  const messages = computed(() => messagesStore.activeMessages)
  const isStreaming = computed(() => messagesStore.isStreaming)

  const { scrollRef, virtualizer } = useVirtualScroll(messages)

  const isAtBottom = ref(true)
  const isLoadingOlder = ref(false)

  const SCROLL_THRESHOLD = 100

  function updateIsAtBottom() {
    const el = scrollRef.value
    if (!el) return
    const distanceFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight
    isAtBottom.value = distanceFromBottom < SCROLL_THRESHOLD
  }

  function scrollToBottom(behavior = 'smooth') {
    nextTick(() => {
      const el = scrollRef.value
      if (!el) return
      el.scrollTo({ top: el.scrollHeight, behavior })
    })
  }

  async function handleScroll() {
    updateIsAtBottom()

    const el = scrollRef.value
    if (!el) return

    if (el.scrollTop < 80 && !isLoadingOlder.value) {
      isLoadingOlder.value = true
      await emit('load-older')
      isLoadingOlder.value = false
    }
  }

  watch(
    () => messages.value.length,
    () => {
      if (isAtBottom.value) {
        scrollToBottom('auto')
      }
    }
  )

  watch(isStreaming, (streaming) => {
    if (streaming && isAtBottom.value) {
      scrollToBottom('auto')
    }
  })

  onMounted(async () => {
    if (props.conversationId) {
      await messagesStore.fetchForConversation(props.conversationId)
      scrollToBottom('auto')
    }
  })

  watch(
    () => props.conversationId,
    async (id) => {
      if (id) {
        await messagesStore.fetchForConversation(id)
        scrollToBottom('auto')
      }
    }
  )

  const suggestions = [
    { icon: Sparkles, text: 'Ask me anything' },
    { icon: MessageSquare, text: 'Start a conversation' },
  ]
</script>

<template>
  <div class="relative flex flex-1 flex-col overflow-hidden">
    <!-- Empty state -->
    <div
      v-if="messages.length === 0 && !isStreaming"
      class="flex flex-1 flex-col items-center justify-center gap-6 px-6 py-12"
    >
      <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-muted dark:bg-muted">
        <Sparkles class="h-7 w-7 text-muted-foreground dark:text-muted-foreground" />
      </div>
      <div class="text-center">
        <h3 class="text-lg font-semibold text-foreground dark:text-foreground">How can I help?</h3>
        <p class="mt-1 text-sm text-muted-foreground dark:text-muted-foreground">
          Send a message to start a conversation.
        </p>
      </div>
      <div class="flex flex-wrap justify-center gap-2">
        <button
          v-for="s in suggestions"
          :key="s.text"
          class="flex items-center gap-2 rounded-xl border border-border bg-muted/50 px-4 py-2.5 text-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground dark:border-border dark:bg-muted/30 dark:text-muted-foreground dark:hover:bg-muted dark:hover:text-foreground"
          @click="emit('suggest', s.text)"
        >
          <component :is="s.icon" class="h-4 w-4" />
          {{ s.text }}
        </button>
      </div>
    </div>

    <!-- Virtual message list -->
    <div v-else ref="scrollRef" class="flex-1 overflow-y-auto" @scroll="handleScroll">
      <!-- Older loading indicator -->
      <div v-if="isLoadingOlder" class="flex justify-center py-3">
        <div
          class="h-5 w-5 animate-spin rounded-full border-2 border-muted-foreground/30 border-t-muted-foreground dark:border-muted-foreground/20 dark:border-t-muted-foreground"
        />
      </div>

      <div :style="{ height: virtualizer.getTotalSize() + 'px', position: 'relative' }">
        <div
          v-for="item in virtualizer.getVirtualItems()"
          :key="item.key"
          :ref="(el) => virtualizer.measureElement(el)"
          :data-index="item.index"
          :style="{
            position: 'absolute',
            top: 0,
            left: 0,
            width: '100%',
            transform: `translateY(${item.start}px)`,
          }"
        >
          <MessageBubble
            v-if="!messages[item.index]?.isStreaming"
            v-once
            :message="messages[item.index]"
            :is-last="item.index === messages.length - 1"
            @edit="emit('edit-message', $event)"
            @regenerate="emit('regenerate', $event)"
            @delete="emit('delete-message', $event)"
            @continue="emit('continue-message', $event)"
          />
          <MessageBubble
            v-else
            :message="messages[item.index]"
            :is-last="item.index === messages.length - 1"
            @edit="emit('edit-message', $event)"
            @regenerate="emit('regenerate', $event)"
            @delete="emit('delete-message', $event)"
            @continue="emit('continue-message', $event)"
          />
        </div>
      </div>

      <StreamingIndicator v-if="isStreaming" />
    </div>

    <!-- Scroll to bottom button -->
    <button
      v-show="!isAtBottom && messages.length > 0"
      class="absolute bottom-24 right-6 flex h-8 w-8 items-center justify-center rounded-full border border-border bg-background shadow-md transition-all hover:bg-muted dark:border-border dark:bg-background dark:hover:bg-muted"
      @click="scrollToBottom('smooth')"
    >
      <ChevronDown class="h-4 w-4 text-muted-foreground dark:text-muted-foreground" />
    </button>
  </div>
</template>
