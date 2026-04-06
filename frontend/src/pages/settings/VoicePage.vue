<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import {
  Volume2,
  Mic,
  Play,
  Square,
  CheckCircle,
  Loader2,
  AlertCircle,
  Info,
  Keyboard,
  ShieldCheck,
  ShieldAlert,
} from 'lucide-vue-next'
import { useSettingsStore } from '@/stores/settings'
import { useUiStore } from '@/stores/ui'

const settingsStore = useSettingsStore()
const uiStore = useUiStore()

const VOICES = [
  { id: 'alloy', name: 'Alloy', description: 'Neutral, balanced' },
  { id: 'echo', name: 'Echo', description: 'Male, warm' },
  { id: 'fable', name: 'Fable', description: 'British accent' },
  { id: 'onyx', name: 'Onyx', description: 'Male, deep' },
  { id: 'nova', name: 'Nova', description: 'Female, energetic' },
  { id: 'shimmer', name: 'Shimmer', description: 'Female, gentle' },
]

const tts = reactive({
  voice: 'alloy',
  speed: 1.0,
  auto_play: false,
})

const stt = reactive({
  mode: 'browser',
  silence_threshold: 2,
  continuous: false,
  push_to_talk_key: 'Space',
})

const saving = ref(false)
const previewingVoice = ref(false)
const recordingTest = ref(false)
const testAudioUrl = ref(null)
const recordingChunks = ref([])
const mediaRecorder = ref(null)
const micPermission = ref('unknown')
const listeningForKey = ref(false)

onMounted(async () => {
  await settingsStore.fetch()
  const s = settingsStore.settings
  if (s) {
    tts.voice = s.tts_voice ?? 'alloy'
    tts.speed = s.tts_speed ?? 1.0
    tts.auto_play = s.tts_auto_play ?? false
    stt.mode = s.stt_mode ?? 'browser'
    stt.silence_threshold = s.stt_silence_threshold ?? 2
    stt.continuous = s.stt_continuous ?? false
    stt.push_to_talk_key = s.stt_push_to_talk_key ?? 'Space'
  }
  checkMicPermission()
})

async function checkMicPermission() {
  if (!navigator.permissions) {
    micPermission.value = 'unknown'
    return
  }
  try {
    const result = await navigator.permissions.query({ name: 'microphone' })
    micPermission.value = result.state
    result.addEventListener('change', () => {
      micPermission.value = result.state
    })
  } catch {
    micPermission.value = 'unknown'
  }
}

async function requestMicPermission() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    stream.getTracks().forEach((t) => t.stop())
    micPermission.value = 'granted'
    uiStore.addToast({ type: 'success', message: 'Microphone access granted.' })
  } catch {
    micPermission.value = 'denied'
    uiStore.addToast({ type: 'error', message: 'Microphone access denied.' })
  }
}

async function previewVoice() {
  if (previewingVoice.value) return
  previewingVoice.value = true
  try {
    const response = await fetch(`/api/v1/tts/preview?voice=${tts.voice}&speed=${tts.speed}`)
    if (!response.ok) throw new Error()
    const blob = await response.blob()
    const url = URL.createObjectURL(blob)
    const audio = new Audio(url)
    audio.onended = () => {
      previewingVoice.value = false
      URL.revokeObjectURL(url)
    }
    audio.onerror = () => {
      previewingVoice.value = false
    }
    audio.play()
  } catch {
    previewingVoice.value = false
    uiStore.addToast({ type: 'error', message: 'Preview not available. Save settings and try from a conversation.' })
  }
}

async function startTestRecording() {
  if (micPermission.value !== 'granted') {
    await requestMicPermission()
    if (micPermission.value !== 'granted') return
  }
  testAudioUrl.value = null
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ audio: true })
    recordingChunks.value = []
    mediaRecorder.value = new MediaRecorder(stream)
    mediaRecorder.value.ondataavailable = (e) => {
      if (e.data.size > 0) recordingChunks.value.push(e.data)
    }
    mediaRecorder.value.onstop = () => {
      const blob = new Blob(recordingChunks.value, { type: 'audio/webm' })
      testAudioUrl.value = URL.createObjectURL(blob)
      stream.getTracks().forEach((t) => t.stop())
    }
    mediaRecorder.value.start()
    recordingTest.value = true
  } catch {
    uiStore.addToast({ type: 'error', message: 'Could not access microphone.' })
  }
}

function stopTestRecording() {
  mediaRecorder.value?.stop()
  recordingTest.value = false
}

function startListeningForKey() {
  listeningForKey.value = true
  window.addEventListener('keydown', captureKey, { once: true })
}

function captureKey(e) {
  e.preventDefault()
  stt.push_to_talk_key = e.code
  listeningForKey.value = false
}

onUnmounted(() => {
  window.removeEventListener('keydown', captureKey)
  if (testAudioUrl.value) URL.revokeObjectURL(testAudioUrl.value)
})

async function save() {
  saving.value = true
  try {
    await settingsStore.update({
      tts_voice: tts.voice,
      tts_speed: tts.speed,
      tts_auto_play: tts.auto_play,
      stt_mode: stt.mode,
      stt_silence_threshold: stt.silence_threshold,
      stt_continuous: stt.continuous,
      stt_push_to_talk_key: stt.push_to_talk_key,
    })
    uiStore.addToast({ type: 'success', message: 'Voice settings saved.' })
  } catch (err) {
    const msg = err?.response?.data?.message ?? 'Failed to save settings.'
    uiStore.addToast({ type: 'error', message: msg })
  } finally {
    saving.value = false
  }
}

const micPermissionIcon = computed(() => {
  if (micPermission.value === 'granted') return ShieldCheck
  return ShieldAlert
})

const micPermissionText = computed(() => ({
  granted: 'Microphone access granted',
  denied: 'Microphone access denied',
  prompt: 'Microphone permission not yet requested',
  unknown: 'Microphone permission status unknown',
}[micPermission.value] ?? 'Unknown'))

const micPermissionColor = computed(() =>
  micPermission.value === 'granted'
    ? 'text-green-600 dark:text-green-400'
    : 'text-amber-500 dark:text-amber-400',
)
</script>

<template>
  <div class="max-w-2xl mx-auto p-6 space-y-8">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Voice Settings</h1>

    <!-- Text-to-Speech -->
    <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5">
      <div class="flex items-center gap-2 mb-1">
        <Volume2 class="w-5 h-5 text-gray-500 dark:text-gray-400" />
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Text-to-Speech</h2>
      </div>

      <!-- Voice selector -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Voice</label>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
          <button
            v-for="voice in VOICES"
            :key="voice.id"
            type="button"
            class="flex flex-col items-start p-3 rounded-lg border text-left transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :class="tts.voice === voice.id
              ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 dark:border-indigo-400'
              : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-500'"
            @click="tts.voice = voice.id"
          >
            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ voice.name }}</span>
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ voice.description }}</span>
          </button>
        </div>
        <div class="mt-2">
          <button
            type="button"
            :disabled="previewingVoice"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-60 transition"
            @click="previewVoice"
          >
            <Loader2 v-if="previewingVoice" class="w-3.5 h-3.5 animate-spin" />
            <Play v-else class="w-3.5 h-3.5" />
            Preview voice
          </button>
        </div>
      </div>

      <!-- Speed -->
      <div>
        <div class="flex items-center justify-between mb-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Speed</label>
          <span class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ tts.speed.toFixed(1) }}x</span>
        </div>
        <input
          v-model.number="tts.speed"
          type="range"
          min="0.5"
          max="2.0"
          step="0.1"
          class="w-full accent-indigo-600"
        />
        <div class="flex justify-between text-xs text-gray-400 dark:text-gray-500 mt-0.5">
          <span>0.5x (slow)</span>
          <span>2.0x (fast)</span>
        </div>
      </div>

      <!-- Auto-play -->
      <div class="flex items-start gap-4">
        <button
          type="button"
          role="switch"
          :aria-checked="tts.auto_play"
          class="mt-0.5 relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
          :class="tts.auto_play ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'"
          @click="tts.auto_play = !tts.auto_play"
        >
          <span
            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
            :class="tts.auto_play ? 'translate-x-4' : 'translate-x-0.5'"
          />
        </button>
        <div>
          <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Auto-play responses</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex items-start gap-1">
            <Info class="w-3 h-3 mt-0.5 flex-shrink-0" />
            Automatically read new assistant messages aloud using TTS.
          </p>
        </div>
      </div>
    </section>

    <!-- Speech-to-Text -->
    <section class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5">
      <div class="flex items-center gap-2 mb-1">
        <Mic class="w-5 h-5 text-gray-500 dark:text-gray-400" />
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Speech-to-Text</h2>
      </div>

      <!-- Mode -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Recognition mode</label>
        <div class="grid grid-cols-2 gap-3">
          <button
            type="button"
            class="flex flex-col items-start p-3 rounded-lg border text-left transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :class="stt.mode === 'browser'
              ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 dark:border-indigo-400'
              : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-500'"
            @click="stt.mode = 'browser'"
          >
            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Browser</span>
            <span class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Uses the Web Speech API. No server required, instant results.</span>
          </button>
          <button
            type="button"
            class="flex flex-col items-start p-3 rounded-lg border text-left transition focus:outline-none focus:ring-2 focus:ring-indigo-500"
            :class="stt.mode === 'whisper'
              ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 dark:border-indigo-400'
              : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-500'"
            @click="stt.mode = 'whisper'"
          >
            <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Whisper</span>
            <span class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Server-side transcription via OpenAI Whisper. Higher accuracy, multi-language.</span>
          </button>
        </div>
      </div>

      <!-- Silence threshold -->
      <div>
        <div class="flex items-center justify-between mb-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Silence detection threshold</label>
          <span class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ stt.silence_threshold }}s</span>
        </div>
        <input
          v-model.number="stt.silence_threshold"
          type="range"
          min="1"
          max="5"
          step="0.5"
          class="w-full accent-indigo-600"
        />
        <div class="flex justify-between text-xs text-gray-400 dark:text-gray-500 mt-0.5">
          <span>1s (fast)</span>
          <span>5s (patient)</span>
        </div>
      </div>

      <!-- Continuous dictation -->
      <div class="flex items-start gap-4">
        <button
          type="button"
          role="switch"
          :aria-checked="stt.continuous"
          class="mt-0.5 relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
          :class="stt.continuous ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'"
          @click="stt.continuous = !stt.continuous"
        >
          <span
            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
            :class="stt.continuous ? 'translate-x-4' : 'translate-x-0.5'"
          />
        </button>
        <div>
          <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Continuous dictation</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Keep listening after each utterance. Sends on silence.</p>
        </div>
      </div>

      <!-- Push-to-talk key -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Push-to-talk key</label>
        <div class="flex items-center gap-3">
          <div class="flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-900 min-w-28">
            <Keyboard class="w-4 h-4 text-gray-400 dark:text-gray-500" />
            <span class="text-sm font-mono text-gray-700 dark:text-gray-300">{{ stt.push_to_talk_key }}</span>
          </div>
          <button
            type="button"
            class="px-3 py-2 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
            :class="listeningForKey ? 'bg-indigo-50 dark:bg-indigo-900/30 border-indigo-400 dark:border-indigo-500 text-indigo-700 dark:text-indigo-300' : ''"
            @click="startListeningForKey"
          >
            {{ listeningForKey ? 'Press any key...' : 'Change key' }}
          </button>
        </div>
      </div>

      <!-- Mic permission -->
      <div class="flex items-center gap-3 bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3">
        <component :is="micPermissionIcon" class="w-5 h-5 flex-shrink-0" :class="micPermissionColor" />
        <div class="flex-1">
          <p class="text-sm font-medium" :class="micPermissionColor">{{ micPermissionText }}</p>
        </div>
        <button
          v-if="micPermission.value !== 'granted'"
          type="button"
          class="px-3 py-1.5 rounded-lg text-xs font-medium bg-indigo-600 hover:bg-indigo-700 text-white transition"
          @click="requestMicPermission"
        >Request access</button>
      </div>

      <!-- Test recording -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Test microphone</label>
        <div class="flex items-center gap-3 flex-wrap">
          <button
            v-if="!recordingTest"
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-red-600 hover:bg-red-700 text-white transition"
            @click="startTestRecording"
          >
            <Mic class="w-4 h-4" /> Start recording
          </button>
          <button
            v-else
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-gray-700 dark:bg-gray-600 hover:bg-gray-800 dark:hover:bg-gray-500 text-white transition"
            @click="stopTestRecording"
          >
            <Square class="w-4 h-4" /> Stop recording
          </button>
          <span v-if="recordingTest" class="flex items-center gap-1.5 text-xs text-red-500 dark:text-red-400 animate-pulse">
            <span class="w-2 h-2 rounded-full bg-red-500 dark:bg-red-400 inline-block" />
            Recording...
          </span>
        </div>
        <div v-if="testAudioUrl" class="mt-3">
          <audio :src="testAudioUrl" controls class="w-full h-10 rounded-lg" />
        </div>
      </div>
    </section>

    <!-- Save -->
    <div class="flex justify-end">
      <button
        type="button"
        :disabled="saving"
        class="inline-flex items-center gap-2 px-5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-medium transition"
        @click="save"
      >
        <Loader2 v-if="saving" class="w-4 h-4 animate-spin" />
        <CheckCircle v-else class="w-4 h-4" />
        {{ saving ? 'Saving...' : 'Save Voice Settings' }}
      </button>
    </div>
  </div>
</template>
