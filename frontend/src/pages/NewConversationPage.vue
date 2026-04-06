<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Sparkles,
  Code2,
  FileText,
  Lightbulb,
  MessageSquare,
  BookOpen,
  ChevronRight,
  Cpu,
  UserCircle,
} from 'lucide-vue-next'
import ChatInput from '@/components/chat/ChatInput.vue'
import ModelSelector from '@/components/selectors/ModelSelector.vue'
import PersonaSelector from '@/components/selectors/PersonaSelector.vue'
import { useConversation } from '@/composables/useConversation'
import { useConversationsStore } from '@/stores/conversations'
import { useModelsStore } from '@/stores/models'
import { usePersonasStore } from '@/stores/personas'

const router = useRouter()
const { send } = useConversation()
const conversationsStore = useConversationsStore()
const modelsStore = useModelsStore()
const personasStore = usePersonasStore()

const showModelSelector = ref(false)
const showPersonaSelector = ref(false)
const selectedModelId = ref(null)
const selectedPersonaId = ref(null)

const activeModel = computed(
  () => modelsStore.models.find((m) => m.id === selectedModelId.value) ?? modelsStore.defaultModel,
)

const activePersona = computed(
  () => personasStore.personas.find((p) => p.id === selectedPersonaId.value) ?? null,
)

const recentConversations = computed(() => conversationsStore.sortedConversations.slice(0, 5))

const SUGGESTIONS = [
  { icon: Code2, label: 'Write a Python script', prompt: 'Write a Python script that reads a CSV file and outputs a summary of the data.' },
  { icon: Lightbulb, label: 'Help me brainstorm', prompt: 'Help me brainstorm ideas for a new side project I could build in a weekend.' },
  { icon: Sparkles, label: 'Explain a concept', prompt: 'Explain quantum computing in simple terms, as if I have no physics background.' },
  { icon: FileText, label: 'Summarize a document', prompt: 'I will paste a document below. Please summarize its key points in bullet form.' },
  { icon: MessageSquare, label: 'Draft an email', prompt: 'Help me draft a professional email requesting a meeting with a potential client.' },
  { icon: BookOpen, label: 'Teach me something', prompt: 'Teach me the basics of SQL joins with clear examples.' },
]

onMounted(async () => {
  conversationsStore.setActive(null)
  if (!modelsStore.models.length) await modelsStore.fetch()
  if (!personasStore.personas.length) await personasStore.fetch()
  if (!conversationsStore.conversations.length) await conversationsStore.fetch()
  selectedModelId.value = modelsStore.defaultModel?.id ?? null
})

async function handleSend(content, attachments) {
  if (!content.trim()) return
  await send(content, {
    model: selectedModelId.value,
    personaId: selectedPersonaId.value,
    attachments,
  })
}

async function sendSuggestion(prompt) {
  await send(prompt, {
    model: selectedModelId.value,
    personaId: selectedPersonaId.value,
  })
}

function goToConversation(id) {
  router.push(`/c/${id}`)
}

function formatRelativeTime(dateStr) {
  const date = new Date(dateStr)
  const now = new Date()
  const diffMs = now - date
  const diffMins = Math.floor(diffMs / 60000)
  const diffHours = Math.floor(diffMins / 60)
  const diffDays = Math.floor(diffHours / 24)
  if (diffMins < 1) return 'just now'
  if (diffMins < 60) return `${diffMins}m ago`
  if (diffHours < 24) return `${diffHours}h ago`
  if (diffDays === 1) return 'yesterday'
  if (diffDays < 7) return `${diffDays}d ago`
  return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })
}
</script>

<template>
  <div class="flex flex-1 flex-col overflow-hidden">
    <!-- Main centered content -->
    <div class="flex flex-1 flex-col items-center justify-center overflow-y-auto px-4 pb-4 pt-12">
      <!-- Greeting -->
      <div class="mb-8 text-center">
        <div class="mb-4 flex justify-center">
          <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-blue-500 to-violet-600 shadow-lg">
            <Sparkles class="h-7 w-7 text-white" />
          </div>
        </div>
        <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-50">
          What can I help you with?
        </h1>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
          Ask anything, or pick a suggestion below to get started.
        </p>
      </div>

      <!-- Model + Persona selectors -->
      <div class="mb-6 flex flex-wrap items-center justify-center gap-2">
        <button
          class="flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 shadow-sm transition-colors hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-blue-600 dark:hover:bg-blue-950/30 dark:hover:text-blue-400"
          @click="showModelSelector = true"
        >
          <Cpu class="h-3.5 w-3.5" />
          {{ activeModel?.display_name ?? activeModel?.name ?? 'Select model' }}
        </button>

        <button
          class="flex items-center gap-1.5 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-medium text-gray-600 shadow-sm transition-colors hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300 dark:hover:border-violet-600 dark:hover:bg-violet-950/30 dark:hover:text-violet-400"
          @click="showPersonaSelector = true"
        >
          <UserCircle class="h-3.5 w-3.5" />
          {{ activePersona?.name ?? 'No persona' }}
        </button>
      </div>

      <!-- Suggestion chips -->
      <div class="mb-8 grid w-full max-w-2xl grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
        <button
          v-for="s in SUGGESTIONS"
          :key="s.label"
          class="flex items-center gap-2.5 rounded-xl border border-gray-200 bg-white px-4 py-3 text-left text-sm text-gray-700 shadow-sm transition-all hover:border-blue-200 hover:bg-blue-50 hover:shadow-md hover:text-blue-700 dark:border-gray-700 dark:bg-gray-800/60 dark:text-gray-300 dark:hover:border-blue-700/60 dark:hover:bg-blue-950/20 dark:hover:text-blue-400"
          @click="sendSuggestion(s.prompt)"
        >
          <component :is="s.icon" class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" />
          <span class="flex-1 leading-snug">{{ s.label }}</span>
        </button>
      </div>

      <!-- Recent conversations -->
      <div v-if="recentConversations.length" class="w-full max-w-2xl">
        <h2 class="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
          Recent conversations
        </h2>
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800/60">
          <button
            v-for="(convo, index) in recentConversations"
            :key="convo.id"
            class="flex w-full items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-gray-50 dark:hover:bg-gray-700/50"
            :class="{ 'border-t border-gray-100 dark:border-gray-700/70': index > 0 }"
            @click="goToConversation(convo.id)"
          >
            <MessageSquare class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" />
            <span class="flex-1 truncate text-sm text-gray-700 dark:text-gray-300">
              {{ convo.title ?? 'Untitled conversation' }}
            </span>
            <span class="shrink-0 text-xs text-gray-400 dark:text-gray-500">
              {{ formatRelativeTime(convo.updated_at) }}
            </span>
            <ChevronRight class="h-3.5 w-3.5 shrink-0 text-gray-300 dark:text-gray-600" />
          </button>
        </div>
      </div>
    </div>

    <!-- Chat input pinned to bottom -->
    <ChatInput
      @send="handleSend"
      @open-model-selector="showModelSelector = true"
      @open-persona-selector="showPersonaSelector = true"
    />

    <!-- Model selector modal -->
    <ModelSelector
      v-model="selectedModelId"
      :show="showModelSelector"
      @update:show="showModelSelector = $event"
    />

    <!-- Persona selector modal -->
    <PersonaSelector
      v-model="selectedPersonaId"
      :show="showPersonaSelector"
      @update:show="showPersonaSelector = $event"
    />
  </div>
</template>
