<script setup>
import { ref, computed, watch, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import {
  Search,
  Check,
  Zap,
  Eye,
  Code2,
  MessageSquare,
  Globe,
  X,
  ChevronDown,
  Cpu,
} from 'lucide-vue-next'
import { useModelsStore } from '@/stores/models'

const props = defineProps({
  modelValue: {
    type: String,
    default: null,
  },
  show: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['update:modelValue', 'update:show'])

const modelsStore = useModelsStore()
const searchQuery = ref('')
const searchInput = ref(null)

watch(
  () => props.show,
  async (val) => {
    if (val) {
      searchQuery.value = ''
      await nextTick()
      searchInput.value?.focus()
    }
  },
)

const PROVIDER_ORDER = ['auto', 'ollama', 'anthropic', 'openai', 'google', 'mistral', 'other']

const PROVIDER_LABELS = {
  auto: 'Auto',
  ollama: 'Ollama (Local)',
  anthropic: 'Anthropic',
  openai: 'OpenAI',
  google: 'Google',
  mistral: 'Mistral',
  other: 'Other',
}

const COST_LABELS = {
  free: 'Free',
  low: '$',
  medium: '$$',
  high: '$$$',
}

const COST_COLORS = {
  free: 'text-emerald-500 dark:text-emerald-400',
  low: 'text-sky-500 dark:text-sky-400',
  medium: 'text-amber-500 dark:text-amber-400',
  high: 'text-rose-500 dark:text-rose-400',
}

function capabilityIcon(cap) {
  const map = {
    vision: Eye,
    code: Code2,
    chat: MessageSquare,
    web: Globe,
    fast: Zap,
    reasoning: Cpu,
  }
  return map[cap] ?? MessageSquare
}

const autoOption = {
  id: 'auto',
  name: 'Auto',
  provider: 'auto',
  description: 'Automatically route to the best model for each request',
  capabilities: ['chat', 'code', 'vision'],
  is_local: false,
  cost_tier: 'free',
  parameters: null,
}

const filteredGrouped = computed(() => {
  const q = searchQuery.value.toLowerCase().trim()

  const allModels = [autoOption, ...modelsStore.availableModels]

  const matched = q
    ? allModels.filter(
        (m) =>
          m.name.toLowerCase().includes(q) ||
          m.provider?.toLowerCase().includes(q) ||
          m.description?.toLowerCase().includes(q),
      )
    : allModels

  const groups = {}
  for (const model of matched) {
    const provider = model.provider ?? 'other'
    if (!groups[provider]) groups[provider] = []
    groups[provider].push(model)
  }

  return PROVIDER_ORDER.filter((p) => groups[p]?.length).map((p) => ({
    provider: p,
    label: PROVIDER_LABELS[p] ?? p,
    models: groups[p],
  }))
})

function select(model) {
  emit('update:modelValue', model.id)
  emit('update:show', false)
}

function close() {
  emit('update:show', false)
}

function formatParams(params) {
  if (!params) return null
  if (params >= 1e9) return `${(params / 1e9).toFixed(0)}B`
  if (params >= 1e6) return `${(params / 1e6).toFixed(0)}M`
  return `${params}`
}
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-start justify-center pt-[10vh]"
        @mousedown.self="close"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/40 dark:bg-black/60" @click="close" />

        <!-- Panel -->
        <div
          class="relative z-10 w-full max-w-lg rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-700 dark:bg-neutral-900"
        >
          <!-- Search -->
          <div class="flex items-center gap-2 border-b border-neutral-200 px-3 dark:border-neutral-700">
            <Search class="h-4 w-4 shrink-0 text-neutral-400" />
            <input
              ref="searchInput"
              v-model="searchQuery"
              placeholder="Search models..."
              class="h-11 flex-1 bg-transparent text-sm text-neutral-900 placeholder-neutral-400 outline-none dark:text-neutral-100"
              @keydown.escape="close"
            />
            <button
              class="rounded p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
              @click="close"
            >
              <X class="h-4 w-4" />
            </button>
          </div>

          <!-- Model list -->
          <div class="max-h-[60vh] overflow-y-auto py-2">
            <template v-if="filteredGrouped.length">
              <div v-for="group in filteredGrouped" :key="group.provider">
                <div
                  class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-neutral-400 dark:text-neutral-500"
                >
                  {{ group.label }}
                </div>

                <button
                  v-for="model in group.models"
                  :key="model.id"
                  class="flex w-full items-start gap-3 px-3 py-2.5 text-left transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-800"
                  :class="{ 'bg-neutral-100 dark:bg-neutral-800': modelValue === model.id }"
                  @click="select(model)"
                >
                  <!-- Check indicator -->
                  <div class="mt-0.5 h-4 w-4 shrink-0">
                    <Check
                      v-if="modelValue === model.id"
                      class="h-4 w-4 text-blue-600 dark:text-blue-400"
                    />
                  </div>

                  <!-- Info -->
                  <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-1.5">
                      <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                        {{ model.name }}
                      </span>

                      <!-- Local badge -->
                      <span
                        v-if="model.provider === 'ollama'"
                        class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400"
                      >
                        Local
                      </span>

                      <!-- Parameter count badge -->
                      <span
                        v-if="formatParams(model.parameters)"
                        class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400"
                      >
                        {{ formatParams(model.parameters) }}
                      </span>

                      <!-- Cost tier (remote models) -->
                      <span
                        v-if="model.provider !== 'ollama' && model.provider !== 'auto' && model.cost_tier"
                        class="text-xs font-medium"
                        :class="COST_COLORS[model.cost_tier] ?? 'text-neutral-400'"
                      >
                        {{ COST_LABELS[model.cost_tier] ?? model.cost_tier }}
                      </span>
                    </div>

                    <!-- Description -->
                    <p
                      v-if="model.description"
                      class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400 line-clamp-1"
                    >
                      {{ model.description }}
                    </p>

                    <!-- Capabilities -->
                    <div v-if="model.capabilities?.length" class="mt-1 flex flex-wrap gap-1">
                      <span
                        v-for="cap in model.capabilities"
                        :key="cap"
                        class="flex items-center gap-1 rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400"
                      >
                        <component :is="capabilityIcon(cap)" class="h-2.5 w-2.5" />
                        {{ cap }}
                      </span>
                    </div>
                  </div>
                </button>
              </div>
            </template>

            <div
              v-else
              class="px-3 py-8 text-center text-sm text-neutral-400 dark:text-neutral-500"
            >
              No models found for "{{ searchQuery }}"
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
