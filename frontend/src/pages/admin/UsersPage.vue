<script setup>
  import { ref, onMounted } from 'vue'
  import {
    Search, UserPlus, ChevronDown, X, Copy, Check,
    Pencil, Trash2, UserCog, Send, MoreHorizontal,
  } from 'lucide-vue-next'
  import api from '@/services/api'
  import { useToast } from '@/composables/useToast'
  import { useAuthStore } from '@/stores/auth'

  const { toast } = useToast()
  const auth = useAuthStore()

  const users = ref([])
  const total = ref(0)
  const loading = ref(true)
  const loadingMore = ref(false)
  const nextCursor = ref(null)
  const search = ref('')
  const searchDebounce = ref(null)

  // Create user modal
  const showCreateModal = ref(false)
  const createLoading = ref(false)
  const createForm = ref({ name: '', email: '', role: 'user', send_welcome: true })

  // Edit user modal
  const showEditModal = ref(false)
  const editLoading = ref(false)
  const editForm = ref({ id: '', name: '', email: '', role: 'user', password: '' })

  // Delete confirm
  const showDeleteConfirm = ref(false)
  const deleteTarget = ref(null)
  const deleteLoading = ref(false)

  // Impersonation
  const impersonateLoading = ref({})

  // Role helpers
  const roleBadgeClass = {
    'super-admin': 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
    admin: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
    user: 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300',
  }
  const roleLabel = { 'super-admin': 'Super Admin', admin: 'Admin', user: 'User' }

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
      users.value = cursor ? [...users.value, ...list] : list
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

  function openCreateModal() {
    createForm.value = { name: '', email: '', role: 'user', send_welcome: true }
    showCreateModal.value = true
  }

  async function createUser() {
    createLoading.value = true
    try {
      await api.post('/admin/users', createForm.value)
      toast({ title: 'User created' })
      showCreateModal.value = false
      await fetchUsers()
    } catch (err) {
      toast({ title: err?.response?.data?.message ?? 'Failed to create user', variant: 'destructive' })
    } finally {
      createLoading.value = false
    }
  }

  function openEditModal(user) {
    editForm.value = { id: user.id, name: user.name, email: user.email, role: user.role, password: '' }
    showEditModal.value = true
  }

  async function updateUser() {
    editLoading.value = true
    try {
      const payload = { name: editForm.value.name, email: editForm.value.email, role: editForm.value.role }
      if (editForm.value.password) payload.password = editForm.value.password
      await api.put(`/admin/users/${editForm.value.id}`, payload)
      toast({ title: 'User updated' })
      showEditModal.value = false
      await fetchUsers()
    } catch (err) {
      toast({ title: err?.response?.data?.message ?? 'Failed to update user', variant: 'destructive' })
    } finally {
      editLoading.value = false
    }
  }

  function confirmDelete(user) {
    deleteTarget.value = user
    showDeleteConfirm.value = true
  }

  async function deleteUser() {
    if (!deleteTarget.value) return
    deleteLoading.value = true
    try {
      await api.delete(`/admin/users/${deleteTarget.value.id}`)
      toast({ title: 'User deleted' })
      showDeleteConfirm.value = false
      deleteTarget.value = null
      await fetchUsers()
    } catch (err) {
      toast({ title: err?.response?.data?.message ?? 'Failed to delete user', variant: 'destructive' })
    } finally {
      deleteLoading.value = false
    }
  }

  async function impersonateUser(user) {
    impersonateLoading.value[user.id] = true
    try {
      await auth.impersonate(user.id)
      window.location.href = '/c/new'
    } catch (err) {
      toast({ title: err?.response?.data?.message ?? 'Failed to impersonate', variant: 'destructive' })
      impersonateLoading.value[user.id] = false
    }
  }

  async function toggleUserStatus(user) {
    try {
      const action = user.disabled_at ? 'enable' : 'disable'
      await api.patch(`/admin/users/${user.id}/${action}`)
      user.disabled_at = user.disabled_at ? null : new Date().toISOString()
      toast({ title: `User ${action}d` })
    } catch {
      toast({ title: 'Failed to update user status', variant: 'destructive' })
    }
  }

  async function resendWelcome(user) {
    try {
      await api.post(`/admin/users/${user.id}/resend-welcome`)
      toast({ title: 'Welcome email sent' })
    } catch (err) {
      toast({ title: err?.response?.data?.message ?? 'Failed to send email', variant: 'destructive' })
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
        class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
        @click="openCreateModal"
      >
        <UserPlus class="h-4 w-4" />
        Create User
      </button>
    </div>

    <!-- Search -->
    <div class="relative">
      <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 dark:text-gray-500 pointer-events-none" />
      <input
        v-model="search" type="text" placeholder="Search by name or email..."
        class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 pl-9 pr-4 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
        @input="onSearchInput"
      />
    </div>

    <!-- Table -->
    <div class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden">
      <!-- Loading -->
      <div v-if="loading" class="divide-y divide-gray-100 dark:divide-gray-800">
        <div v-for="i in 6" :key="i" class="flex items-center gap-3 px-5 py-4">
          <div class="h-9 w-9 shrink-0 rounded-full bg-gray-200 dark:bg-gray-700 animate-pulse" />
          <div class="flex-1 space-y-1.5">
            <div class="h-3.5 w-32 rounded bg-gray-200 dark:bg-gray-700 animate-pulse" />
            <div class="h-3 w-48 rounded bg-gray-200 dark:bg-gray-700 animate-pulse" />
          </div>
        </div>
      </div>

      <template v-else>
        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50">
                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">User</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Role</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Status</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Last Active</th>
                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
              <tr v-for="u in users" :key="u.id" class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                <td class="px-5 py-3">
                  <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-blue-500 to-violet-600 text-xs font-semibold text-white uppercase">
                      {{ getInitials(u.name) }}
                    </div>
                    <div class="min-w-0">
                      <span class="block font-medium text-gray-900 dark:text-gray-100 truncate">{{ u.name }}</span>
                      <span class="block text-xs text-gray-500 dark:text-gray-400 truncate">{{ u.email }}</span>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-3">
                  <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium" :class="roleBadgeClass[u.role] ?? roleBadgeClass.user">
                    {{ roleLabel[u.role] ?? u.role }}
                  </span>
                </td>
                <td class="px-5 py-3">
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
                    :class="u.disabled_at ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400' : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400'">
                    {{ u.disabled_at ? 'Disabled' : 'Active' }}
                  </span>
                </td>
                <td class="px-5 py-3 text-gray-500 dark:text-gray-400 text-xs">{{ formatRelativeTime(u.last_active_at) }}</td>
                <td class="px-5 py-3">
                  <div class="flex items-center justify-end gap-1.5">
                    <button title="Edit" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition-colors" @click="openEditModal(u)">
                      <Pencil class="h-3.5 w-3.5" />
                    </button>
                    <button v-if="u.role !== 'super-admin' || auth.isSuperAdmin" title="Impersonate"
                      :disabled="impersonateLoading[u.id] || u.id === auth.user?.id"
                      class="rounded-lg p-1.5 text-gray-400 hover:bg-amber-50 hover:text-amber-600 dark:hover:bg-amber-900/20 dark:hover:text-amber-400 transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                      @click="impersonateUser(u)">
                      <UserCog class="h-3.5 w-3.5" />
                    </button>
                    <button title="Send welcome email" class="rounded-lg p-1.5 text-gray-400 hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-900/20 dark:hover:text-blue-400 transition-colors" @click="resendWelcome(u)">
                      <Send class="h-3.5 w-3.5" />
                    </button>
                    <button title="Toggle status" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200 transition-colors" @click="toggleUserStatus(u)">
                      {{ u.disabled_at ? 'Enable' : 'Disable' }}
                    </button>
                    <button v-if="u.id !== auth.user?.id" title="Delete"
                      class="rounded-lg p-1.5 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400 transition-colors"
                      @click="confirmDelete(u)">
                      <Trash2 class="h-3.5 w-3.5" />
                    </button>
                  </div>
                </td>
              </tr>
              <tr v-if="users.length === 0">
                <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No users found</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Load more -->
        <div v-if="nextCursor" class="border-t border-gray-100 dark:border-gray-800 px-5 py-3 text-center">
          <button :disabled="loadingMore" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline disabled:opacity-50" @click="fetchUsers(nextCursor)">
            {{ loadingMore ? 'Loading...' : 'Load more' }}
          </button>
        </div>
      </template>
    </div>
  </div>

  <!-- Create User Modal -->
  <Teleport to="body">
    <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="showCreateModal = false" />
      <div class="relative w-full max-w-md rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 px-6 py-4">
          <h2 class="text-base font-semibold text-gray-900 dark:text-gray-50">Create User</h2>
          <button class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" @click="showCreateModal = false">
            <X class="h-4 w-4" />
          </button>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
            <input v-model="createForm.name" type="text" placeholder="Full name" class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
            <input v-model="createForm.email" type="email" placeholder="email@example.com" class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
            <select v-model="createForm.role" class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="user">User</option>
              <option value="admin">Admin</option>
              <option v-if="auth.isSuperAdmin" value="super-admin">Super Admin</option>
            </select>
          </div>
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="createForm.send_welcome" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500" />
            <span class="text-sm text-gray-700 dark:text-gray-300">Send welcome email</span>
          </label>
          <button :disabled="createLoading || !createForm.name || !createForm.email"
            class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
            @click="createUser">
            {{ createLoading ? 'Creating...' : 'Create User' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Edit User Modal -->
  <Teleport to="body">
    <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="showEditModal = false" />
      <div class="relative w-full max-w-md rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-2xl">
        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 px-6 py-4">
          <h2 class="text-base font-semibold text-gray-900 dark:text-gray-50">Edit User</h2>
          <button class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors" @click="showEditModal = false">
            <X class="h-4 w-4" />
          </button>
        </div>
        <div class="px-6 py-5 space-y-4">
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
            <input v-model="editForm.name" type="text" class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
            <input v-model="editForm.email" type="email" class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Role</label>
            <select v-model="editForm.role" class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500">
              <option value="user">User</option>
              <option value="admin">Admin</option>
              <option v-if="auth.isSuperAdmin" value="super-admin">Super Admin</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">New Password <span class="text-gray-400 font-normal">(leave blank to keep current)</span></label>
            <input v-model="editForm.password" type="password" placeholder="New password" autocomplete="new-password" class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500" />
          </div>
          <button :disabled="editLoading || !editForm.name || !editForm.email"
            class="w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
            @click="updateUser">
            {{ editLoading ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>

  <!-- Delete Confirm Modal -->
  <Teleport to="body">
    <div v-if="showDeleteConfirm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="showDeleteConfirm = false" />
      <div class="relative w-full max-w-sm rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-2xl">
        <div class="px-6 py-5 space-y-4">
          <h2 class="text-base font-semibold text-gray-900 dark:text-gray-50">Delete User</h2>
          <p class="text-sm text-gray-600 dark:text-gray-400">
            Are you sure you want to delete <strong>{{ deleteTarget?.name }}</strong>? This action cannot be undone.
          </p>
          <div class="flex gap-3">
            <button class="flex-1 rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors" @click="showDeleteConfirm = false">Cancel</button>
            <button :disabled="deleteLoading" class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50 transition-colors" @click="deleteUser">
              {{ deleteLoading ? 'Deleting...' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </Teleport>
</template>
