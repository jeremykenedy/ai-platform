<script setup>
  import { ref, reactive, onMounted, watch } from 'vue'
  import {
    Brain,
    Plus,
    Trash2,
    Edit2,
    Check,
    X,
    ChevronDown,
    AlertTriangle,
    Loader2,
    ExternalLink,
    Star,
    GitMerge,
  } from 'lucide-vue-next'
  import { useMemoryStore } from '@/stores/memory'
  import { useSettingsStore } from '@/stores/settings'
  import { useUiStore } from '@/stores/ui'

  const memoryStore = useMemoryStore()
  const settingsStore = useSettingsStore()
  const uiStore = useUiStore()

  const CATEGORIES = ['all', 'preference', 'fact', 'instruction', 'context', 'personality']
  const CATEGORY_COLORS = {
    preference: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
    fact: 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300',
    instruction: 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300',
    context: 'bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300',
    personality: 'bg-pink-100 dark:bg-pink-900/40 text-pink-700 dark:text-pink-300',
  }
  const SORTS = [
    { value: 'importance_desc', label: 'Importance (high to low)' },
    { value: 'date_desc', label: 'Date (newest first)' },
    { value: 'date_asc', label: 'Date (oldest first)' },
    { value: 'accessed_desc', label: 'Recently accessed' },
  ]

  const filterCategory = ref('all')
  const sortBy = ref('importance_desc')
  const selectedIds = ref(new Set())
  const editingId = ref(null)
  const editContent = ref('')
  const editImportance = ref(5)
  const deletingId = ref(null)
  const deleteConfirmId = ref(null)
  const loadingMore = ref(false)
  const bulkDeleting = ref(false)
  const clearConfirmStep = ref(0)
  const clearTypeValue = ref('')
  const showAddForm = ref(false)
  const addingMemory = ref(false)
  const memoryEnabled = ref(true)

  const addForm = reactive({
    content: '',
    category: 'fact',
    importance: 5,
  })

  const mergeForms = ref({})

  onMounted(async () => {
    await Promise.all([fetchMemories(), memoryStore.fetchConflicts(), settingsStore.fetch()])
    if (settingsStore.settings) {
      memoryEnabled.value = settingsStore.settings.memory_enabled ?? true
    }
  })

  async function fetchMemories() {
    const params = {}
    if (filterCategory.value !== 'all') params.category = filterCategory.value
    params.sort = sortBy.value
    await memoryStore.fetch(params)
  }

  watch([filterCategory, sortBy], () => {
    selectedIds.value.clear()
    fetchMemories()
  })

  async function loadMore() {
    if (!memoryStore.pagination.hasMore) return
    loadingMore.value = true
    try {
      const params = { cursor: memoryStore.pagination.nextCursor }
      if (filterCategory.value !== 'all') params.category = filterCategory.value
      params.sort = sortBy.value
      await memoryStore.fetch(params)
    } finally {
      loadingMore.value = false
    }
  }

  function toggleSelect(id) {
    if (selectedIds.value.has(id)) {
      selectedIds.value.delete(id)
    } else {
      selectedIds.value.add(id)
    }
    selectedIds.value = new Set(selectedIds.value)
  }

  function toggleSelectAll() {
    if (selectedIds.value.size === memoryStore.memories.length) {
      selectedIds.value.clear()
    } else {
      selectedIds.value = new Set(memoryStore.memories.map((m) => m.id))
    }
  }

  async function bulkDelete() {
    if (selectedIds.value.size === 0) return
    bulkDeleting.value = true
    try {
      await memoryStore.bulkDestroy([...selectedIds.value])
      selectedIds.value.clear()
      uiStore.addToast({ type: 'success', message: 'Selected memories deleted.' })
    } catch {
      uiStore.addToast({ type: 'error', message: 'Failed to delete memories.' })
    } finally {
      bulkDeleting.value = false
    }
  }

  function startEdit(memory) {
    editingId.value = memory.id
    editContent.value = memory.content
    editImportance.value = memory.importance ?? 5
  }

  function cancelEdit() {
    editingId.value = null
  }

  async function saveEdit(memory) {
    try {
      await memoryStore.update(memory.id, {
        content: editContent.value,
        importance: editImportance.value,
      })
      editingId.value = null
      uiStore.addToast({ type: 'success', message: 'Memory updated.' })
    } catch {
      uiStore.addToast({ type: 'error', message: 'Failed to update memory.' })
    }
  }

  async function deleteMemory(id) {
    deletingId.value = id
    try {
      await memoryStore.destroy(id)
      deleteConfirmId.value = null
      uiStore.addToast({ type: 'success', message: 'Memory deleted.' })
    } catch {
      uiStore.addToast({ type: 'error', message: 'Failed to delete memory.' })
    } finally {
      deletingId.value = null
    }
  }

  async function addMemory() {
    if (!addForm.content.trim()) return
    addingMemory.value = true
    try {
      await memoryStore.create({ ...addForm })
      addForm.content = ''
      addForm.category = 'fact'
      addForm.importance = 5
      showAddForm.value = false
      uiStore.addToast({ type: 'success', message: 'Memory added.' })
    } catch {
      uiStore.addToast({ type: 'error', message: 'Failed to add memory.' })
    } finally {
      addingMemory.value = false
    }
  }

  async function resolveConflict(conflict, resolution, mergedContent = null) {
    try {
      await memoryStore.resolveConflict(conflict.id, { action: resolution, content: mergedContent })
      delete mergeForms.value[conflict.id]
      uiStore.addToast({ type: 'success', message: 'Conflict resolved.' })
    } catch {
      uiStore.addToast({ type: 'error', message: 'Failed to resolve conflict.' })
    }
  }

  function startMerge(conflict) {
    mergeForms.value[conflict.id] =
      (conflict.memory_a?.content ?? '') + '\n\n' + (conflict.memory_b?.content ?? '')
  }

  function cancelMerge(conflictId) {
    delete mergeForms.value[conflictId]
  }

  function cancelClear() {
    clearConfirmStep.value = 0
    clearTypeValue.value = ''
  }

  async function clearAllMemories() {
    await memoryStore.bulkDestroy(memoryStore.memories.map((m) => m.id))
    clearConfirmStep.value = 0
    clearTypeValue.value = ''
    uiStore.addToast({ type: 'success', message: 'All memories cleared.' })
  }

  async function toggleMemoryEnabled() {
    memoryEnabled.value = !memoryEnabled.value
    try {
      await settingsStore.update({ memory_enabled: memoryEnabled.value })
    } catch {
      memoryEnabled.value = !memoryEnabled.value
    }
  }

  function formatDate(dateStr) {
    if (!dateStr) return 'Never'
    return new Date(dateStr).toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  }

  function importanceColor(score) {
    if (score >= 8) return 'text-red-500 dark:text-red-400'
    if (score >= 5) return 'text-amber-500 dark:text-amber-400'
    return 'text-gray-400 dark:text-gray-500'
  }
</script>

<template>
  <div class="max-w-3xl mx-auto p-6 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
      <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Memory</h1>
        <span
          class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300"
        >
          {{ memoryStore.memories.length }}
        </span>
      </div>
      <div class="flex items-center gap-3">
        <div class="flex items-center gap-2">
          <button
            type="button"
            role="switch"
            :aria-checked="memoryEnabled"
            class="relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :class="memoryEnabled ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'"
            @click="toggleMemoryEnabled"
          >
            <span
              class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
              :class="memoryEnabled ? 'translate-x-4' : 'translate-x-0.5'"
            />
          </button>
          <span class="text-sm text-gray-600 dark:text-gray-300">Enable memory</span>
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition"
          @click="showAddForm = !showAddForm"
        >
          <Plus class="w-4 h-4" /> Add Memory
        </button>
      </div>
    </div>

    <!-- Add Memory Form -->
    <div
      v-if="showAddForm"
      class="bg-white dark:bg-gray-800 rounded-xl border border-indigo-300 dark:border-indigo-600 p-4 space-y-3"
    >
      <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Add New Memory</h2>
      <textarea
        v-model="addForm.content"
        rows="3"
        placeholder="What should I remember?"
        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
      />
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1"
            >Category</label
          >
          <select
            v-model="addForm.category"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-1.5 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
          >
            <option v-for="cat in CATEGORIES.filter((c) => c !== 'all')" :key="cat" :value="cat">
              {{ cat }}
            </option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
            Importance: {{ addForm.importance }}
          </label>
          <input
            v-model.number="addForm.importance"
            type="range"
            min="1"
            max="10"
            step="1"
            class="w-full accent-indigo-600"
          />
        </div>
      </div>
      <div class="flex justify-end gap-2">
        <button
          type="button"
          class="px-3 py-1.5 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
          @click="showAddForm = false"
        >
          Cancel
        </button>
        <button
          type="button"
          :disabled="addingMemory || !addForm.content.trim()"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-medium transition"
          @click="addMemory"
        >
          <Loader2 v-if="addingMemory" class="w-3.5 h-3.5 animate-spin" />
          <Check v-else class="w-3.5 h-3.5" />
          {{ addingMemory ? 'Adding...' : 'Add' }}
        </button>
      </div>
    </div>

    <!-- Conflict Resolution -->
    <div v-if="memoryStore.conflicts.length > 0" class="space-y-3">
      <div class="flex items-center gap-2">
        <AlertTriangle class="w-5 h-5 text-amber-500" />
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">
          Conflicts ({{ memoryStore.conflicts.length }})
        </h2>
      </div>
      <div
        v-for="conflict in memoryStore.conflicts"
        :key="conflict.id"
        class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-xl p-4 space-y-3"
      >
        <p class="text-xs font-medium text-amber-700 dark:text-amber-300">
          Conflicting memories detected
        </p>
        <div class="grid grid-cols-2 gap-3">
          <div
            class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-amber-200 dark:border-amber-700"
          >
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Memory A</p>
            <p class="text-sm text-gray-800 dark:text-gray-200">{{ conflict.memory_a?.content }}</p>
          </div>
          <div
            class="bg-white dark:bg-gray-800 rounded-lg p-3 border border-amber-200 dark:border-amber-700"
          >
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Memory B</p>
            <p class="text-sm text-gray-800 dark:text-gray-200">{{ conflict.memory_b?.content }}</p>
          </div>
        </div>

        <!-- Merge form -->
        <div v-if="mergeForms[conflict.id] !== undefined" class="space-y-2">
          <label class="block text-xs font-medium text-gray-600 dark:text-gray-400"
            >Merged content</label
          >
          <textarea
            v-model="mergeForms[conflict.id]"
            rows="3"
            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
          />
          <div class="flex gap-2">
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white transition"
              @click="resolveConflict(conflict, 'merge', mergeForms[conflict.id])"
            >
              Save Merged
            </button>
            <button
              type="button"
              class="px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition"
              @click="cancelMerge(conflict.id)"
            >
              Cancel
            </button>
          </div>
        </div>

        <div v-else class="flex flex-wrap gap-2">
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg text-xs font-medium bg-green-600 hover:bg-green-700 text-white transition"
            @click="resolveConflict(conflict, 'keep_new')"
          >
            Keep New
          </button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg text-xs font-medium bg-blue-600 hover:bg-blue-700 text-white transition"
            @click="resolveConflict(conflict, 'keep_old')"
          >
            Keep Old
          </button>
          <button
            type="button"
            class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-purple-600 hover:bg-purple-700 text-white transition"
            @click="startMerge(conflict)"
          >
            <GitMerge class="w-3.5 h-3.5" /> Merge
          </button>
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition"
            @click="resolveConflict(conflict, 'dismiss')"
          >
            Dismiss
          </button>
        </div>
      </div>
    </div>

    <!-- Filter Bar -->
    <div class="flex flex-wrap items-center gap-3">
      <div class="flex-1 min-w-40">
        <select
          v-model="filterCategory"
          class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option v-for="cat in CATEGORIES" :key="cat" :value="cat">
            {{ cat === 'all' ? 'All categories' : cat.charAt(0).toUpperCase() + cat.slice(1) }}
          </option>
        </select>
      </div>
      <div class="flex-1 min-w-48">
        <select
          v-model="sortBy"
          class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
        >
          <option v-for="s in SORTS" :key="s.value" :value="s.value">{{ s.label }}</option>
        </select>
      </div>

      <!-- Bulk actions -->
      <div v-if="memoryStore.memories.length > 0" class="flex items-center gap-2">
        <button
          type="button"
          class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition"
          @click="toggleSelectAll"
        >
          {{ selectedIds.size === memoryStore.memories.length ? 'Deselect all' : 'Select all' }}
        </button>
        <button
          v-if="selectedIds.size > 0"
          type="button"
          :disabled="bulkDeleting"
          class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-medium bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white transition"
          @click="bulkDelete"
        >
          <Loader2 v-if="bulkDeleting" class="w-3 h-3 animate-spin" />
          <Trash2 v-else class="w-3 h-3" />
          Delete {{ selectedIds.size }} selected
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div
      v-if="memoryStore.isLoading && memoryStore.memories.length === 0"
      class="flex items-center justify-center gap-2 py-12 text-gray-400 dark:text-gray-500 text-sm"
    >
      <Loader2 class="w-5 h-5 animate-spin" /> Loading memories...
    </div>

    <!-- Empty state -->
    <div
      v-else-if="memoryStore.memories.length === 0"
      class="flex flex-col items-center justify-center py-16 text-center"
    >
      <Brain class="w-14 h-14 text-gray-300 dark:text-gray-600 mb-4" />
      <p class="text-gray-500 dark:text-gray-400 text-sm">No memories found.</p>
    </div>

    <!-- Memory List -->
    <div class="space-y-3">
      <div
        v-for="memory in memoryStore.memories"
        :key="memory.id"
        class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4"
        :class="{
          'border-indigo-300 dark:border-indigo-600 ring-1 ring-indigo-300 dark:ring-indigo-600':
            selectedIds.has(memory.id),
        }"
      >
        <div class="flex items-start gap-3">
          <!-- Checkbox -->
          <input
            type="checkbox"
            :checked="selectedIds.has(memory.id)"
            class="mt-0.5 h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 accent-indigo-600 flex-shrink-0"
            @change="toggleSelect(memory.id)"
          />

          <div class="flex-1 min-w-0 space-y-2">
            <!-- Edit mode -->
            <div v-if="editingId === memory.id" class="space-y-2">
              <textarea
                v-model="editContent"
                rows="3"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-none"
              />
              <div class="flex items-center gap-3">
                <label class="text-xs text-gray-500 dark:text-gray-400"
                  >Importance: {{ editImportance }}</label
                >
                <input
                  v-model.number="editImportance"
                  type="range"
                  min="1"
                  max="10"
                  class="flex-1 accent-indigo-600"
                />
              </div>
              <div class="flex gap-2">
                <button
                  type="button"
                  class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white transition"
                  @click="saveEdit(memory)"
                >
                  <Check class="w-3 h-3" /> Save
                </button>
                <button
                  type="button"
                  class="inline-flex items-center gap-1 px-3 py-1 rounded-lg text-xs font-medium bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                  @click="cancelEdit"
                >
                  <X class="w-3 h-3" /> Cancel
                </button>
              </div>
            </div>

            <!-- View mode -->
            <div v-else>
              <p class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed">
                {{ memory.content }}
              </p>
            </div>

            <!-- Meta row -->
            <div class="flex flex-wrap items-center gap-2 text-xs text-gray-400 dark:text-gray-500">
              <span
                class="px-2 py-0.5 rounded-full text-xs font-medium capitalize"
                :class="
                  CATEGORY_COLORS[memory.category] ??
                  'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300'
                "
                >{{ memory.category }}</span
              >
              <span
                :class="importanceColor(memory.importance ?? 5)"
                class="flex items-center gap-0.5 font-medium"
              >
                <Star class="w-3 h-3" /> {{ memory.importance ?? 5 }}/10
              </span>
              <span>Accessed {{ formatDate(memory.last_accessed_at) }}</span>
              <span v-if="memory.access_count">{{ memory.access_count }}x</span>
              <a
                v-if="memory.conversation_id"
                :href="`/conversations/${memory.conversation_id}`"
                class="inline-flex items-center gap-0.5 text-indigo-500 dark:text-indigo-400 hover:underline"
              >
                <ExternalLink class="w-3 h-3" /> source
              </a>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex items-center gap-1 flex-shrink-0">
            <button
              v-if="editingId !== memory.id"
              type="button"
              class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
              @click="startEdit(memory)"
            >
              <Edit2 class="w-3.5 h-3.5" />
            </button>
            <button
              type="button"
              class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
              @click="deleteConfirmId = deleteConfirmId === memory.id ? null : memory.id"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Delete confirmation inline -->
        <div
          v-if="deleteConfirmId === memory.id"
          class="mt-3 flex items-center gap-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-2.5 text-xs"
        >
          <AlertTriangle class="w-3.5 h-3.5 text-red-500 flex-shrink-0" />
          <span class="flex-1 text-red-700 dark:text-red-300">Delete this memory?</span>
          <button
            type="button"
            class="px-2.5 py-1 rounded text-xs bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition"
            @click="deleteConfirmId = null"
          >
            No
          </button>
          <button
            type="button"
            :disabled="deletingId === memory.id"
            class="inline-flex items-center gap-1 px-2.5 py-1 rounded text-xs bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white transition"
            @click="deleteMemory(memory.id)"
          >
            <Loader2 v-if="deletingId === memory.id" class="w-3 h-3 animate-spin" />
            Yes, delete
          </button>
        </div>
      </div>
    </div>

    <!-- Load More -->
    <div v-if="memoryStore.pagination.hasMore" class="flex justify-center">
      <button
        type="button"
        :disabled="loadingMore"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-60 transition"
        @click="loadMore"
      >
        <Loader2 v-if="loadingMore" class="w-4 h-4 animate-spin" />
        <ChevronDown v-else class="w-4 h-4" />
        Load more
      </button>
    </div>

    <!-- Clear All -->
    <div
      v-if="memoryStore.memories.length > 0"
      class="pt-4 border-t border-gray-200 dark:border-gray-700 space-y-3"
    >
      <div v-if="clearConfirmStep === 0">
        <button
          type="button"
          class="text-sm text-red-500 hover:text-red-700 dark:hover:text-red-400 font-medium transition"
          @click="clearConfirmStep = 1"
        >
          Clear all memories
        </button>
      </div>

      <div
        v-else-if="clearConfirmStep === 1"
        class="flex items-center gap-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4"
      >
        <AlertTriangle class="w-5 h-5 text-red-500 flex-shrink-0" />
        <div class="flex-1">
          <p class="text-sm font-semibold text-red-700 dark:text-red-300">
            This will permanently delete all memories.
          </p>
          <p class="text-xs text-red-600 dark:text-red-400 mt-0.5">This action cannot be undone.</p>
        </div>
        <button
          type="button"
          class="px-3 py-1.5 rounded-lg text-sm bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition"
          @click="clearConfirmStep = 0"
        >
          Cancel
        </button>
        <button
          type="button"
          class="px-3 py-1.5 rounded-lg text-sm bg-red-600 hover:bg-red-700 text-white font-medium transition"
          @click="clearConfirmStep = 2"
        >
          Continue
        </button>
      </div>

      <div
        v-else-if="clearConfirmStep === 2"
        class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4 space-y-3"
      >
        <p class="text-sm font-semibold text-red-700 dark:text-red-300">
          Type <span class="font-mono bg-red-100 dark:bg-red-800/40 px-1 rounded">DELETE</span> to
          confirm
        </p>
        <input
          v-model="clearTypeValue"
          type="text"
          placeholder="Type DELETE here"
          class="w-full rounded-lg border border-red-300 dark:border-red-700 px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-red-500"
        />
        <div class="flex gap-2">
          <button
            type="button"
            class="px-3 py-1.5 rounded-lg text-sm bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition"
            @click="cancelClear"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="clearTypeValue !== 'DELETE'"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm bg-red-600 hover:bg-red-700 disabled:opacity-40 text-white font-medium transition"
            @click="clearAllMemories"
          >
            <Trash2 class="w-3.5 h-3.5" /> Clear all memories
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
