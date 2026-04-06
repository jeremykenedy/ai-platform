<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { cn } from '@/lib/utils'
import { useRouter } from 'vue-router'
import { Plus, Search, BrainCircuit } from 'lucide-vue-next'
import { useUIStore } from '@/stores/ui'
import { useConversationsStore } from '@/stores/conversations'
import { useModelsStore } from '@/stores/models'
import { useProjectsStore } from '@/stores/projects'
import SidebarConversationItem from './SidebarConversationItem.vue'
import SidebarProjectFilter from './SidebarProjectFilter.vue'
import SidebarUserMenu from './SidebarUserMenu.vue'

const ui = useUIStore()
const conversations = useConversationsStore()
const models = useModelsStore()
const projects = useProjectsStore()
const router = useRouter()

const searchQuery = ref('')
let debounceTimer = null

const filteredGrouped = computed(() => {
  const q = searchQuery.value.trim().toLowerCase()
  const pid = projects.activeId

  const filter = (list) => {
    let result = list
    if (pid) result = result.filter((c) => c.project_id === pid)
    if (q) result = result.filter((c) => (c.title ?? '').toLowerCase().includes(q))
    return result
  }

  const raw = conversations.grouped
  const out = {}
  for (const [group, list] of Object.entries(raw)) {
    const filtered = filter(list)
    if (filtered.length) out[group] = filtered
  }
  return out
})

const hasConversations = computed(() => Object.values(filteredGrouped.value).some((l) => l.length > 0))

const activeModelLabel = computed(() => {
  const m = models.activeModel ?? models.defaultModel
  if (!m) return 'No model'
  return m.name ?? m.id ?? 'Model'
})

function onSearch(e) {
  clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    searchQuery.value = e.target.value
  }, 200)
}

async function newChat() {
  const convo = await conversations.create({ title: null })
  router.push(`/c/${convo.id}`)
}

async function deleteConversation(convo) {
  await conversations.destroy(convo.id)
}

function closeSidebar() {
  if (ui.isMobile) ui.toggleSidebar()
}

onMounted(async () => {
  await Promise.all([
    conversations.fetch(),
    models.models.length === 0 && models.fetch(),
    projects.projects.length === 0 && projects.fetch(),
  ])
})

watch(
  () => ui.isMobile,
  (mobile) => {
    if (mobile) ui.sidebarOpen = false
  },
)
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-200"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="ui.sidebarOpen && ui.isMobile"
        class="fixed inset-0 z-30 bg-black/50 md:hidden"
        @click="ui.toggleSidebar()"
      />
    </Transition>
  </Teleport>

  <Transition
    enter-active-class="transition-transform duration-200 ease-out"
    enter-from-class="-translate-x-full"
    enter-to-class="translate-x-0"
    leave-active-class="transition-transform duration-200 ease-in"
    leave-from-class="translate-x-0"
    leave-to-class="-translate-x-full"
  >
    <aside
      v-show="ui.sidebarOpen"
      :class="cn(
        'flex w-72 shrink-0 flex-col',
        'border-r border-gray-200 dark:border-gray-800',
        'bg-white dark:bg-gray-900',
        'fixed inset-y-0 left-0 z-40 md:relative md:z-auto'
      )"
    >
      <div
        :class="cn(
          'flex h-14 items-center justify-between border-b px-4',
          'border-gray-200 dark:border-gray-800'
        )"
      >
        <span class="text-base font-semibold text-gray-900 dark:text-gray-50">Conversations</span>
      </div>

      <div class="flex flex-col gap-1 border-b border-gray-200 dark:border-gray-800 p-3">
        <button
          :class="cn(
            'flex w-full items-center justify-center gap-2 rounded-lg px-4 py-2 text-sm font-semibold transition-colors',
            'bg-primary text-primary-foreground',
            'hover:bg-primary/90'
          )"
          @click="newChat"
        >
          <Plus class="h-4 w-4" />
          New Chat
        </button>

        <div class="relative mt-1">
          <Search
            class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500"
          />
          <input
            type="text"
            placeholder="Search conversations..."
            :class="cn(
              'w-full rounded-md border py-1.5 pl-8 pr-3 text-sm',
              'border-gray-200 dark:border-gray-700',
              'bg-gray-50 dark:bg-gray-800',
              'text-gray-900 dark:text-gray-100',
              'placeholder-gray-400 dark:placeholder-gray-500',
              'focus:outline-none focus:ring-2 focus:ring-primary/50',
              'transition-colors'
            )"
            @input="onSearch"
          />
        </div>
      </div>

      <SidebarProjectFilter v-if="projects.projects.length > 0" />

      <div class="flex-1 overflow-y-auto py-2">
        <template v-if="hasConversations">
          <div
            v-for="(list, group) in filteredGrouped"
            :key="group"
            class="mb-2"
          >
            <p
              :class="cn(
                'px-4 py-1 text-xs font-semibold uppercase tracking-wider',
                'text-gray-400 dark:text-gray-500'
              )"
            >
              {{ group }}
            </p>
            <div class="px-2">
              <SidebarConversationItem
                v-for="convo in list"
                :key="convo.id"
                :conversation="convo"
                @select="closeSidebar"
                @delete="deleteConversation"
              />
            </div>
          </div>
        </template>

        <div
          v-else-if="conversations.isLoading"
          class="flex items-center justify-center py-8"
        >
          <div
            class="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent"
          />
        </div>

        <div
          v-else
          class="flex flex-col items-center justify-center gap-2 py-12 text-center px-4"
        >
          <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ searchQuery ? 'No conversations match your search.' : 'No conversations yet.' }}
          </p>
          <button
            v-if="!searchQuery"
            :class="cn(
              'text-sm font-medium transition-colors',
              'text-primary hover:text-primary/80'
            )"
            @click="newChat"
          >
            Start a new chat
          </button>
        </div>
      </div>

      <div
        :class="cn(
          'border-t border-gray-200 dark:border-gray-800 p-2'
        )"
      >
        <div
          :class="cn(
            'mb-2 flex items-center gap-2 rounded-lg px-3 py-1.5',
            'text-gray-500 dark:text-gray-400'
          )"
        >
          <BrainCircuit class="h-3.5 w-3.5 shrink-0" />
          <span class="truncate text-xs font-medium">{{ activeModelLabel }}</span>
        </div>
        <SidebarUserMenu />
      </div>
    </aside>
  </Transition>
</template>
