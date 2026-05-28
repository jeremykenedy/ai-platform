<script setup>
  import { ref } from 'vue'
  import { UserX } from 'lucide-vue-next'
  import { useAuthStore } from '@/stores/auth'
  import api from '@/services/api'

  const auth = useAuthStore()
  const leaving = ref(false)

  async function leaveImpersonation() {
    leaving.value = true
    try {
      await api.post('/admin/impersonate/leave')
      await auth.fetchUser()
      window.location.href = '/admin/users'
    } catch {
      leaving.value = false
    }
  }
</script>

<template>
  <div
    v-if="auth.user?.impersonating"
    class="fixed top-0 inset-x-0 z-[60] flex items-center justify-center gap-3 bg-amber-500 px-4 py-1.5 text-sm font-medium text-amber-950"
  >
    <span>Impersonating <strong>{{ auth.user.name }}</strong></span>
    <button
      :disabled="leaving"
      class="inline-flex items-center gap-1 rounded-md bg-amber-600 px-2.5 py-0.5 text-xs font-semibold text-white hover:bg-amber-700 disabled:opacity-50 transition-colors"
      @click="leaveImpersonation"
    >
      <UserX class="h-3 w-3" />
      {{ leaving ? 'Leaving...' : 'Leave' }}
    </button>
  </div>
</template>
