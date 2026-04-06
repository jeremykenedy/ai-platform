<script setup>
import { ref, watch, onUnmounted } from 'vue'

const props = defineProps({
  isActive: {
    type: Boolean,
    default: false,
  },
})

const BAR_COUNT = 24
const bars = ref(Array(BAR_COUNT).fill(4))
const elapsed = ref(0)
const useWebAudio = ref(true)

let animationId = null
let analyser = null
let dataArray = null
let audioStream = null
let audioContext = null
let timerInterval = null

function formatDuration(secs) {
  const s = Math.floor(secs % 60)
  const m = Math.floor(secs / 60)
  return `${m}:${s.toString().padStart(2, '0')}`
}

async function startWebAudio() {
  try {
    audioStream = await navigator.mediaDevices.getUserMedia({ audio: true })
    audioContext = new AudioContext()
    const source = audioContext.createMediaStreamSource(audioStream)
    analyser = audioContext.createAnalyser()
    analyser.fftSize = 64
    dataArray = new Uint8Array(analyser.frequencyBinCount)
    source.connect(analyser)
    useWebAudio.value = true
    drawFrame()
  } catch {
    useWebAudio.value = false
  }
}

function drawFrame() {
  if (!analyser) return
  analyser.getByteFrequencyData(dataArray)
  const step = Math.floor(dataArray.length / BAR_COUNT)
  bars.value = Array.from({ length: BAR_COUNT }, (_, i) => {
    const val = dataArray[i * step] ?? 0
    return Math.max(4, Math.round((val / 255) * 32))
  })
  animationId = requestAnimationFrame(drawFrame)
}

function stopWebAudio() {
  if (animationId) {
    cancelAnimationFrame(animationId)
    animationId = null
  }
  if (audioStream) {
    audioStream.getTracks().forEach((t) => t.stop())
    audioStream = null
  }
  if (audioContext) {
    audioContext.close()
    audioContext = null
  }
  analyser = null
  dataArray = null
  bars.value = Array(BAR_COUNT).fill(4)
}

function startTimer() {
  elapsed.value = 0
  timerInterval = setInterval(() => {
    elapsed.value++
  }, 1000)
}

function stopTimer() {
  if (timerInterval) {
    clearInterval(timerInterval)
    timerInterval = null
  }
}

watch(
  () => props.isActive,
  async (active) => {
    if (active) {
      startTimer()
      await startWebAudio()
    } else {
      stopTimer()
      stopWebAudio()
    }
  },
  { immediate: true },
)

onUnmounted(() => {
  stopWebAudio()
  stopTimer()
})
</script>

<template>
  <div class="flex items-center gap-2">
    <!-- Recording indicator dot -->
    <span
      class="h-2.5 w-2.5 shrink-0 rounded-full bg-rose-500"
      :class="isActive ? 'animate-pulse' : 'opacity-40'"
    />

    <!-- Waveform bars -->
    <div class="flex h-8 items-end gap-px">
      <div
        v-for="(height, i) in bars"
        :key="i"
        class="w-1 rounded-t-sm bg-rose-400 transition-all duration-75 dark:bg-rose-500"
        :class="!isActive ? 'animate-pulse' : ''"
        :style="{ height: `${height}px` }"
      />
    </div>

    <!-- Duration counter -->
    <span class="min-w-[36px] text-right text-xs tabular-nums text-neutral-500 dark:text-neutral-400">
      {{ formatDuration(elapsed) }}
    </span>
  </div>
</template>
