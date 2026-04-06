<script setup>
  import { ref, onMounted, onUnmounted } from 'vue'
  import { Download, X, CheckCircle } from 'lucide-vue-next'

  const DISMISS_KEY = 'pwa-install-dismissed-at'
  const DISMISS_TTL = 7 * 24 * 60 * 60 * 1000 // 7 days

  const showBanner = ref(false)
  const showSuccess = ref(false)
  let deferredPrompt = null

  function isDismissedRecently() {
    const ts = localStorage.getItem(DISMISS_KEY)
    if (!ts) return false
    return Date.now() - Number(ts) < DISMISS_TTL
  }

  function isIos() {
    return /iphone|ipad|ipod/i.test(navigator.userAgent)
  }

  function handleBeforeInstallPrompt(e) {
    e.preventDefault()
    if (isDismissedRecently()) return
    deferredPrompt = e
    showBanner.value = true
  }

  async function install() {
    if (!deferredPrompt) return
    deferredPrompt.prompt()
    const { outcome } = await deferredPrompt.userChoice
    deferredPrompt = null
    showBanner.value = false
    if (outcome === 'accepted') {
      showSuccess.value = true
      setTimeout(() => {
        showSuccess.value = false
      }, 3000)
    }
  }

  function dismiss() {
    localStorage.setItem(DISMISS_KEY, String(Date.now()))
    showBanner.value = false
  }

  onMounted(() => {
    if (isIos()) return
    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
  })

  onUnmounted(() => {
    window.removeEventListener('beforeinstallprompt', handleBeforeInstallPrompt)
  })
</script>

<template>
  <Teleport to="body">
    <Transition name="slide-up">
      <div
        v-if="showBanner"
        class="fixed bottom-4 left-1/2 z-50 w-full max-w-sm -translate-x-1/2 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-lg dark:border-gray-700 dark:bg-gray-900"
        role="region"
        aria-label="Install app"
      >
        <div class="flex items-center gap-3">
          <Download class="h-5 w-5 shrink-0 text-blue-500 dark:text-blue-400" />
          <p class="flex-1 text-sm font-medium text-gray-900 dark:text-gray-100">
            Install My AI for a better experience
          </p>
          <button
            class="shrink-0 rounded p-1 text-gray-400 transition-colors hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
            aria-label="Dismiss install prompt"
            @click="dismiss"
          >
            <X class="h-4 w-4" />
          </button>
        </div>
        <div class="mt-3 flex gap-2">
          <button
            class="flex-1 rounded-lg bg-blue-600 px-3 py-1.5 text-sm font-medium text-white transition-colors hover:bg-blue-700 dark:bg-blue-500 dark:hover:bg-blue-600"
            @click="install"
          >
            Install
          </button>
          <button
            class="flex-1 rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
            @click="dismiss"
          >
            Not now
          </button>
        </div>
      </div>
    </Transition>

    <Transition name="slide-up">
      <div
        v-if="showSuccess"
        class="fixed bottom-4 left-1/2 z-50 flex -translate-x-1/2 items-center gap-2 rounded-xl bg-green-500 px-4 py-3 text-sm font-medium text-white shadow-lg dark:bg-green-600"
        role="status"
      >
        <CheckCircle class="h-4 w-4" />
        <span>App installed successfully!</span>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
  .slide-up-enter-active {
    transition:
      transform 0.3s ease,
      opacity 0.3s ease;
  }

  .slide-up-leave-active {
    transition:
      transform 0.25s ease,
      opacity 0.25s ease;
  }

  .slide-up-enter-from {
    transform: translate(-50%, 100%);
    opacity: 0;
  }

  .slide-up-leave-to {
    transform: translate(-50%, 100%);
    opacity: 0;
  }
</style>
