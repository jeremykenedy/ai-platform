<script setup>
  import { ref } from 'vue'
  import { Upload } from 'lucide-vue-next'
  import { useToast } from '@/composables/useToast'

  const emit = defineEmits(['files'])

  const { toast } = useToast()
  const isDragOver = ref(false)
  const dragCounter = ref(0)
  const fileInput = ref(null)

  const MAX_FILE_SIZE = 50 * 1024 * 1024 // 50 MB

  const ACCEPTED_MIME_TYPES = new Set([
    // Images
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
    'image/svg+xml',
    // PDFs
    'application/pdf',
    // Documents
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    // Text / code
    'text/plain',
    'text/html',
    'text/css',
    'text/javascript',
    'text/typescript',
    'text/csv',
    'text/markdown',
    'application/json',
    'application/xml',
    'application/x-yaml',
  ])

  const ACCEPTED_EXTENSIONS = new Set([
    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp',
    'svg',
    'pdf',
    'doc',
    'docx',
    'xls',
    'xlsx',
    'txt',
    'md',
    'csv',
    'html',
    'css',
    'js',
    'ts',
    'jsx',
    'tsx',
    'json',
    'xml',
    'yaml',
    'yml',
    'py',
    'rb',
    'php',
    'java',
    'c',
    'cpp',
    'h',
    'go',
    'rs',
    'swift',
    'kt',
    'sh',
  ])

  function isAccepted(file) {
    if (ACCEPTED_MIME_TYPES.has(file.type)) return true
    const ext = file.name.split('.').pop()?.toLowerCase()
    return ext ? ACCEPTED_EXTENSIONS.has(ext) : false
  }

  function validate(files) {
    const valid = []
    for (const file of files) {
      if (!isAccepted(file)) {
        toast({
          title: 'Unsupported file type',
          description: `"${file.name}" is not a supported file type.`,
          variant: 'destructive',
        })
        continue
      }
      if (file.size > MAX_FILE_SIZE) {
        toast({
          title: 'File too large',
          description: `"${file.name}" exceeds the 50 MB limit.`,
          variant: 'destructive',
        })
        continue
      }
      valid.push(file)
    }
    return valid
  }

  function onDragEnter(e) {
    e.preventDefault()
    dragCounter.value++
    isDragOver.value = true
  }

  function onDragLeave(e) {
    e.preventDefault()
    dragCounter.value--
    if (dragCounter.value <= 0) {
      dragCounter.value = 0
      isDragOver.value = false
    }
  }

  function onDragOver(e) {
    e.preventDefault()
  }

  function onDrop(e) {
    e.preventDefault()
    dragCounter.value = 0
    isDragOver.value = false
    const files = Array.from(e.dataTransfer?.files ?? [])
    const valid = validate(files)
    if (valid.length) emit('files', valid)
  }

  function openPicker() {
    fileInput.value?.click()
  }

  function onFileInputChange(e) {
    const files = Array.from(e.target.files ?? [])
    const valid = validate(files)
    if (valid.length) emit('files', valid)
    e.target.value = ''
  }
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isDragOver"
        class="fixed inset-0 z-50 flex items-center justify-center"
        @dragenter="onDragEnter"
        @dragleave="onDragLeave"
        @dragover="onDragOver"
        @drop="onDrop"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-blue-500/10 backdrop-blur-sm dark:bg-blue-500/10" />

        <!-- Drop target -->
        <div
          class="relative z-10 m-6 flex w-full max-w-lg flex-col items-center gap-4 rounded-2xl border-2 border-dashed border-blue-400 bg-white/90 px-8 py-14 text-center shadow-xl dark:bg-neutral-900/90"
        >
          <div
            class="flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/40"
          >
            <Upload class="h-8 w-8 text-blue-600 dark:text-blue-400" />
          </div>
          <div>
            <p class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
              Drop files here
            </p>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
              Images, PDFs, documents, and code files up to 50 MB
            </p>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>

  <!-- Invisible drag listener layer mounted on the window -->
  <div
    class="contents"
    @dragenter="onDragEnter"
    @dragleave="onDragLeave"
    @dragover="onDragOver"
    @drop="onDrop"
    @click="openPicker"
  >
    <slot />
  </div>

  <input
    ref="fileInput"
    type="file"
    multiple
    class="hidden"
    accept=".jpg,.jpeg,.png,.gif,.webp,.svg,.pdf,.doc,.docx,.xls,.xlsx,.txt,.md,.csv,.html,.css,.js,.ts,.jsx,.tsx,.json,.xml,.yaml,.yml,.py,.rb,.php,.java,.c,.cpp,.h,.go,.rs,.swift,.kt,.sh"
    @change="onFileInputChange"
  />
</template>
