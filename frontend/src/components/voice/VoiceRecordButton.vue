<script setup>
  import { ref, computed, onUnmounted } from 'vue'
  import { Mic, Loader2 } from 'lucide-vue-next'
  import { useVoice } from '@/composables/useVoice'
  import WaveformVisualizer from './WaveformVisualizer.vue'

  const emit = defineEmits(['transcript', 'recording'])

  const { isRecording, transcript, startRecording, stopRecording } = useVoice()
  const isProcessing = ref(false)
  const holdTimer = ref(null)
  const isHoldMode = ref(false)

  const state = computed(() => {
    if (isProcessing.value) return 'processing'
    if (isRecording.value) return 'recording'
    return 'idle'
  })

  async function handleClick() {
    if (isProcessing.value) return

    if (isRecording.value) {
      await endRecording()
    } else {
      await beginRecording()
    }
  }

  async function beginRecording() {
    try {
      await startRecording()
      emit('recording', true)
    } catch {
      // Permission error handled by MicrophonePermission component upstream
    }
  }

  async function endRecording() {
    stopRecording()
    emit('recording', false)
    isProcessing.value = true
    // Wait for transcript to populate (the composable sends to API and sets transcript)
    const maxWait = 15000
    const step = 200
    let waited = 0
    await new Promise((resolve) => {
      const check = setInterval(() => {
        waited += step
        if (transcript.value || waited >= maxWait) {
          clearInterval(check)
          resolve()
        }
      }, step)
    })
    if (transcript.value) {
      emit('transcript', transcript.value)
      transcript.value = ''
    }
    isProcessing.value = false
  }

  // Hold-to-talk
  function onPointerDown(_e) {
    holdTimer.value = setTimeout(async () => {
      isHoldMode.value = true
      await beginRecording()
    }, 150)
  }

  function onPointerUp(_e) {
    if (holdTimer.value) {
      clearTimeout(holdTimer.value)
      holdTimer.value = null
    }
    if (isHoldMode.value && isRecording.value) {
      isHoldMode.value = false
      endRecording()
    }
  }

  onUnmounted(() => {
    if (holdTimer.value) clearTimeout(holdTimer.value)
  })
</script>

<template>
  <div class="flex flex-col items-center gap-2">
    <!-- Waveform shown while recording -->
    <Transition
      enter-active-class="transition duration-200"
      enter-from-class="opacity-0 -translate-y-1"
      enter-to-class="opacity-100 translate-y-0"
      leave-active-class="transition duration-150"
      leave-from-class="opacity-100 translate-y-0"
      leave-to-class="opacity-0 -translate-y-1"
    >
      <div v-if="state === 'recording'" class="flex items-center">
        <WaveformVisualizer :is-active="isRecording" />
      </div>
    </Transition>

    <!-- Button -->
    <button
      class="relative flex h-10 w-10 items-center justify-center rounded-full transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
      :class="{
        'bg-neutral-100 hover:bg-neutral-200 text-neutral-600 dark:bg-neutral-800 dark:hover:bg-neutral-700 dark:text-neutral-400':
          state === 'idle',
        'bg-rose-500 text-white shadow-lg shadow-rose-500/30 scale-110 ring-4 ring-rose-500/20':
          state === 'recording',
        'bg-neutral-100 text-neutral-400 cursor-not-allowed dark:bg-neutral-800':
          state === 'processing',
      }"
      :disabled="isProcessing"
      @click="handleClick"
      @pointerdown="onPointerDown"
      @pointerup="onPointerUp"
      @pointerleave="onPointerUp"
    >
      <!-- Recording pulse ring -->
      <span
        v-if="state === 'recording'"
        class="absolute inset-0 rounded-full bg-rose-500 animate-ping opacity-20"
      />

      <Loader2 v-if="state === 'processing'" class="h-5 w-5 animate-spin" />
      <Mic v-else class="h-5 w-5" />
    </button>

    <!-- Hold-to-talk hint -->
    <p v-if="state === 'idle'" class="text-[10px] text-neutral-400 dark:text-neutral-500">
      Hold to talk
    </p>
    <p v-else-if="state === 'recording'" class="text-[10px] text-rose-500">Recording...</p>
    <p v-else-if="state === 'processing'" class="text-[10px] text-neutral-400">Processing...</p>
  </div>
</template>
