<script setup>
  import { cn } from '@/lib/utils'
  import { useRoute, RouterLink } from 'vue-router'
  import { LayoutDashboard, Users, BrainCircuit, GraduationCap, ArrowLeft } from 'lucide-vue-next'

  const route = useRoute()

  const navLinks = [
    { to: '/admin/dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { to: '/admin/users', label: 'Users', icon: Users },
    { to: '/admin/models', label: 'Models', icon: BrainCircuit },
    { to: '/admin/training', label: 'Training', icon: GraduationCap },
  ]

  function isActive(path) {
    return route.path.startsWith(path)
  }
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-gray-50 dark:bg-gray-950">
    <aside
      :class="
        cn(
          'flex w-56 shrink-0 flex-col border-r',
          'border-gray-200 dark:border-gray-800',
          'bg-white dark:bg-gray-900'
        )
      "
    >
      <div
        :class="cn('flex h-14 items-center border-b px-4', 'border-gray-200 dark:border-gray-800')"
      >
        <span class="text-base font-semibold text-gray-900 dark:text-gray-50">Admin</span>
      </div>

      <nav class="flex flex-1 flex-col gap-1 p-2 overflow-y-auto">
        <RouterLink
          v-for="link in navLinks"
          :key="link.to"
          :to="link.to"
          :class="
            cn(
              'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
              isActive(link.to)
                ? 'bg-gray-100 text-gray-900 dark:bg-gray-800 dark:text-gray-50'
                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-50'
            )
          "
        >
          <component :is="link.icon" class="h-4 w-4 shrink-0" />
          {{ link.label }}
        </RouterLink>
      </nav>

      <div :class="cn('border-t p-2', 'border-gray-200 dark:border-gray-800')">
        <RouterLink
          to="/c/new"
          :class="
            cn(
              'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
              'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
              'dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-50'
            )
          "
        >
          <ArrowLeft class="h-4 w-4 shrink-0" />
          Back to Chat
        </RouterLink>
      </div>
    </aside>

    <div class="flex flex-1 flex-col overflow-hidden">
      <header
        :class="
          cn(
            'flex h-14 items-center border-b px-6',
            'border-gray-200 dark:border-gray-800',
            'bg-white dark:bg-gray-900'
          )
        "
      >
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-50">Admin</h1>
      </header>

      <main class="flex-1 overflow-y-auto p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>
