<script setup>
import { ref, computed, onMounted } from 'vue'
import {
  Search,
  UserPlus,
  ChevronDown,
  X,
  Copy,
  Check,
  MoreHorizontal,
  ShieldAlert,
  ShieldCheck,
  User,
} from 'lucide-vue-next'
import api from '@/services/api'
import { useToast } from '@/composables/useToast'

const { toast } = useToast()

const users = ref([])
const total = ref(0)
const loading = ref(true)
const loadingMore = ref(false)
const nextCursor = ref(null)
const search = ref('')
const searchDebounce = ref(null)

const showInviteModal = ref(false)
const inviteLoading = ref(false)
const inviteUrl = ref('')
const inviteForm = ref({ name: '', email: '', role: 'user' })

const roleUpdating = ref({})
const toggleLoading = ref({})

function getInitials(name) {
  if (!name) return '?'
  return name.split(' ').map((p) => p[0]).join('').toUpperCase().slice(0, 2)
}

function formatRelativeTime(dateStr) {
  if (!dateStr) return 'Never'
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

const roleBadgeClass = {
  'super-admin': 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
  'super_admin': 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
  admin: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
  user: 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
}

const roleLabel = {
  'super-admin': 'Super Admin',
  'super_admin': 'Super Admin',
  admin: 'Admin',
  user: 'User',
}

const roleIcon = {
  'super-admin': ShieldAlert,
  'super_admin': ShieldAlert,
  admin: ShieldCheck,
  user: User,
}

async function fetchUsers(cursor = null) {
  if (cursor) loadingMore.value = true
  else loading.value = true

  try {
    const params = { limit: 20 }
    if (cursor) params.cursor = cursor
    if (search.value) params.search = search.value

    const response = await api.get('/admin/users', { params })
    const data = response?.data ?? {}
    const list = data.data ?? []
    nextCursor.value = data.next_cursor ?? null
    total.value = data.total ?? total.value

    if (cursor) {
      users.value = [...users.value, ...list]
    } else {
      users.value = list
    }
  } catch {
    toast({ title: 'Failed to load users', variant: 'destructive' })
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

function onSearchInput() {
  clearTimeout(searchDebounce.value)
  searchDebounce.value = setTimeout(() => fetchUsers(), 400)
}

async function loadMore() {
  if (nextCursor.value) {
    await fetchUsers(nextCursor.value)
  }
}

async function updateRole(user, newRole) {
  if (user.role === newRole) return
  if (!confirm(`Change ${user.name}'s role to ${roleLabel[newRole] ?? newRole}?`)) return

  roleUpdating.value[user.id] = true
  try {
    await api.patch(`/admin/users/${user.id}/role`, { role: newRole })
    user.role = newRole
    toast({ title: 'Role updated' })
  } catch {
    toast({ title: 'Failed to update role', variant: 'destructive' })
  } finally {
    roleUpdating.value[user.id] = false
  }
}

async function toggleUserStatus(user) {
  toggleLoading.value[user.id] = true
  try {
    const action = user.disabled_at ? 'enable' : 'disable'
    await api.patch(`/admin/users/${user.id}/${action}`)
    user.disabled_at = user.disabled_at ? null : new Date().toISOString()
    toast({ title: `User ${action}d` })
  } catch {
    toast({ title: 'Failed to update user status', variant: 'destructive' })
  } finally {
    toggleLoading.value[user.id] = false
  }
}

function openInviteModal() {
  inviteForm.value = { name: '', email: '', role: 'user' }
  inviteUrl.value = ''
  showInviteModal.value = true
}

function closeInviteModal() {
  showInviteModal.value = false
  inviteUrl.value = ''
}

const copied = ref(false)

async function copyInviteUrl() {
  await navigator.clipboard.writeText(inviteUrl.value)
  copied.value = true
  setTimeout(() => { copied.value = false }, 2000)
}

async function sendInvite() {
  inviteLoading.value = true
  try {
    const response = await api.post('/admin/invitations', inviteForm.value)
    inviteUrl.value = response?.data?.data?.url ?? response?.data?.url ?? ''
    toast({ title: 'Invitation sent' })
    await fetchUsers()
  } catch (err) {
    const msg = err?.response?.data?.message ?? 'Failed to send invitation'
    toast({ title: msg, variant: 'destructive' })
  } finally {
    inviteLoading.value = false
  }
}

onMounted(() => fetchUsers())
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-50">Users</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ total.toLocaleString() }} total</p>
      </div>
      <button
        @click="openInviteModal"
        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
      >
        <UserPlus class="h-4 w-4" />
        Invite User
      </button>
    </div>

    <!-- Search -->
    <div class="relative">
      <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 dark:text-gray-500 pointer-events-none" />
      <input
        v-model="search"
        @input="onSearchInput"
        type="text"
        placeholder="Search by name or email..."
        class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 pl-9 pr-4 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
      />
    </div>

    <!-- Table -->
    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
      <!-- Loading skeleton -->
      <div v-if="loading" class="divide-y divide-gray-100 dark:divide-gray-800">
        <div v-for="i in 6" :key="i" class="flex items-center gap-3 px-5 py-4">
          <div class="h-9 w-9 shrink-0 rounded-full bg-gray-200 dark:bg-gray-700 animate-pulse" />
          <div class="flex-1 space-y-1.5">
            <div class="h-3.5 w-32 rounded bg-gray-200 dark:bg-gray-700 animate-pulse" />
            <div class="h-3 w-48 rounded bg-gray-200 dark:bg-gray-700 animate-pulse" />
          </div>
          <div class="h-6 w-16 rounded-full bg-gray-200 dark:bg-gray-700 animate-pulse" />
        </div>
      </div>

      <template v-else>
        <!-- Desktop table -->
        <div class="hidden md:block overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">User</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Email</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Role</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Last Active</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
              <tr
                v-for="u in users"
                :key="u.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
              >
                <td class="px-5 py-3">
                  <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-violet-600 text-xs font-semibold text-white uppercase">
                      {{ getInitials(u.name) }}
                    </div>
                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ u.name }}</span>
                  </div>
                </td>
                <td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ u.email }}</td>
                <td class="px-5 py-3">
                  <div class="relative inline-block">
                    <select
                      :value="u.role"
                      @change="updateRole(u, $event.target.value)"
                      :disabled="roleUpdating[u.id]"
                      class="appearance-none rounded-full px-3 py-1 text-xs font-medium pr-6 cursor-pointer border-0 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      :class="roleBadgeClass[u.role] ?? roleBadgeClass.user"
                    >
                      <option value="user">User</option>
                      <option value="admin">Admin</option>
                      <option value="super_admin">Super Admin</option>
                    </select>
                    <ChevronDown class="pointer-events-none absolute right-1.5 top-1/2 -translate-y-1/2 h-3 w-3 opacity-60" />
                  </div>
                </td>
                <td class="px-5 py-3 text-gray-500 dark:text-gray-400">
                  {{ formatRelativeTime(u.last_active_at) }}
                </td>
                <td class="px-5 py-3">
                  <span
                    class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="u.disabled_at
                      ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
                      : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'"
                  >
                    {{ u.disabled_at ? 'Disabled' : 'Active' }}
                  </span>
                </td>
                <td class="px-5 py-3 text-right">
                  <button
                    @click="toggleUserStatus(u)"
                    :disabled="toggleLoading[u.id]"
                    class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-1 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 disabled:opacity-50 transition-colors"
                  >
                    {{ u.disabled_at ? 'Enable' : 'Disable' }}
                  </button>
                </td>
              </tr>
              <tr v-if="users.length === 0">
                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                  No users found
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Mobile list -->
        <ul class="md:hidden divide-y divide-gray-100 dark:divide-gray-800">
          <li
            v-for="u in users"
            :key="u.id"
            class="flex items-start gap-3 px-4 py-3"
          >
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-violet-600 text-xs font-semibold text-white uppercase">
              {{ getInitials(u.name) }}
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium text-gray-900 dark:text-gray-100">{{ u.name }}</span>
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="roleBadgeClass[u.role] ?? roleBadgeClass.user"
                >
                  {{ roleLabel[u.role] ?? u.role }}
                </span>
              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ u.email }}</p>
              <div class="flex items-center gap-2 mt-2">
                <span
                  class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                  :class="u.disabled_at
                    ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400'
                    : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'"
                >
                  {{ u.disabled_at ? 'Disabled' : 'Active' }}
                </span>
                <button
                  @click="toggleUserStatus(u)"
                  :disabled="toggleLoading[u.id]"
                  class="text-xs text-blue-600 dark:text-blue-400 hover:underline disabled:opacity-50"
                >
                  {{ u.disabled_at ? 'Enable' : 'Disable' }}
                </button>
              </div>
            </div>
          </li>
          <li v-if="users.length === 0" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
            No users found
          </li>
        </ul>

        <!-- Load more -->
        <div v-if="nextCursor" class="border-t border-gray-100 dark:border-gray-800 px-5 py-3 text-center">
          <button
            @click="loadMore"
            :disabled="loadingMore"
            class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline disabled:opacity-50"
          >
            {{ loadingMore ? 'Loading...' : 'Load more' }}
          </button>
        </div>
      </template>
    </div>
  </div>

  <!-- Invite Modal -->
  <Teleport to="body">
    <div
      v-if="showInviteModal"
      class="fixed inset-0 z-50 flex items-center justify-center p-4"
    >
      <div class="absolute inset-0 bg-black/50" @click="closeInviteModal" />
      <div class="relative w-full max-w-md rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 px-6 py-4">
          <h2 class="text-base font-semibold text-gray-900 dark:text-gray-50">Invite User</h2>
          <button
            @click="closeInviteModal"
            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
          >
            <X class="h-4 w-4" />
          </button>
        </div>

        <div class="px-6 py-5 space-y-4">
          <!-- Success state -->
          <template v-if="inviteUrl">
            <p class="text-sm text-gray-600 dark:text-gray-400">
              Invitation created. Share this link with the user:
            </p>
            <div class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 px-3 py-2">
              <p class="min-w-0 flex-1 truncate text-xs font-mono text-gray-700 dark:text-gray-300">
                {{ inviteUrl }}
              </p>
              <button
                @click="copyInviteUrl"
                class="shrink-0 rounded-md p-1 text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors"
              >
                <Check v-if="copied" class="h-4 w-4 text-green-500" />
                <Copy v-else class="h-4 w-4" />
              </button>
            </div>
            <button
              @click="closeInviteModal"
              class="w-full rounded-lg bg-gray-100 dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
            >
              Done
            </button>
          </template>

          <!-- Form state -->
          <template v-else>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
              <input
                v-model="inviteForm.name"
                type="text"
                placeholder="Full name"
                class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
              <input
                v-model="inviteForm.email"
                type="email"
                placeholder="email@example.com"
                class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
              <select
                v-model="inviteForm.role"
                class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="user">User</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <button
              @click="sendInvite"
              :disabled="inviteLoading || !inviteForm.email"
              class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
            >
              {{ inviteLoading ? 'Sending...' : 'Send Invitation' }}
            </button>
          </template>
        </div>
      </div>
    </div>
  </Teleport>
</template>
