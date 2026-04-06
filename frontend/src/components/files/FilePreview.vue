<script setup>
import { ref, computed } from 'vue'
import { FileText, FileCode, File, ExternalLink, ChevronDown, ChevronUp, ZoomIn } from 'lucide-vue-next'

const props = defineProps({
  attachment: {
    type: Object,
    required: true,
  },
})

const showExpanded = ref(false)
const showFullImage = ref(false)

const isImage = computed(() => {
  const mime = props.attachment.mime_type ?? ''
  return mime.startsWith('image/')
})

const isPdf = computed(() => {
  const mime = props.attachment.mime_type ?? ''
  return mime === 'application/pdf'
})

const isText = computed(() => {
  const mime = props.attachment.mime_type ?? ''
  return (
    mime.startsWith('text/') ||
    mime === 'application/json' ||
    mime === 'application/xml' ||
    mime === 'application/x-yaml'
  )
})

const isCode = computed(() => {
  const name = props.attachment.filename ?? ''
  const codeExts = ['js', 'ts', 'jsx', 'tsx', 'vue', 'py', 'rb', 'php', 'java', 'c', 'cpp',
    'h', 'go', 'rs', 'swift', 'kt', 'sh', 'css', 'html', 'json', 'xml', 'yaml', 'yml']
  const ext = name.split('.').pop()?.toLowerCase()
  return codeExts.includes(ext)
})

const icon = computed(() => {
  if (isPdf.value || isText.value) return FileText
  if (isCode.value) return FileCode
  return File
})

const previewLines = computed(() => {
  const text = props.attachment.extracted_text ?? ''
  if (!text) return []
  const lines = text.split('\n')
  return showExpanded.value ? lines : lines.slice(0, 10)
})

const totalLines = computed(() => {
  return (props.attachment.extracted_text ?? '').split('\n').length
})

const hasMore = computed(() => totalLines.value > 10)

const detectedLanguage = computed(() => {
  const ext = (props.attachment.filename ?? '').split('.').pop()?.toLowerCase()
  const langMap = {
    js: 'javascript', ts: 'typescript', jsx: 'jsx', tsx: 'tsx',
    py: 'python', rb: 'ruby', php: 'php', java: 'java',
    c: 'c', cpp: 'cpp', h: 'c', go: 'go', rs: 'rust',
    swift: 'swift', kt: 'kotlin', sh: 'bash',
    json: 'json', xml: 'xml', yaml: 'yaml', yml: 'yaml',
    html: 'html', css: 'css', vue: 'vue',
  }
  return langMap[ext] ?? 'text'
})

function formatSize(bytes) {
  if (!bytes) return ''
  if (bytes >= 1024 * 1024) return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
  if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`
  return `${bytes} B`
}
</script>

<template>
  <div class="my-2 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
    <!-- Image -->
    <template v-if="isImage">
      <div class="group relative cursor-zoom-in" @click="showFullImage = true">
        <img
          :src="attachment.path"
          :alt="attachment.filename"
          class="block max-h-96 max-w-full rounded-xl object-contain"
        />
        <div
          class="absolute inset-0 flex items-center justify-center rounded-xl bg-black/0 transition-colors group-hover:bg-black/20"
        >
          <ZoomIn
            class="h-8 w-8 scale-75 text-white opacity-0 transition-all group-hover:scale-100 group-hover:opacity-100"
          />
        </div>
      </div>

      <!-- Full-screen image dialog -->
      <Teleport to="body">
        <Transition
          enter-active-class="transition duration-150"
          enter-from-class="opacity-0"
          enter-to-class="opacity-100"
          leave-active-class="transition duration-150"
          leave-from-class="opacity-100"
          leave-to-class="opacity-0"
        >
          <div
            v-if="showFullImage"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4"
            @click="showFullImage = false"
          >
            <img
              :src="attachment.path"
              :alt="attachment.filename"
              class="max-h-full max-w-full rounded-lg object-contain"
              @click.stop
            />
          </div>
        </Transition>
      </Teleport>
    </template>

    <!-- PDF -->
    <template v-else-if="isPdf">
      <div class="flex items-center gap-3 p-3">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-100 dark:bg-rose-900/30">
          <FileText class="h-5 w-5 text-rose-600 dark:text-rose-400" />
        </div>
        <div class="min-w-0 flex-1">
          <div class="truncate text-sm font-medium text-neutral-900 dark:text-neutral-100">
            {{ attachment.filename }}
          </div>
          <div class="flex items-center gap-2 text-xs text-neutral-400 dark:text-neutral-500">
            <span v-if="attachment.page_count">{{ attachment.page_count }} pages</span>
            <span v-if="attachment.size">{{ formatSize(attachment.size) }}</span>
          </div>
        </div>
        <a
          v-if="attachment.path"
          :href="attachment.path"
          target="_blank"
          rel="noopener noreferrer"
          class="flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium text-blue-600 transition-colors hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
        >
          View
          <ExternalLink class="h-3 w-3" />
        </a>
      </div>
    </template>

    <!-- Text / code files -->
    <template v-else-if="isText || isCode">
      <div class="flex items-center gap-2 border-b border-neutral-200 px-3 py-2 dark:border-neutral-700">
        <component :is="icon" class="h-4 w-4 shrink-0 text-neutral-400" />
        <span class="min-w-0 flex-1 truncate text-xs font-medium text-neutral-700 dark:text-neutral-300">
          {{ attachment.filename }}
        </span>
        <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium text-neutral-500 dark:bg-neutral-800 dark:text-neutral-400">
          {{ detectedLanguage }}
        </span>
      </div>
      <div class="overflow-x-auto bg-neutral-950 p-3 text-xs">
        <pre class="text-neutral-200"><code>{{ previewLines.join('\n') }}</code></pre>
      </div>
      <div
        v-if="hasMore"
        class="flex items-center justify-between border-t border-neutral-200 px-3 py-1.5 dark:border-neutral-700"
      >
        <span class="text-xs text-neutral-400">
          {{ showExpanded ? 'Showing all' : `Showing 10 of ${totalLines}` }} lines
        </span>
        <button
          class="flex items-center gap-1 text-xs font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
          @click="showExpanded = !showExpanded"
        >
          {{ showExpanded ? 'Show less' : 'Show more' }}
          <ChevronDown v-if="!showExpanded" class="h-3 w-3" />
          <ChevronUp v-else class="h-3 w-3" />
        </button>
      </div>
    </template>

    <!-- Generic file -->
    <template v-else>
      <div class="flex items-center gap-3 p-3">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-neutral-100 dark:bg-neutral-800">
          <File class="h-5 w-5 text-neutral-400 dark:text-neutral-500" />
        </div>
        <div class="min-w-0 flex-1">
          <div class="truncate text-sm font-medium text-neutral-900 dark:text-neutral-100">
            {{ attachment.filename }}
          </div>
          <div class="text-xs text-neutral-400 dark:text-neutral-500">
            {{ attachment.mime_type }}
            <template v-if="attachment.size"> &middot; {{ formatSize(attachment.size) }}</template>
          </div>
        </div>
        <a
          v-if="attachment.path"
          :href="attachment.path"
          download
          class="flex items-center gap-1 rounded-lg px-3 py-1.5 text-xs font-medium text-blue-600 transition-colors hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
        >
          Download
          <ExternalLink class="h-3 w-3" />
        </a>
      </div>
    </template>
  </div>
</template>
