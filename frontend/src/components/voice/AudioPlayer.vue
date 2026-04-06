<script setup>
  import { ref, computed, watch, onUnmounted } from 'vue'
  import { Play, Pause, X, ChevronDown } from 'lucide-vue-next'

  const props = defineProps({
    audioUrl: {
      type: String,
      default: null,
    },
    audioData: {
      type: [ArrayBuffer, Blob],
      default: null,
    },
  })

  const emit = defineEmits(['close'])

  const audioEl = ref(null)
  const isPlaying = ref(false)
  const currentTime = ref(0)
  const duration = ref(0)
  const isSeeking = ref(false)
  const showSpeedMenu = ref(false)
  const speed = ref(1)
  const objectUrl = ref(null)

  const SPEEDS = [0.5, 0.75, 1, 1.25, 1.5, 2]

  const src = computed(() => {
    if (props.audioUrl) return props.audioUrl
    if (objectUrl.value) return objectUrl.value
    return null
  })

  watch(
    () => props.audioData,
    (data) => {
      if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
      if (data instanceof Blob) {
        objectUrl.value = URL.createObjectURL(data)
      } else if (data instanceof ArrayBuffer) {
        objectUrl.value = URL.createObjectURL(new Blob([data]))
      } else {
        objectUrl.value = null
      }
    },
    { immediate: true }
  )

  onUnmounted(() => {
    if (objectUrl.value) URL.revokeObjectURL(objectUrl.value)
  })

  function formatTime(secs) {
    const s = Math.floor(secs % 60)
    const m = Math.floor(secs / 60)
    return `${m}:${s.toString().padStart(2, '0')}`
  }

  const progressPercent = computed(() => {
    if (!duration.value) return 0
    return (currentTime.value / duration.value) * 100
  })

  function onTimeUpdate() {
    if (!isSeeking.value) {
      currentTime.value = audioEl.value?.currentTime ?? 0
    }
  }

  function onDurationChange() {
    duration.value = audioEl.value?.duration ?? 0
  }

  function onEnded() {
    isPlaying.value = false
    currentTime.value = 0
  }

  async function togglePlay() {
    if (!audioEl.value) return
    if (isPlaying.value) {
      audioEl.value.pause()
      isPlaying.value = false
    } else {
      await audioEl.value.play()
      isPlaying.value = true
    }
  }

  function seekTo(e) {
    if (!audioEl.value || !duration.value) return
    const rect = e.currentTarget.getBoundingClientRect()
    const ratio = (e.clientX - rect.left) / rect.width
    const time = Math.max(0, Math.min(duration.value, ratio * duration.value))
    audioEl.value.currentTime = time
    currentTime.value = time
  }

  function setSpeed(s) {
    speed.value = s
    if (audioEl.value) audioEl.value.playbackRate = s
    showSpeedMenu.value = false
  }

  function onClose() {
    if (audioEl.value) {
      audioEl.value.pause()
      isPlaying.value = false
    }
    emit('close')
  }
</script>

<template>
  <div
    class="flex items-center gap-3 rounded-xl border border-neutral-200 bg-white px-3 py-2 shadow-sm dark:border-neutral-700 dark:bg-neutral-800"
  >
    <!-- Hidden audio element -->
    <audio
      ref="audioEl"
      :src="src"
      :playbackRate="speed"
      preload="metadata"
      @timeupdate="onTimeUpdate"
      @durationchange="onDurationChange"
      @ended="onEnded"
      @play="isPlaying = true"
      @pause="isPlaying = false"
    />

    <!-- Play / pause -->
    <button
      class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-neutral-900 text-white transition-opacity hover:opacity-80 dark:bg-white dark:text-neutral-900"
      @click="togglePlay"
    >
      <Pause v-if="isPlaying" class="h-4 w-4" />
      <Play v-else class="ml-0.5 h-4 w-4" />
    </button>

    <!-- Progress + times -->
    <div class="flex min-w-0 flex-1 flex-col gap-1">
      <!-- Progress bar -->
      <div
        class="group relative h-1.5 cursor-pointer rounded-full bg-neutral-200 dark:bg-neutral-700"
        @click="seekTo"
      >
        <div
          class="h-full rounded-full bg-neutral-900 transition-all dark:bg-white"
          :style="{ width: progressPercent + '%' }"
        />
        <div
          class="absolute top-1/2 -translate-y-1/2 h-3 w-3 rounded-full border-2 border-neutral-900 bg-white opacity-0 transition-all group-hover:opacity-100 dark:border-white dark:bg-neutral-900"
          :style="{ left: `calc(${progressPercent}% - 6px)` }"
        />
      </div>

      <!-- Times -->
      <div class="flex items-center justify-between">
        <span class="text-[10px] tabular-nums text-neutral-400">{{ formatTime(currentTime) }}</span>
        <span class="text-[10px] tabular-nums text-neutral-400">{{ formatTime(duration) }}</span>
      </div>
    </div>

    <!-- Speed control -->
    <div class="relative shrink-0">
      <button
        class="flex items-center gap-0.5 rounded px-1.5 py-1 text-xs font-medium text-neutral-500 transition-colors hover:bg-neutral-100 hover:text-neutral-800 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-neutral-200"
        @click="showSpeedMenu = !showSpeedMenu"
      >
        {{ speed }}x
        <ChevronDown class="h-3 w-3" />
      </button>

      <Transition
        enter-active-class="transition duration-100 ease-out"
        enter-from-class="opacity-0 scale-95"
        enter-to-class="opacity-100 scale-100"
        leave-active-class="transition duration-75 ease-in"
        leave-from-class="opacity-100 scale-100"
        leave-to-class="opacity-0 scale-95"
      >
        <div
          v-if="showSpeedMenu"
          class="absolute bottom-full right-0 mb-1 min-w-[72px] overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-neutral-800"
        >
          <button
            v-for="s in SPEEDS"
            :key="s"
            class="block w-full px-3 py-1.5 text-left text-xs font-medium transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-700"
            :class="
              s === speed
                ? 'text-blue-600 dark:text-blue-400'
                : 'text-neutral-700 dark:text-neutral-300'
            "
            @click="setSpeed(s)"
          >
            {{ s }}x
          </button>
        </div>
      </Transition>
    </div>

    <!-- Close -->
    <button
      class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-neutral-400 transition-colors hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200"
      @click="onClose"
    >
      <X class="h-4 w-4" />
    </button>
  </div>
</template>
