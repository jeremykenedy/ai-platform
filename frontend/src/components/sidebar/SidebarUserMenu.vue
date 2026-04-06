<script setup>
  import { ref, computed } from 'vue'
  import { cn } from '@/lib/utils'
  import { useRouter } from 'vue-router'
  import { Settings, LogOut, Moon, Sun, Monitor, ShieldCheck } from 'lucide-vue-next'
  import { useAuthStore } from '@/stores/auth'
  import { useUIStore } from '@/stores/ui'

  const auth = useAuthStore()
  const ui = useUIStore()
  const router = useRouter()

  const open = ref(false)

  const initials = computed(() => {
    const name = auth.user?.name ?? auth.user?.email ?? '?'
    return name
      .split(' ')
      .slice(0, 2)
      .map((w) => w[0]?.toUpperCase() ?? '')
      .join('')
  })

  const displayName = computed(() => auth.user?.name ?? auth.user?.email ?? 'User')
  const displayEmail = computed(() => auth.user?.email ?? '')

  const themes = [
    { value: 'light', label: 'Light', icon: Sun },
    { value: 'dark', label: 'Dark', icon: Moon },
    { value: 'system', label: 'System', icon: Monitor },
  ]

  function toggle() {
    open.value = !open.value
  }

  function close() {
    open.value = false
  }

  function setTheme(value) {
    ui.setTheme(value)
  }

  async function logout() {
    close()
    await auth.logout()
    router.push('/login')
  }
</script>

<template>
  <div class="relative">
    <button
      :class="
        cn(
          'flex w-full items-center gap-3 rounded-lg px-3 py-2 transition-colors',
          'text-gray-700 dark:text-gray-300',
          'hover:bg-gray-100 dark:hover:bg-gray-800'
        )
      "
      @click="toggle"
    >
      <div
        :class="
          cn(
            'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
            'bg-primary text-primary-foreground'
          )
        "
      >
        <img
          v-if="auth.user?.avatar"
          :src="auth.user.avatar"
          :alt="displayName"
          class="h-8 w-8 rounded-full object-cover"
        />
        <span v-else>{{ initials }}</span>
      </div>

      <div class="flex min-w-0 flex-1 flex-col text-left">
        <span class="truncate text-sm font-medium text-gray-900 dark:text-gray-50">
          {{ displayName }}
        </span>
        <span class="truncate text-xs text-gray-500 dark:text-gray-400">
          {{ displayEmail }}
        </span>
      </div>
    </button>

    <Teleport to="body">
      <div v-if="open" class="fixed inset-0 z-40" @click="close" />
      <div
        v-if="open"
        :class="
          cn(
            'fixed bottom-16 left-3 z-50 w-56 rounded-xl border py-1 shadow-lg',
            'border-gray-200 dark:border-gray-700',
            'bg-white dark:bg-gray-800'
          )
        "
      >
        <div class="px-3 py-2">
          <p class="truncate text-sm font-medium text-gray-900 dark:text-gray-50">
            {{ displayName }}
          </p>
          <p class="truncate text-xs text-gray-500 dark:text-gray-400">
            {{ displayEmail }}
          </p>
        </div>

        <div class="my-1 border-t border-gray-200 dark:border-gray-700" />

        <div class="px-3 py-1.5">
          <p class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">Theme</p>
          <div class="flex gap-1">
            <button
              v-for="t in themes"
              :key="t.value"
              :class="
                cn(
                  'flex flex-1 items-center justify-center gap-1 rounded-md py-1 text-xs font-medium transition-colors',
                  ui.theme === t.value
                    ? 'bg-gray-100 text-gray-900 dark:bg-gray-700 dark:text-gray-50'
                    : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200'
                )
              "
              @click="setTheme(t.value)"
            >
              <component :is="t.icon" class="h-3.5 w-3.5" />
              {{ t.label }}
            </button>
          </div>
        </div>

        <div class="my-1 border-t border-gray-200 dark:border-gray-700" />

        <RouterLink
          to="/settings/general"
          :class="
            cn(
              'flex items-center gap-2 px-3 py-2 text-sm transition-colors',
              'text-gray-700 dark:text-gray-300',
              'hover:bg-gray-100 dark:hover:bg-gray-700'
            )
          "
          @click="close"
        >
          <Settings class="h-4 w-4" />
          Settings
        </RouterLink>

        <RouterLink
          v-if="auth.isAdmin"
          to="/admin/dashboard"
          :class="
            cn(
              'flex items-center gap-2 px-3 py-2 text-sm transition-colors',
              'text-gray-700 dark:text-gray-300',
              'hover:bg-gray-100 dark:hover:bg-gray-700'
            )
          "
          @click="close"
        >
          <ShieldCheck class="h-4 w-4" />
          Admin
        </RouterLink>

        <div class="my-1 border-t border-gray-200 dark:border-gray-700" />

        <button
          :class="
            cn(
              'flex w-full items-center gap-2 px-3 py-2 text-sm transition-colors',
              'text-red-600 dark:text-red-400',
              'hover:bg-red-50 dark:hover:bg-red-900/20'
            )
          "
          @click="logout"
        >
          <LogOut class="h-4 w-4" />
          Log out
        </button>
      </div>
    </Teleport>
  </div>
</template>
