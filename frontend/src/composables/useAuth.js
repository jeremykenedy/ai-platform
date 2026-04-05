import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

export function useAuth() {
  const store = useAuthStore()
  const router = useRouter()

  const isAuthenticated = computed(() => store.isAuthenticated)
  const user = computed(() => store.user)
  const isAdmin = computed(() => store.isAdmin)

  async function login(email, password) {
    await store.login(email, password)
    const redirect = router.currentRoute.value.query.redirect
    router.push(redirect || '/c/new')
  }

  async function logout() {
    await store.logout()
    router.push('/login')
  }

  return { isAuthenticated, user, isAdmin, login, logout }
}
