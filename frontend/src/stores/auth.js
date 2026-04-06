import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const isLoading = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const isAdmin = computed(() => user.value?.role === 'admin' || user.value?.role === 'super_admin')
  const isSuperAdmin = computed(() => user.value?.role === 'super_admin')
  const permissions = computed(() => user.value?.permissions ?? [])
  const userTimezone = computed(
    () => user.value?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone
  )

  async function login(email, password) {
    const response = await api.post('/api/v1/auth/login', { email, password })
    await fetchUser()
    return response.data
  }

  async function logout() {
    await api.post('/api/v1/auth/logout')
    user.value = null
  }

  async function register(data) {
    const response = await api.post('/api/v1/auth/register', data)
    await fetchUser()
    return response.data
  }

  async function fetchUser() {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/auth/user')
      user.value = response.data.data ?? response.data
    } catch {
      user.value = null
    } finally {
      isLoading.value = false
    }
  }

  async function updateProfile(data) {
    const response = await api.patch('/api/v1/auth/user', data)
    user.value = response.data.data ?? response.data
    return response.data
  }

  return {
    user,
    isLoading,
    isAuthenticated,
    isAdmin,
    isSuperAdmin,
    permissions,
    userTimezone,
    login,
    logout,
    register,
    fetchUser,
    updateProfile,
  }
})
