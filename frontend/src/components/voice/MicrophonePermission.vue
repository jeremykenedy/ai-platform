<script setup>
  import { ref } from 'vue'
  import { Mic, AlertTriangle, ExternalLink } from 'lucide-vue-next'

  const emit = defineEmits(['granted', 'denied'])

  const state = ref('prompt') // 'prompt' | 'requesting' | 'denied'

  async function requestPermission() {
    state.value = 'requesting'
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
      stream.getTracks().forEach((t) => t.stop())
      state.value = 'prompt'
      emit('granted')
    } catch {
      state.value = 'denied'
      emit('denied')
    }
  }
</script>

<template>
  <div
    class="flex items-start gap-3 rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3 dark:border-neutral-700 dark:bg-neutral-800/50"
  >
    <div
      class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-neutral-200 dark:bg-neutral-700"
    >
      <AlertTriangle v-if="state === 'denied'" class="h-4 w-4 text-amber-500" />
      <Mic v-else class="h-4 w-4 text-neutral-500 dark:text-neutral-400" />
    </div>

    <div class="flex-1">
      <template v-if="state !== 'denied'">
        <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
          Microphone access needed
        </p>
        <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
          Allow microphone access to use voice input and transcription.
        </p>
        <button
          class="mt-2 flex items-center gap-1.5 rounded-lg bg-neutral-900 px-3 py-1.5 text-xs font-medium text-white transition-opacity hover:opacity-80 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-neutral-900"
          :disabled="state === 'requesting'"
          @click="requestPermission"
        >
          <Mic class="h-3.5 w-3.5" />
          {{ state === 'requesting' ? 'Requesting...' : 'Allow Microphone' }}
        </button>
      </template>

      <template v-else>
        <p class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
          Microphone access denied
        </p>
        <p class="mt-0.5 text-xs text-neutral-500 dark:text-neutral-400">
          To enable voice input, open your browser settings and allow microphone access for this
          site, then reload the page.
        </p>
        <a
          href="https://support.google.com/chrome/answer/2693767"
          target="_blank"
          rel="noopener noreferrer"
          class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
        >
          How to enable in browser settings
          <ExternalLink class="h-3 w-3" />
        </a>
      </template>
    </div>
  </div>
</template>
