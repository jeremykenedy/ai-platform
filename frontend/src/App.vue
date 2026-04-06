<script setup>
  import { ref, onMounted } from 'vue'
  import { useRouter } from 'vue-router'
  import { useAuthStore } from '@/stores/auth'
  import { useUIStore } from '@/stores/ui'
  import { useTheme } from '@/composables/useTheme'
  import { useKeyboard } from '@/composables/useKeyboard'
  import { useMobile } from '@/composables/useMobile'

  import OfflineIndicator from '@/components/offline/OfflineIndicator.vue'
  import PwaInstallPrompt from '@/components/offline/PwaInstallPrompt.vue'
  import ServiceWorkerUpdate from '@/components/offline/ServiceWorkerUpdate.vue'
  import ToastContainer from '@/components/feedback/ToastContainer.vue'
  import CommandPalette from '@/components/feedback/CommandPalette.vue'
  import KeyboardShortcutHelp from '@/components/feedback/KeyboardShortcutHelp.vue'
  import LoadingScreen from '@/components/feedback/LoadingScreen.vue'
  import ErrorBoundary from '@/components/feedback/ErrorBoundary.vue'

  const router = useRouter()
  const authStore = useAuthStore()
  const ui = useUIStore()
  const { initFromServer } = useTheme()

  useMobile()

  const isInitializing = ref(true)
  const showCommandPalette = ref(false)
  const showShortcutHelp = ref(false)

  useKeyboard({
    'mod+k': () => {
      showCommandPalette.value = !showCommandPalette.value
    },
    'mod+/': () => {
      showShortcutHelp.value = !showShortcutHelp.value
    },
    'mod+n': () => {
      router.push('/c/new')
    },
    'mod+shift+s': () => {
      ui.toggleSidebar()
    },
    escape: () => {
      if (showCommandPalette.value) {
        showCommandPalette.value = false
      } else if (showShortcutHelp.value) {
        showShortcutHelp.value = false
      }
    },
  })

  onMounted(async () => {
    try {
      await authStore.fetchUser()
      if (authStore.isAuthenticated) {
        await initFromServer()
      }
    } finally {
      isInitializing.value = false
      document.documentElement.classList.add('antialiased')
    }
  })
</script>

<template>
  <div class="min-h-screen bg-background text-foreground">
    <!-- Loading screen during auth check -->
    <LoadingScreen v-if="isInitializing" message="Loading..." />

    <!-- Main app -->
    <template v-else>
      <OfflineIndicator />
      <ErrorBoundary>
        <router-view />
      </ErrorBoundary>
    </template>

    <!-- Global overlays (always rendered so transitions work) -->
    <ToastContainer />
    <PwaInstallPrompt />
    <ServiceWorkerUpdate />
    <CommandPalette v-model:show="showCommandPalette" />
    <KeyboardShortcutHelp v-model:show="showShortcutHelp" />
  </div>
</template>
