<script setup>
  import { ref, computed } from 'vue'
  import { cn } from '@/lib/utils'
  import { useRoute, RouterLink } from 'vue-router'
  import {
    Settings,
    BrainCircuit,
    Bot,
    BookOpenText,
    Plug,
    Mic,
    Palette,
    ArrowLeft,
    ChevronDown,
  } from 'lucide-vue-next'
  const route = useRoute()

  const mobileMenuOpen = ref(false)

  const navLinks = [
    { to: '/settings/general', label: 'General', icon: Settings },
    { to: '/settings/models', label: 'Models', icon: BrainCircuit },
    { to: '/settings/personas', label: 'Personas', icon: Bot },
    { to: '/settings/memory', label: 'Memory', icon: BookOpenText },
    { to: '/settings/integrations', label: 'Integrations', icon: Plug },
    { to: '/settings/voice', label: 'Voice', icon: Mic },
    { to: '/settings/appearance', label: 'Appearance', icon: Palette },
  ]

  function isActive(path) {
    return route.path === path || route.path.startsWith(path + '/')
  }

  const activeLink = computed(() => navLinks.find((l) => isActive(l.to)) ?? navLinks[0])

  function toggleMobileMenu() {
    mobileMenuOpen.value = !mobileMenuOpen.value
  }

  function closeMobileMenu() {
    mobileMenuOpen.value = false
  }
</script>

<template>
  <div class="flex h-screen overflow-hidden bg-gray-50 dark:bg-gray-950">
    <aside
      :class="
        cn(
          'hidden md:flex w-52 shrink-0 flex-col border-r',
          'border-gray-200 dark:border-gray-800',
          'bg-white dark:bg-gray-900'
        )
      "
    >
      <div
        :class="cn('flex h-14 items-center border-b px-4', 'border-gray-200 dark:border-gray-800')"
      >
        <span class="text-base font-semibold text-gray-900 dark:text-gray-50">Settings</span>
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
            'flex h-14 items-center justify-between border-b px-4 md:px-6',
            'border-gray-200 dark:border-gray-800',
            'bg-white dark:bg-gray-900'
          )
        "
      >
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-50">Settings</h1>
        <RouterLink
          to="/c/new"
          :class="
            cn(
              'hidden md:flex items-center gap-1.5 text-sm font-medium transition-colors',
              'text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-50'
            )
          "
        >
          <ArrowLeft class="h-4 w-4" />
          Back to Chat
        </RouterLink>

        <button
          class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-1.5 text-sm font-medium text-gray-700 dark:text-gray-300 md:hidden"
          @click="toggleMobileMenu"
        >
          <component :is="activeLink.icon" class="h-4 w-4" />
          {{ activeLink.label }}
          <ChevronDown
            :class="cn('h-4 w-4 transition-transform', mobileMenuOpen && 'rotate-180')"
          />
        </button>
      </header>

      <div
        v-if="mobileMenuOpen"
        :class="
          cn('md:hidden border-b bg-white dark:bg-gray-900', 'border-gray-200 dark:border-gray-800')
        "
      >
        <nav class="flex flex-col gap-1 p-2">
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
            @click="closeMobileMenu"
          >
            <component :is="link.icon" class="h-4 w-4 shrink-0" />
            {{ link.label }}
          </RouterLink>
          <RouterLink
            to="/c/new"
            :class="
              cn(
                'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
                'dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-50'
              )
            "
            @click="closeMobileMenu"
          >
            <ArrowLeft class="h-4 w-4 shrink-0" />
            Back to Chat
          </RouterLink>
        </nav>
      </div>

      <main class="flex-1 overflow-y-auto p-4 md:p-6">
        <router-view />
      </main>
    </div>
  </div>
</template>
