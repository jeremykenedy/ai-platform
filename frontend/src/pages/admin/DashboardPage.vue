<script setup>
  import { ref, onMounted, onUnmounted } from 'vue'
  import {
    Users,
    MessageSquare,
    MessagesSquare,
    Cpu,
    UserCheck,
    Activity,
    HardDrive,
    MemoryStick,
    RefreshCw,
  } from 'lucide-vue-next'
  import api from '@/services/api'
  import { useToast } from '@/composables/useToast'

  const { toast } = useToast()

  const loading = ref(true)
  const refreshing = ref(false)

  const stats = ref({
    total_users: 0,
    new_users_this_week: 0,
    total_conversations: 0,
    total_messages: 0,
    active_models: 0,
    active_users_24h: 0,
    queue_pending: 0,
    queue_failed: 0,
    storage_used: '0 B',
    memory_used: '0 B',
    memory_total: '0 B',
  })

  const activity = ref([])

  let refreshTimer = null

  function formatRelativeTime(dateStr) {
    if (!dateStr) return ''
    const diff = Date.now() - new Date(dateStr).getTime()
    const seconds = Math.floor(diff / 1000)
    if (seconds < 60) return `${seconds}s ago`
    const minutes = Math.floor(seconds / 60)
    if (minutes < 60) return `${minutes}m ago`
    const hours = Math.floor(minutes / 60)
    if (hours < 24) return `${hours}h ago`
    const days = Math.floor(hours / 24)
    return `${days}d ago`
  }

  function getInitials(name) {
    if (!name) return '?'
    return name
      .split(' ')
      .map((p) => p[0])
      .join('')
      .toUpperCase()
      .slice(0, 2)
  }

  async function fetchDashboard(silent = false) {
    if (!silent) loading.value = true
    else refreshing.value = true

    try {
      const response = await api.get('/admin/dashboard')
      const data = response?.data?.data ?? response?.data ?? {}
      stats.value = { ...stats.value, ...data.stats }
      activity.value = data.activity ?? []
    } catch {
      if (!silent) {
        toast({ title: 'Failed to load dashboard', variant: 'destructive' })
      }
    } finally {
      loading.value = false
      refreshing.value = false
    }
  }

  async function refresh() {
    await fetchDashboard(true)
  }

  onMounted(() => {
    fetchDashboard()
    refreshTimer = setInterval(() => fetchDashboard(true), 30000)
  })

  onUnmounted(() => {
    clearInterval(refreshTimer)
  })
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-50">Dashboard</h1>
      <button
        :disabled="refreshing"
        class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 transition-colors"
        @click="refresh"
      >
        <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': refreshing }" />
        Refresh
      </button>
    </div>

    <!-- Loading skeleton -->
    <template v-if="loading">
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div
          v-for="i in 8"
          :key="i"
          class="h-28 animate-pulse rounded-xl bg-gray-200 dark:bg-gray-800"
        />
      </div>
      <div class="h-64 animate-pulse rounded-xl bg-gray-200 dark:bg-gray-800" />
    </template>

    <template v-else>
      <!-- Primary stats row -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <!-- Total Users -->
        <div
          class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5"
        >
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Users</span>
            <div
              class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30"
            >
              <Users class="h-5 w-5 text-blue-600 dark:text-blue-400" />
            </div>
          </div>
          <p class="text-3xl font-bold text-gray-900 dark:text-gray-50">
            {{ stats.total_users.toLocaleString() }}
          </p>
          <p class="mt-1 text-xs text-green-600 dark:text-green-400">
            +{{ stats.new_users_this_week }} this week
          </p>
        </div>

        <!-- Total Conversations -->
        <div
          class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5"
        >
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Conversations</span>
            <div
              class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/30"
            >
              <MessageSquare class="h-5 w-5 text-violet-600 dark:text-violet-400" />
            </div>
          </div>
          <p class="text-3xl font-bold text-gray-900 dark:text-gray-50">
            {{ stats.total_conversations.toLocaleString() }}
          </p>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">all time</p>
        </div>

        <!-- Total Messages -->
        <div
          class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5"
        >
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Messages</span>
            <div
              class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 dark:bg-emerald-900/30"
            >
              <MessagesSquare class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
            </div>
          </div>
          <p class="text-3xl font-bold text-gray-900 dark:text-gray-50">
            {{ stats.total_messages.toLocaleString() }}
          </p>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">all time</p>
        </div>

        <!-- Active Models -->
        <div
          class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5"
        >
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Models</span>
            <div
              class="flex h-9 w-9 items-center justify-center rounded-lg bg-orange-50 dark:bg-orange-900/30"
            >
              <Cpu class="h-5 w-5 text-orange-600 dark:text-orange-400" />
            </div>
          </div>
          <p class="text-3xl font-bold text-gray-900 dark:text-gray-50">
            {{ stats.active_models }}
          </p>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">enabled</p>
        </div>
      </div>

      <!-- Secondary stats row -->
      <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <!-- Active Users 24h -->
        <div
          class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5"
        >
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Users</span>
            <div
              class="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-50 dark:bg-teal-900/30"
            >
              <UserCheck class="h-5 w-5 text-teal-600 dark:text-teal-400" />
            </div>
          </div>
          <p class="text-3xl font-bold text-gray-900 dark:text-gray-50">
            {{ stats.active_users_24h.toLocaleString() }}
          </p>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">last 24 hours</p>
        </div>

        <!-- Queue Health -->
        <div
          class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5"
        >
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Queue Health</span>
            <div
              class="flex h-9 w-9 items-center justify-center rounded-lg"
              :class="
                stats.queue_failed > 0
                  ? 'bg-red-50 dark:bg-red-900/30'
                  : 'bg-green-50 dark:bg-green-900/30'
              "
            >
              <Activity
                class="h-5 w-5"
                :class="
                  stats.queue_failed > 0
                    ? 'text-red-600 dark:text-red-400'
                    : 'text-green-600 dark:text-green-400'
                "
              />
            </div>
          </div>
          <p class="text-3xl font-bold text-gray-900 dark:text-gray-50">
            {{ stats.queue_pending }}
          </p>
          <p
            class="mt-1 text-xs"
            :class="
              stats.queue_failed > 0
                ? 'text-red-600 dark:text-red-400'
                : 'text-gray-500 dark:text-gray-400'
            "
          >
            {{ stats.queue_failed }} failed jobs
          </p>
        </div>

        <!-- Storage -->
        <div
          class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5"
        >
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Storage Used</span>
            <div
              class="flex h-9 w-9 items-center justify-center rounded-lg bg-sky-50 dark:bg-sky-900/30"
            >
              <HardDrive class="h-5 w-5 text-sky-600 dark:text-sky-400" />
            </div>
          </div>
          <p class="text-3xl font-bold text-gray-900 dark:text-gray-50">
            {{ stats.storage_used }}
          </p>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">MinIO</p>
        </div>

        <!-- System Memory -->
        <div
          class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5"
        >
          <div class="flex items-center justify-between mb-3">
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">System Memory</span>
            <div
              class="flex h-9 w-9 items-center justify-center rounded-lg bg-pink-50 dark:bg-pink-900/30"
            >
              <MemoryStick class="h-5 w-5 text-pink-600 dark:text-pink-400" />
            </div>
          </div>
          <p class="text-3xl font-bold text-gray-900 dark:text-gray-50">
            {{ stats.memory_used }}
          </p>
          <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">of {{ stats.memory_total }}</p>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900">
        <div class="flex items-center border-b border-gray-200 dark:border-gray-800 px-5 py-4">
          <h2 class="text-base font-semibold text-gray-900 dark:text-gray-50">Recent Activity</h2>
        </div>

        <div
          v-if="activity.length === 0"
          class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400"
        >
          No recent activity
        </div>

        <ul v-else class="divide-y divide-gray-100 dark:divide-gray-800">
          <li v-for="entry in activity" :key="entry.id" class="flex items-start gap-3 px-5 py-3">
            <div
              class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-200 dark:bg-gray-700 text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase"
            >
              {{ getInitials(entry.causer_name) }}
            </div>
            <div class="min-w-0 flex-1">
              <p class="text-sm text-gray-800 dark:text-gray-200">
                <span class="font-medium">{{ entry.causer_name ?? 'System' }}</span>
                {{ ' ' }}{{ entry.description }}
              </p>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                {{ formatRelativeTime(entry.created_at) }}
              </p>
            </div>
          </li>
        </ul>
      </div>
    </template>
  </div>
</template>
