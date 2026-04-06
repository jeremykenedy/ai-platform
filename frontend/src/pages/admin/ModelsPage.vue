<script setup>
  import { ref, onMounted, onUnmounted } from 'vue'
  import { RefreshCw, Download, Trash2, MemoryStick, Clock, X } from 'lucide-vue-next'
  import api from '@/services/api'
  import { getEcho, leaveChannel } from '@/services/echo'
  import { useModelsStore } from '@/stores/models'
  import { useToast } from '@/composables/useToast'

  const { toast } = useToast()
  const modelsStore = useModelsStore()

  const models = ref([])
  const runningModels = ref([])
  const loadingModels = ref(true)
  const loadingRunning = ref(false)
  const syncingModels = ref(false)

  const showPullPanel = ref(false)
  const pullModelName = ref('')
  const pulling = ref(false)
  const pullProgress = ref(0)
  const pullStatus = ref('')

  const deletingId = ref(null)
  const togglingId = ref(null)
  const unloadingName = ref(null)

  let runningTimer = null

  const capabilityBadge = {
    chat: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
    vision: 'bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-400',
    embedding: 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400',
    code: 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400',
    tools: 'bg-teal-100 dark:bg-teal-900/30 text-teal-700 dark:text-teal-400',
    reasoning: 'bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-400',
  }

  function formatBytes(bytes) {
    if (!bytes) return '0 B'
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    let i = 0
    let val = bytes
    while (val >= 1024 && i < units.length - 1) {
      val /= 1024
      i++
    }
    return `${val.toFixed(1)} ${units[i]}`
  }

  function formatRelativeTime(dateStr) {
    if (!dateStr) return ''
    const diff = Date.now() - new Date(dateStr).getTime()
    const minutes = Math.floor(diff / 60000)
    if (minutes < 1) return 'just now'
    if (minutes < 60) return `${minutes}m ago`
    return `${Math.floor(minutes / 60)}h ago`
  }

  async function fetchModels() {
    loadingModels.value = true
    try {
      const response = await api.get('/admin/models')
      models.value = response?.data?.data ?? response?.data ?? []
    } catch {
      toast({ title: 'Failed to load models', variant: 'destructive' })
    } finally {
      loadingModels.value = false
    }
  }

  async function fetchRunningModels(silent = false) {
    if (!silent) loadingRunning.value = true
    try {
      const response = await api.get('/admin/models/running')
      runningModels.value = response?.data?.data ?? response?.data ?? []
    } catch {
      // Silently fail for running models
    } finally {
      loadingRunning.value = false
    }
  }

  async function syncModels() {
    syncingModels.value = true
    try {
      await api.post('/admin/models/sync')
      toast({ title: 'Models synced' })
      await fetchModels()
      await modelsStore.fetch()
    } catch {
      toast({ title: 'Sync failed', variant: 'destructive' })
    } finally {
      syncingModels.value = false
    }
  }

  async function toggleActive(model) {
    togglingId.value = model.id
    try {
      await api.patch(`/admin/models/${model.id}/toggle`)
      model.is_active = !model.is_active
      toast({ title: `Model ${model.is_active ? 'enabled' : 'disabled'}` })
    } catch {
      toast({ title: 'Failed to update model', variant: 'destructive' })
    } finally {
      togglingId.value = null
    }
  }

  async function deleteModel(model) {
    if (!confirm(`Delete "${model.name}"? This cannot be undone.`)) return
    deletingId.value = model.id
    try {
      await api.delete(`/admin/models/${model.id}`)
      models.value = models.value.filter((m) => m.id !== model.id)
      toast({ title: 'Model deleted' })
    } catch {
      toast({ title: 'Failed to delete model', variant: 'destructive' })
    } finally {
      deletingId.value = null
    }
  }

  async function unloadModel(name) {
    unloadingName.value = name
    try {
      await api.post('/admin/models/unload', { name })
      runningModels.value = runningModels.value.filter((m) => m.name !== name)
      toast({ title: 'Model unloaded' })
    } catch {
      toast({ title: 'Failed to unload model', variant: 'destructive' })
    } finally {
      unloadingName.value = null
    }
  }

  async function pullModel() {
    if (!pullModelName.value.trim()) return
    pulling.value = true
    pullProgress.value = 0
    pullStatus.value = 'Starting...'

    try {
      await api.post('/admin/models/pull', { model: pullModelName.value.trim() })
      // Progress comes via WebSocket
    } catch {
      toast({ title: 'Failed to initiate pull', variant: 'destructive' })
      pulling.value = false
    }
  }

  function subscribeToEvents() {
    const echo = getEcho()
    echo.private('admin').listen('ModelPullProgress', (e) => {
      if (e.model !== pullModelName.value.trim()) return
      pullProgress.value = e.percent ?? 0
      pullStatus.value = e.status ?? ''
      if (e.completed) {
        pulling.value = false
        pullModelName.value = ''
        toast({ title: 'Model pulled successfully' })
        fetchModels()
        modelsStore.fetch()
      }
      if (e.failed) {
        pulling.value = false
        toast({ title: e.error ?? 'Pull failed', variant: 'destructive' })
      }
    })
  }

  onMounted(() => {
    fetchModels()
    fetchRunningModels()
    subscribeToEvents()
    runningTimer = setInterval(() => fetchRunningModels(true), 10000)
  })

  onUnmounted(() => {
    clearInterval(runningTimer)
    leaveChannel('admin')
  })
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-50">Models</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ models.length }} models</p>
      </div>
      <div class="flex items-center gap-2">
        <button
          :disabled="syncingModels"
          class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 transition-colors"
          @click="syncModels"
        >
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': syncingModels }" />
          Sync Models
        </button>
        <button
          class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
          @click="showPullPanel = !showPullPanel"
        >
          <Download class="h-4 w-4" />
          Pull Model
        </button>
      </div>
    </div>

    <!-- Pull Model Panel -->
    <div
      v-if="showPullPanel"
      class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-5 space-y-4"
    >
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-100">
          Pull Model from Ollama
        </h2>
        <button
          class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200"
          @click="showPullPanel = false"
        >
          <X class="h-4 w-4" />
        </button>
      </div>
      <div class="flex gap-2">
        <input
          v-model="pullModelName"
          type="text"
          placeholder="e.g. llama3.2:latest"
          :disabled="pulling"
          class="flex-1 rounded-lg border border-blue-200 dark:border-blue-700 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
          @keydown.enter="pullModel"
        />
        <button
          :disabled="pulling || !pullModelName.trim()"
          class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
          @click="pullModel"
        >
          {{ pulling ? 'Pulling...' : 'Pull' }}
        </button>
      </div>
      <div v-if="pulling">
        <div
          class="flex items-center justify-between text-xs text-blue-700 dark:text-blue-300 mb-1"
        >
          <span>{{ pullStatus }}</span>
          <span>{{ pullProgress }}%</span>
        </div>
        <div class="h-2 rounded-full bg-blue-200 dark:bg-blue-800 overflow-hidden">
          <div
            class="h-full rounded-full bg-blue-600 transition-all duration-300"
            :style="{ width: pullProgress + '%' }"
          />
        </div>
      </div>
    </div>

    <!-- Running Models -->
    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
      <div
        class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 px-5 py-3"
      >
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-50">Running Models</h2>
        <span class="text-xs text-gray-500 dark:text-gray-400">refreshes every 10s</span>
      </div>

      <div
        v-if="loadingRunning"
        class="flex items-center gap-2 px-5 py-4 text-sm text-gray-500 dark:text-gray-400"
      >
        <RefreshCw class="h-3 w-3 animate-spin" />
        Loading...
      </div>
      <div
        v-else-if="runningModels.length === 0"
        class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400"
      >
        No models currently loaded
      </div>
      <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
        <li v-for="m in runningModels" :key="m.name" class="flex items-center gap-4 px-5 py-3">
          <div class="flex h-2 w-2 shrink-0 rounded-full bg-green-500" />
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
              {{ m.name }}
            </p>
            <div class="flex gap-3 mt-0.5">
              <span class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                <MemoryStick class="h-3 w-3" />
                {{ formatBytes(m.size_vram) }}
              </span>
              <span class="inline-flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400">
                <Clock class="h-3 w-3" />
                {{ formatRelativeTime(m.expires_at) }}
              </span>
            </div>
          </div>
          <button
            :disabled="unloadingName === m.name"
            class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 transition-colors"
            @click="unloadModel(m.name)"
          >
            {{ unloadingName === m.name ? 'Unloading...' : 'Unload' }}
          </button>
        </li>
      </ul>
    </div>

    <!-- Models Table -->
    <div
      class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden"
    >
      <div class="border-b border-gray-200 dark:border-gray-800 px-5 py-3">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-50">All Models</h2>
      </div>

      <!-- Loading skeleton -->
      <div v-if="loadingModels" class="divide-y divide-gray-100 dark:divide-gray-800">
        <div v-for="i in 5" :key="i" class="flex items-center gap-4 px-5 py-4">
          <div class="h-4 w-24 rounded bg-gray-200 dark:bg-gray-700 animate-pulse" />
          <div class="h-4 w-40 rounded bg-gray-200 dark:bg-gray-700 animate-pulse" />
          <div class="h-4 w-20 rounded bg-gray-200 dark:bg-gray-700 animate-pulse" />
          <div class="ml-auto h-6 w-12 rounded-full bg-gray-200 dark:bg-gray-700 animate-pulse" />
        </div>
      </div>

      <div v-else class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr
              class="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50"
            >
              <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">
                Provider
              </th>
              <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Name</th>
              <th
                class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400 hidden lg:table-cell"
              >
                Display Name
              </th>
              <th
                class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400 hidden xl:table-cell"
              >
                Parameters
              </th>
              <th
                class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400 hidden xl:table-cell"
              >
                Context
              </th>
              <th
                class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400 hidden lg:table-cell"
              >
                Capabilities
              </th>
              <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">
                Source
              </th>
              <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">
                Active
              </th>
              <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">
                Actions
              </th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            <tr
              v-for="m in models"
              :key="m.id"
              class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
            >
              <td class="px-5 py-3 text-gray-600 dark:text-gray-400 capitalize">
                {{ m.provider }}
              </td>
              <td class="px-5 py-3 font-mono text-xs text-gray-900 dark:text-gray-100">
                {{ m.name }}
              </td>
              <td class="px-5 py-3 text-gray-700 dark:text-gray-300 hidden lg:table-cell">
                {{ m.display_name ?? m.name }}
              </td>
              <td class="px-5 py-3 text-gray-600 dark:text-gray-400 hidden xl:table-cell">
                {{ m.parameters ?? '-' }}
              </td>
              <td class="px-5 py-3 text-gray-600 dark:text-gray-400 hidden xl:table-cell">
                {{ m.context_window ? m.context_window.toLocaleString() : '-' }}
              </td>
              <td class="px-5 py-3 hidden lg:table-cell">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="cap in m.capabilities ?? []"
                    :key="cap"
                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="
                      capabilityBadge[cap] ??
                      'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400'
                    "
                  >
                    {{ cap }}
                  </span>
                </div>
              </td>
              <td class="px-5 py-3">
                <span
                  class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="
                    m.provider === 'ollama'
                      ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400'
                      : 'bg-sky-100 dark:bg-sky-900/30 text-sky-700 dark:text-sky-400'
                  "
                >
                  {{ m.provider === 'ollama' ? 'Local' : 'Remote' }}
                </span>
              </td>
              <td class="px-5 py-3">
                <button
                  :disabled="togglingId === m.id"
                  class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center rounded-full transition-colors disabled:opacity-50"
                  :class="m.is_active ? 'bg-blue-600' : 'bg-gray-300 dark:bg-gray-600'"
                  :aria-label="m.is_active ? 'Disable model' : 'Enable model'"
                  @click="toggleActive(m)"
                >
                  <span
                    class="inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition-transform"
                    :class="m.is_active ? 'translate-x-4' : 'translate-x-1'"
                  />
                </button>
              </td>
              <td class="px-5 py-3 text-right">
                <button
                  v-if="m.provider === 'ollama'"
                  :disabled="deletingId === m.id"
                  class="rounded-lg p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-50 transition-colors"
                  title="Delete model"
                  @click="deleteModel(m)"
                >
                  <Trash2 class="h-4 w-4" />
                </button>
              </td>
            </tr>
            <tr v-if="models.length === 0">
              <td
                colspan="9"
                class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400"
              >
                No models found. Click "Sync Models" to load available models.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
