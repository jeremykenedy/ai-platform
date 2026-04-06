<script setup>
  import { ref, computed, onMounted, onUnmounted } from 'vue'
  import { Image, FileText, FileCode, File, X } from 'lucide-vue-next'

  const props = defineProps({
    file: {
      type: Object,
      required: true,
    },
  })

  const emit = defineEmits(['remove'])

  const thumbnailUrl = ref(null)

  const isImage = computed(() => {
    const mime = props.file.mime_type ?? props.file.type ?? ''
    return mime.startsWith('image/')
  })

  const isCode = computed(() => {
    const name = props.file.name ?? ''
    const codeExts = [
      'js',
      'ts',
      'jsx',
      'tsx',
      'vue',
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
      'css',
      'html',
      'json',
      'xml',
      'yaml',
      'yml',
    ]
    const ext = name.split('.').pop()?.toLowerCase()
    return codeExts.includes(ext)
  })

  const isPdf = computed(() => {
    const mime = props.file.mime_type ?? props.file.type ?? ''
    return mime === 'application/pdf' || (props.file.name ?? '').endsWith('.pdf')
  })

  const icon = computed(() => {
    if (isImage.value) return Image
    if (isCode.value) return FileCode
    if (isPdf.value) return FileText
    return File
  })

  const fileName = computed(() => props.file.name ?? 'Unknown file')

  const truncatedName = computed(() => {
    const name = fileName.value
    if (name.length <= 24) return name
    const ext = name.includes('.') ? '.' + name.split('.').pop() : ''
    const base = name.slice(0, 24 - ext.length - 3)
    return base + '...' + ext
  })

  const fileSize = computed(() => {
    const bytes = props.file.size ?? 0
    if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
    if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`
    return `${bytes} B`
  })

  onMounted(() => {
    if (isImage.value && props.file instanceof File) {
      thumbnailUrl.value = URL.createObjectURL(props.file)
    } else if (isImage.value && props.file.path) {
      thumbnailUrl.value = props.file.path
    }
  })

  onUnmounted(() => {
    if (thumbnailUrl.value && props.file instanceof File) {
      URL.revokeObjectURL(thumbnailUrl.value)
    }
  })
</script>

<template>
  <div
    class="group relative flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50 px-2.5 py-1.5 dark:border-neutral-700 dark:bg-neutral-800"
    :title="fileName"
  >
    <!-- Thumbnail or icon -->
    <div class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded">
      <img
        v-if="thumbnailUrl"
        :src="thumbnailUrl"
        :alt="fileName"
        class="h-full w-full object-cover"
      />
      <component :is="icon" v-else class="h-4 w-4 text-neutral-400 dark:text-neutral-500" />
    </div>

    <!-- Name + size -->
    <div class="min-w-0">
      <div class="text-xs font-medium text-neutral-800 dark:text-neutral-200">
        {{ truncatedName }}
      </div>
      <div class="text-[10px] text-neutral-400 dark:text-neutral-500">{{ fileSize }}</div>
    </div>

    <!-- Remove button -->
    <button
      class="ml-1 flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-neutral-400 transition-colors hover:bg-neutral-200 hover:text-neutral-700 dark:hover:bg-neutral-700 dark:hover:text-neutral-200"
      :title="`Remove ${fileName}`"
      @click.stop="emit('remove')"
    >
      <X class="h-3 w-3" />
    </button>
  </div>
</template>
