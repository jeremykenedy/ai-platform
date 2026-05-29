import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const isLoading = ref(false)

  const isAuthenticated = computed(() => user.value !== null)
  const isAdmin = computed(
    () => user.value?.role === 'admin' || user.value?.role === 'super-admin' || user.value?.role === 'super_admin'
  )
  const isSuperAdmin = computed(
    () => user.value?.role === 'super-admin' || user.value?.role === 'super_admin'
  )
  const isImpersonating = computed(() => user.value?.impersonating === true)
  const permissions = computed(() => user.value?.permissions ?? [])
  const userTimezone = computed(
    () => user.value?.timezone ?? Intl.DateTimeFormat().resolvedOptions().timeZone
  )

  async function login(email, password) {
    const response = await api.post('/auth/login', { email, password })
    await fetchUser()
    return response.data
  }

  async function logout() {
    await api.post('/auth/logout')
    user.value = null
  }

  async function register(data) {
    const response = await api.post('/auth/register', data)
    await fetchUser()
    return response.data
  }

  async function fetchUser() {
    isLoading.value = true
    try {
      // 401 is the expected pre-login response; tell axios not to throw
      // so Chrome devtools doesn't log it as a failed resource.
      const response = await api.get('/auth/user', {
        validateStatus: (s) => s < 500,
      })
      user.value = response.status === 200 ? (response.data.data ?? response.data) : null
    } catch {
      user.value = null
    } finally {
      isLoading.value = false
    }
  }

  async function updateProfile(data) {
    const response = await api.patch('/auth/user', data)
    user.value = response.data.data ?? response.data
    return response.data
  }

  async function impersonate(userId) {
    await api.post(`/admin/impersonate/${userId}`)
    await fetchUser()
  }

  async function leaveImpersonation() {
    await api.post('/admin/impersonate/leave')
    await fetchUser()
  }

  return {
    user,
    isLoading,
    isAuthenticated,
    isAdmin,
    isSuperAdmin,
    isImpersonating,
    permissions,
    userTimezone,
    login,
    logout,
    register,
    fetchUser,
    updateProfile,
    impersonate,
    leaveImpersonation,
  }
})
