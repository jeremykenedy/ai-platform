<script setup>
  import { ref, computed, watch, nextTick, onUnmounted } from 'vue'
  import { Paperclip, ArrowUp, Square, Mic, MicOff, X, FileText } from 'lucide-vue-next'
  import { useMessagesStore } from '@/stores/messages'
  import { useModelsStore } from '@/stores/models'
  import { usePersonasStore } from '@/stores/personas'
  import { useSettingsStore } from '@/stores/settings'
  import { useVoice } from '@/composables/useVoice'

  const emit = defineEmits(['send', 'cancel', 'open-model-selector', 'open-persona-selector'])

  const messagesStore = useMessagesStore()
  const modelsStore = useModelsStore()
  const personasStore = usePersonasStore()
  const settingsStore = useSettingsStore()

  const { isRecording, transcript, startRecording, stopRecording } = useVoice()

  const content = ref('')
  const files = ref([])
  const isDragOver = ref(false)
  const textareaRef = ref(null)
  const fileInputRef = ref(null)

  const isStreaming = computed(() => messagesStore.isStreaming)
  const activeModel = computed(() => modelsStore.activeModel)
  const activePersona = computed(() => personasStore.activePersona)
  const sendOnEnter = computed(() => settingsStore.settings?.send_on_enter !== false)

  const canSend = computed(() => content.value.trim().length > 0 && !isStreaming.value)

  const MAX_HEIGHT = 200

  function autoResize() {
    const el = textareaRef.value
    if (!el) return
    el.style.height = 'auto'
    el.style.height = Math.min(el.scrollHeight, MAX_HEIGHT) + 'px'
  }

  function resetTextarea() {
    content.value = ''
    nextTick(() => {
      const el = textareaRef.value
      if (!el) return
      el.style.height = 'auto'
    })
  }

  function handleKeydown(e) {
    if (e.key === 'Enter') {
      if (sendOnEnter.value && !e.shiftKey) {
        e.preventDefault()
        handleSend()
      }
    }
  }

  function handleSend() {
    if (!canSend.value) return
    const text = content.value.trim()
    const attachments = [...files.value]
    emit('send', text, attachments)
    resetTextarea()
    files.value = []
  }

  function handleCancel() {
    messagesStore.cancelStream()
    emit('cancel')
  }

  function openFilePicker() {
    fileInputRef.value?.click()
  }

  function handleFileSelect(e) {
    const selected = Array.from(e.target.files ?? [])
    addFiles(selected)
    e.target.value = ''
  }

  function addFiles(newFiles) {
    for (const f of newFiles) {
      if (!files.value.find((existing) => existing.name === f.name && existing.size === f.size)) {
        files.value.push(f)
      }
    }
  }

  function removeFile(file) {
    files.value = files.value.filter((f) => f !== file)
  }

  function handleDragOver(e) {
    e.preventDefault()
    isDragOver.value = true
  }

  function handleDragLeave(e) {
    if (!e.currentTarget.contains(e.relatedTarget)) {
      isDragOver.value = false
    }
  }

  function handleDrop(e) {
    e.preventDefault()
    isDragOver.value = false
    const dropped = Array.from(e.dataTransfer?.files ?? [])
    addFiles(dropped)
  }

  async function toggleRecording() {
    if (isRecording.value) {
      stopRecording()
    } else {
      await startRecording()
    }
  }

  function handleTranscript(text) {
    content.value += (content.value ? ' ' : '') + text
    nextTick(autoResize)
  }

  // Watch transcript changes from voice composable
  const stopTranscriptWatch = watch(transcript, (val) => {
    if (val) handleTranscript(val)
  })

  onUnmounted(() => {
    stopTranscriptWatch()
  })

  function getFilePreviewUrl(file) {
    if (file.type?.startsWith('image/')) return URL.createObjectURL(file)
    return null
  }
</script>

<template>
  <div
    class="border-t border-border bg-background px-4 py-3 transition-colors"
    :class="isDragOver ? 'bg-primary/5 dark:bg-primary/10' : ''"
    @dragover="handleDragOver"
    @dragleave="handleDragLeave"
    @drop="handleDrop"
  >
    <!-- Drag overlay hint -->
    <div
      v-if="isDragOver"
      class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center rounded-lg border-2 border-dashed border-primary bg-primary/5 dark:bg-primary/10"
    >
      <p class="text-sm font-medium text-primary dark:text-primary">Drop files to attach</p>
    </div>

    <!-- File preview chips -->
    <div v-if="files.length" class="mb-2 flex flex-wrap gap-2">
      <div
        v-for="file in files"
        :key="file.name"
        class="flex items-center gap-1.5 rounded-lg border border-border bg-muted px-2 py-1 text-xs dark:border-border dark:bg-muted"
      >
        <img
          v-if="file.type?.startsWith('image/')"
          :src="getFilePreviewUrl(file)"
          class="h-5 w-5 rounded object-cover"
          :alt="file.name"
        />
        <FileText v-else class="h-3.5 w-3.5 text-muted-foreground dark:text-muted-foreground" />
        <span class="max-w-[120px] truncate text-foreground dark:text-foreground">{{
          file.name
        }}</span>
        <button
          class="ml-0.5 text-muted-foreground transition-colors hover:text-foreground dark:text-muted-foreground dark:hover:text-foreground"
          @click="removeFile(file)"
        >
          <X class="h-3 w-3" />
        </button>
      </div>
    </div>

    <!-- Input row -->
    <div
      class="flex items-end gap-2 rounded-xl border bg-muted/50 px-3 py-2 transition-colors dark:bg-muted/30"
      :class="isDragOver ? 'border-primary' : 'border-border dark:border-border'"
    >
      <!-- Attachment button -->
      <button
        title="Attach files"
        class="mb-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-background hover:text-foreground dark:text-muted-foreground dark:hover:bg-background dark:hover:text-foreground"
        @click="openFilePicker"
      >
        <Paperclip class="h-4 w-4" />
      </button>

      <!-- Textarea -->
      <textarea
        ref="textareaRef"
        v-model="content"
        :rows="1"
        :disabled="isStreaming"
        placeholder="Message..."
        class="flex-1 resize-none bg-transparent py-0.5 text-sm text-foreground outline-none placeholder:text-muted-foreground disabled:opacity-50 dark:text-foreground dark:placeholder:text-muted-foreground"
        :style="{ maxHeight: MAX_HEIGHT + 'px', overflowY: 'auto' }"
        @keydown="handleKeydown"
        @input="autoResize"
      />

      <!-- Voice record button -->
      <button
        :title="isRecording ? 'Stop recording' : 'Voice input'"
        class="mb-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md transition-colors"
        :class="
          isRecording
            ? 'bg-red-100 text-red-600 hover:bg-red-200 dark:bg-red-950/40 dark:text-red-400 dark:hover:bg-red-950/60'
            : 'text-muted-foreground hover:bg-background hover:text-foreground dark:text-muted-foreground dark:hover:bg-background dark:hover:text-foreground'
        "
        @click="toggleRecording"
      >
        <MicOff v-if="isRecording" class="h-4 w-4" />
        <Mic v-else class="h-4 w-4" />
      </button>

      <!-- Send / Stop button -->
      <button
        v-if="!isStreaming"
        :disabled="!canSend"
        title="Send message"
        class="mb-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md transition-colors"
        :class="
          canSend
            ? 'bg-primary text-primary-foreground hover:bg-primary/90 dark:bg-primary dark:text-primary-foreground dark:hover:bg-primary/90'
            : 'bg-muted text-muted-foreground/50 cursor-not-allowed dark:bg-muted dark:text-muted-foreground/40'
        "
        @click="handleSend"
      >
        <ArrowUp class="h-4 w-4" />
      </button>
      <button
        v-else
        title="Stop generation"
        class="mb-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-foreground text-background transition-colors hover:bg-foreground/80 dark:bg-foreground dark:text-background dark:hover:bg-foreground/80"
        @click="handleCancel"
      >
        <Square class="h-3.5 w-3.5" />
      </button>
    </div>

    <!-- Bottom bar: model, persona, char count -->
    <div
      class="mt-1 flex items-center justify-between px-1 text-xs text-muted-foreground dark:text-muted-foreground"
    >
      <div class="flex items-center gap-3">
        <button
          class="transition-colors hover:text-foreground dark:hover:text-foreground"
          @click="emit('open-model-selector')"
        >
          {{ activeModel?.display_name ?? activeModel?.name ?? 'Select model' }}
        </button>
        <span class="text-muted-foreground/40 dark:text-muted-foreground/30">·</span>
        <button
          class="transition-colors hover:text-foreground dark:hover:text-foreground"
          @click="emit('open-persona-selector')"
        >
          {{ activePersona?.name ?? 'No persona' }}
        </button>
      </div>
      <span v-if="content.length > 0">{{ content.length }} chars</span>
    </div>

    <input ref="fileInputRef" type="file" multiple class="hidden" @change="handleFileSelect" />
  </div>
</template>
