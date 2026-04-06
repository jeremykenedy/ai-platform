<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue'
import {
  Search,
  X,
  MessageSquare,
  Cpu,
  User,
  Plus,
  Settings,
  Moon,
  PanelLeft,
  Command,
} from 'lucide-vue-next'
import { useConversationsStore } from '@/stores/conversations'
import { useModelsStore } from '@/stores/models'
import { usePersonasStore } from '@/stores/personas'
import { useUIStore } from '@/stores/ui'
import { useRouter } from 'vue-router'

const props = defineProps({
  show: {
    type: Boolean,
    required: true,
  },
})

const emit = defineEmits(['update:show'])

const conversationsStore = useConversationsStore()
const modelsStore = useModelsStore()
const personasStore = usePersonasStore()
const ui = useUIStore()
const router = useRouter()

const query = ref('')
const activeIndex = ref(0)
const searchInput = ref(null)
const debounceTimer = ref(null)
const debouncedQuery = ref('')

watch(query, (val) => {
  clearTimeout(debounceTimer.value)
  debounceTimer.value = setTimeout(() => {
    debouncedQuery.value = val
    activeIndex.value = 0
  }, 200)
})

watch(
  () => props.show,
  async (val) => {
    if (val) {
      query.value = ''
      debouncedQuery.value = ''
      activeIndex.value = 0
      await nextTick()
      searchInput.value?.focus()
    }
  },
)

const actions = [
  {
    id: 'new-conversation',
    title: 'New Conversation',
    subtitle: 'Start a fresh chat',
    icon: Plus,
    run() {
      conversationsStore.create({ title: 'New conversation' }).then((c) => {
        router.push(`/c/${c.id}`)
      })
      close()
    },
  },
  {
    id: 'open-settings',
    title: 'Open Settings',
    subtitle: 'Manage your preferences',
    icon: Settings,
    run() {
      router.push('/settings')
      close()
    },
  },
  {
    id: 'toggle-dark-mode',
    title: 'Toggle Dark Mode',
    subtitle: ui.isDark ? 'Switch to light mode' : 'Switch to dark mode',
    icon: Moon,
    run() {
      ui.setTheme(ui.isDark ? 'light' : 'dark')
      close()
    },
  },
  {
    id: 'toggle-sidebar',
    title: 'Toggle Sidebar',
    subtitle: 'Show or hide the sidebar',
    icon: PanelLeft,
    run() {
      ui.toggleSidebar()
      close()
    },
  },
]

const filteredGroups = computed(() => {
  const q = debouncedQuery.value.toLowerCase().trim()
  const groups = []

  const convos = conversationsStore.sortedConversations
    .filter((c) => !q || (c.title ?? '').toLowerCase().includes(q))
    .slice(0, 5)

  if (convos.length) {
    groups.push({
      label: 'Recent Conversations',
      items: convos.map((c) => ({
        id: `conv-${c.id}`,
        title: c.title || 'Untitled',
        subtitle: new Date(c.updated_at).toLocaleDateString(),
        icon: MessageSquare,
        run() {
          router.push(`/c/${c.id}`)
          close()
        },
      })),
    })
  }

  const filteredModels = modelsStore.availableModels
    .filter((m) => !q || m.name?.toLowerCase().includes(q) || m.id?.toLowerCase().includes(q))
    .slice(0, 4)

  if (filteredModels.length) {
    groups.push({
      label: 'Models',
      items: filteredModels.map((m) => ({
        id: `model-${m.id}`,
        title: m.name ?? m.id,
        subtitle: m.provider ?? '',
        icon: Cpu,
        run() {
          modelsStore.setActive(m.id)
          close()
        },
      })),
    })
  }

  const filteredPersonas = personasStore.sortedPersonas
    .filter((p) => !q || p.name?.toLowerCase().includes(q))
    .slice(0, 4)

  if (filteredPersonas.length) {
    groups.push({
      label: 'Personas',
      items: filteredPersonas.map((p) => ({
        id: `persona-${p.id}`,
        title: p.name,
        subtitle: p.description ?? '',
        icon: User,
        run() {
          personasStore.setActive(p.id)
          close()
        },
      })),
    })
  }

  const filteredActions = actions.filter(
    (a) => !q || a.title.toLowerCase().includes(q) || a.subtitle.toLowerCase().includes(q),
  )

  if (filteredActions.length) {
    groups.push({ label: 'Actions', items: filteredActions })
  }

  return groups
})

const allItems = computed(() => filteredGroups.value.flatMap((g) => g.items))

const isEmpty = computed(() => allItems.value.length === 0)

function close() {
  emit('update:show', false)
}

function select(item) {
  item.run()
}

function onKeydown(e) {
  if (!props.show) return

  if (e.key === 'Escape') {
    close()
    return
  }

  if (e.key === 'ArrowDown') {
    e.preventDefault()
    activeIndex.value = (activeIndex.value + 1) % (allItems.value.length || 1)
    return
  }

  if (e.key === 'ArrowUp') {
    e.preventDefault()
    activeIndex.value = (activeIndex.value - 1 + (allItems.value.length || 1)) % (allItems.value.length || 1)
    return
  }

  if (e.key === 'Enter') {
    e.preventDefault()
    const item = allItems.value[activeIndex.value]
    if (item) select(item)
  }
}

function globalIndex(groupIndex, itemIndex) {
  let offset = 0
  for (let i = 0; i < groupIndex; i++) {
    offset += filteredGroups.value[i].items.length
  }
  return offset + itemIndex
}

onMounted(() => window.addEventListener('keydown', onKeydown))
onUnmounted(() => window.removeEventListener('keydown', onKeydown))
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 pt-[15vh] backdrop-blur-sm dark:bg-black/60"
        @click.self="close"
      >
        <div class="w-full max-w-xl overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900">
          <!-- Search input -->
          <div class="flex items-center gap-3 border-b border-gray-200 px-4 py-3 dark:border-gray-700">
            <Search class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" />
            <input
              ref="searchInput"
              v-model="query"
              type="text"
              placeholder="Search conversations, models, actions..."
              class="flex-1 bg-transparent text-sm text-gray-900 outline-none placeholder:text-gray-400 dark:text-gray-50 dark:placeholder:text-gray-500"
            />
            <button
              v-if="query"
              class="rounded p-0.5 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
              @click="query = ''"
            >
              <X class="h-3.5 w-3.5" />
            </button>
          </div>

          <!-- Results -->
          <div class="max-h-96 overflow-y-auto py-2">
            <template v-if="!isEmpty">
              <div
                v-for="(group, gi) in filteredGroups"
                :key="group.label"
                class="mb-1"
              >
                <p class="px-4 pb-1 pt-2 text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">
                  {{ group.label }}
                </p>
                <button
                  v-for="(item, ii) in group.items"
                  :key="item.id"
                  class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors"
                  :class="globalIndex(gi, ii) === activeIndex
                    ? 'bg-primary/10 dark:bg-primary/15'
                    : 'hover:bg-gray-100 dark:hover:bg-gray-800'"
                  @click="select(item)"
                  @mouseenter="activeIndex = globalIndex(gi, ii)"
                >
                  <component
                    :is="item.icon"
                    class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500"
                  />
                  <div class="flex min-w-0 flex-col">
                    <span class="truncate text-sm font-medium text-gray-900 dark:text-gray-50">
                      {{ item.title }}
                    </span>
                    <span
                      v-if="item.subtitle"
                      class="truncate text-xs text-gray-400 dark:text-gray-500"
                    >
                      {{ item.subtitle }}
                    </span>
                  </div>
                </button>
              </div>
            </template>

            <!-- Empty state -->
            <div
              v-else
              class="flex flex-col items-center gap-3 py-10 text-center"
            >
              <Command class="h-8 w-8 text-gray-300 dark:text-gray-600" />
              <p class="text-sm text-gray-400 dark:text-gray-500">
                No results for "{{ debouncedQuery }}"
              </p>
            </div>
          </div>

          <!-- Footer hint -->
          <div class="flex items-center gap-4 border-t border-gray-200 px-4 py-2 dark:border-gray-700">
            <span class="text-xs text-gray-400 dark:text-gray-500">
              <kbd class="font-mono">↑↓</kbd> navigate
            </span>
            <span class="text-xs text-gray-400 dark:text-gray-500">
              <kbd class="font-mono">↵</kbd> select
            </span>
            <span class="text-xs text-gray-400 dark:text-gray-500">
              <kbd class="font-mono">Esc</kbd> close
            </span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
