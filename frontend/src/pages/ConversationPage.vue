<script setup>
  import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { AlertCircle, Cpu, UserCircle, Pencil, Check, X } from 'lucide-vue-next'
  import ConversationView from '@/components/chat/ConversationView.vue'
  import ChatInput from '@/components/chat/ChatInput.vue'
  import ModelSelector from '@/components/selectors/ModelSelector.vue'
  import PersonaSelector from '@/components/selectors/PersonaSelector.vue'
  import { useConversation } from '@/composables/useConversation'
  import { useStreaming } from '@/composables/useStreaming'
  import { useConversationsStore } from '@/stores/conversations'
  import { useMessagesStore } from '@/stores/messages'
  import { useModelsStore } from '@/stores/models'
  import { usePersonasStore } from '@/stores/personas'

  const route = useRoute()
  const router = useRouter()
  const { send } = useConversation()
  const conversationsStore = useConversationsStore()
  const messagesStore = useMessagesStore()
  const modelsStore = useModelsStore()
  const personasStore = usePersonasStore()

  const conversationId = computed(() => route.params.id)
  const conversation = computed(() => conversationsStore.activeConversation)

  const isLoading = ref(false)
  const notFound = ref(false)

  const showModelSelector = ref(false)
  const showPersonaSelector = ref(false)

  // Inline title editing
  const isEditingTitle = ref(false)
  const titleDraft = ref('')
  const titleInputRef = ref(null)

  const displayTitle = computed(() => conversation.value?.title ?? 'Untitled conversation')

  const activeModelId = computed(() =>
    conversation.value?.model_name
      ? (modelsStore.models.find((m) => m.name === conversation.value.model_name)?.id ?? null)
      : (modelsStore.defaultModel?.id ?? null)
  )

  const activeModel = computed(
    () => modelsStore.models.find((m) => m.id === activeModelId.value) ?? modelsStore.defaultModel
  )

  const activePersonaId = computed(() => conversation.value?.persona_id ?? null)
  const activePersona = computed(
    () => personasStore.personas.find((p) => p.id === activePersonaId.value) ?? null
  )

  // WebSocket streaming
  const { connect, disconnect } = useStreaming(conversationId)

  async function loadConversation(id) {
    if (!id) return
    isLoading.value = true
    notFound.value = false
    try {
      conversationsStore.setActive(id)

      // Ensure conversation is in store
      if (!conversationsStore.conversations.find((c) => c.id === id)) {
        if (!conversationsStore.conversations.length) {
          await conversationsStore.fetch()
        }
        // Re-check after fetch
        if (!conversationsStore.conversations.find((c) => c.id === id)) {
          notFound.value = true
          return
        }
      }

      connect({
        onToken(token) {
          messagesStore.appendToken(token)
        },
        onComplete(event) {
          if (event?.message) messagesStore.finalizeMessage(event.message)
        },
        onError() {
          messagesStore.handleStreamError(new Error('Stream failed'))
        },
      })
    } catch {
      notFound.value = true
    } finally {
      isLoading.value = false
    }
  }

  async function handleSend(content, attachments) {
    if (!content.trim()) return
    await send(content, {
      model: activeModelId.value,
      personaId: activePersonaId.value,
      attachments,
    })
  }

  async function handleLoadOlder() {
    const id = conversationId.value
    if (!id) return
    const current = messagesStore.messages.get(id) ?? []
    const oldest = current[0]
    if (!oldest) return
    await messagesStore.fetchForConversation(id, oldest.id)
  }

  function startEditTitle() {
    titleDraft.value = displayTitle.value
    isEditingTitle.value = true
    nextTick(() => titleInputRef.value?.focus())
  }

  async function saveTitle() {
    const trimmed = titleDraft.value.trim()
    if (!trimmed || trimmed === displayTitle.value) {
      isEditingTitle.value = false
      return
    }
    try {
      await conversationsStore.update(conversationId.value, { title: trimmed })
    } catch {
      // revert silently
    } finally {
      isEditingTitle.value = false
    }
  }

  function cancelEditTitle() {
    isEditingTitle.value = false
  }

  function handleTitleKeydown(e) {
    if (e.key === 'Enter') {
      e.preventDefault()
      saveTitle()
    } else if (e.key === 'Escape') {
      cancelEditTitle()
    }
  }

  onMounted(async () => {
    if (!modelsStore.models.length) await modelsStore.fetch()
    if (!personasStore.personas.length) await personasStore.fetch()
    await loadConversation(conversationId.value)
  })

  watch(conversationId, async (newId, oldId) => {
    if (newId === oldId) return
    disconnect()
    await loadConversation(newId)
  })

  onUnmounted(() => {
    disconnect()
    conversationsStore.setActive(null)
  })
</script>

<template>
  <div class="flex flex-1 flex-col overflow-hidden">
    <!-- Loading state -->
    <div v-if="isLoading" class="flex flex-1 items-center justify-center">
      <div class="flex flex-col items-center gap-3">
        <div
          class="h-8 w-8 animate-spin rounded-full border-2 border-gray-200 border-t-blue-600 dark:border-gray-700 dark:border-t-blue-400"
        />
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading conversation...</p>
      </div>
    </div>

    <!-- Not found state -->
    <div v-else-if="notFound" class="flex flex-1 flex-col items-center justify-center gap-4 px-6">
      <div
        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-red-100 dark:bg-red-950/40"
      >
        <AlertCircle class="h-7 w-7 text-red-500 dark:text-red-400" />
      </div>
      <div class="text-center">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
          Conversation not found
        </h2>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
          This conversation may have been deleted or you don't have access to it.
        </p>
      </div>
      <button
        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 dark:bg-blue-600 dark:hover:bg-blue-500"
        @click="router.push('/c/new')"
      >
        Start new conversation
      </button>
    </div>

    <!-- Active conversation -->
    <template v-else>
      <!-- Header -->
      <div
        class="flex shrink-0 items-center gap-2 border-b border-border bg-background/95 px-4 py-2.5 backdrop-blur-sm dark:border-border dark:bg-background/95"
      >
        <!-- Inline title -->
        <div class="flex min-w-0 flex-1 items-center gap-1">
          <template v-if="isEditingTitle">
            <input
              ref="titleInputRef"
              v-model="titleDraft"
              type="text"
              class="min-w-0 flex-1 rounded border border-blue-400 bg-white px-2 py-0.5 text-sm font-medium text-gray-900 outline-none focus:ring-2 focus:ring-blue-500/20 dark:border-blue-600 dark:bg-gray-800 dark:text-gray-100"
              @keydown="handleTitleKeydown"
              @blur="saveTitle"
            />
            <button
              class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-green-600 hover:bg-green-50 dark:text-green-400 dark:hover:bg-green-950/30"
              @click="saveTitle"
            >
              <Check class="h-3.5 w-3.5" />
            </button>
            <button
              class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-gray-400 hover:bg-gray-100 dark:text-gray-500 dark:hover:bg-gray-800"
              @click="cancelEditTitle"
            >
              <X class="h-3.5 w-3.5" />
            </button>
          </template>
          <template v-else>
            <h1
              class="truncate text-sm font-medium text-gray-900 dark:text-gray-100"
              :title="displayTitle"
            >
              {{ displayTitle }}
            </h1>
            <button
              class="flex h-6 w-6 shrink-0 items-center justify-center rounded text-gray-300 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
              title="Rename conversation"
              @click="startEditTitle"
            >
              <Pencil class="h-3 w-3" />
            </button>
          </template>
        </div>

        <!-- Badges -->
        <div class="flex shrink-0 items-center gap-1.5">
          <button
            v-if="activePersona"
            class="flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-700 transition-colors hover:bg-violet-200 dark:bg-violet-900/40 dark:text-violet-300 dark:hover:bg-violet-900/60"
            title="Change persona"
            @click="showPersonaSelector = true"
          >
            <UserCircle class="h-3 w-3" />
            {{ activePersona.name }}
          </button>
          <button
            v-else
            class="flex items-center gap-1 rounded-full border border-gray-200 px-2 py-0.5 text-xs text-gray-400 transition-colors hover:border-gray-300 hover:text-gray-600 dark:border-gray-700 dark:text-gray-500 dark:hover:border-gray-600 dark:hover:text-gray-300"
            title="Set persona"
            @click="showPersonaSelector = true"
          >
            <UserCircle class="h-3 w-3" />
            No persona
          </button>

          <button
            class="flex items-center gap-1 rounded-full border border-gray-200 px-2 py-0.5 text-xs font-medium text-gray-600 transition-colors hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 dark:border-gray-700 dark:text-gray-300 dark:hover:border-blue-600 dark:hover:bg-blue-950/20 dark:hover:text-blue-400"
            title="Change model"
            @click="showModelSelector = true"
          >
            <Cpu class="h-3 w-3" />
            {{ activeModel?.display_name ?? activeModel?.name ?? 'Model' }}
          </button>
        </div>
      </div>

      <!-- Conversation messages -->
      <ConversationView
        v-if="conversationId"
        :conversation-id="conversationId"
        @load-older="handleLoadOlder"
      />

      <!-- Chat input -->
      <ChatInput
        @send="handleSend"
        @open-model-selector="showModelSelector = true"
        @open-persona-selector="showPersonaSelector = true"
      />
    </template>

    <!-- Model selector modal -->
    <ModelSelector
      :model-value="activeModelId"
      :show="showModelSelector"
      @update:show="showModelSelector = $event"
    />

    <!-- Persona selector modal -->
    <PersonaSelector
      :model-value="activePersonaId"
      :show="showPersonaSelector"
      @update:show="showPersonaSelector = $event"
    />
  </div>
</template>
