<script setup>
  import { ref, onMounted, computed } from 'vue'
  import { Copy, Check } from 'lucide-vue-next'

  const props = defineProps({
    code: {
      type: String,
      default: '',
    },
    language: {
      type: String,
      default: '',
    },
    showLineNumbers: {
      type: Boolean,
      default: false,
    },
  })

  const highlightedHtml = ref('')
  const isLoading = ref(true)
  const copied = ref(false)
  let copyTimer = null

  const displayLanguage = computed(() => props.language || 'text')

  const lines = computed(() => {
    if (!props.showLineNumbers) return []
    return props.code.split('\n')
  })

  onMounted(async () => {
    await highlight()
  })

  async function highlight() {
    isLoading.value = true
    try {
      const { createHighlighter } = await import('shiki')
      const highlighter = await createHighlighter({
        themes: ['github-dark'],
        langs: [props.language || 'text'].filter(Boolean),
      })
      highlightedHtml.value = highlighter.codeToHtml(props.code, {
        lang: props.language || 'text',
        theme: 'github-dark',
      })
    } catch {
      // Fallback: plain text
      highlightedHtml.value = ''
    } finally {
      isLoading.value = false
    }
  }

  async function copyCode() {
    try {
      await navigator.clipboard.writeText(props.code)
      copied.value = true
      if (copyTimer) clearTimeout(copyTimer)
      copyTimer = setTimeout(() => {
        copied.value = false
      }, 2000)
    } catch {
      // Clipboard unavailable
    }
  }
</script>

<template>
  <div class="group relative my-3 overflow-hidden rounded-xl bg-neutral-950">
    <!-- Header bar -->
    <div class="flex items-center justify-between border-b border-white/10 px-4 py-2">
      <span class="text-[11px] font-medium uppercase tracking-wider text-neutral-400">
        {{ displayLanguage }}
      </span>
      <button
        class="flex items-center gap-1.5 rounded px-2 py-1 text-xs text-neutral-400 transition-colors hover:bg-white/10 hover:text-neutral-200"
        @click="copyCode"
      >
        <Transition
          mode="out-in"
          enter-active-class="transition duration-100"
          enter-from-class="opacity-0 scale-75"
          enter-to-class="opacity-100 scale-100"
          leave-active-class="transition duration-100"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-75"
        >
          <Check v-if="copied" key="check" class="h-3.5 w-3.5 text-emerald-400" />
          <Copy v-else key="copy" class="h-3.5 w-3.5" />
        </Transition>
        {{ copied ? 'Copied!' : 'Copy' }}
      </button>
    </div>

    <!-- Code area -->
    <div class="overflow-x-auto">
      <!-- Loading skeleton -->
      <div v-if="isLoading" class="px-4 py-3">
        <div class="space-y-1.5">
          <div
            v-for="i in Math.min(props.code.split('\n').length, 6)"
            :key="i"
            class="h-4 animate-pulse rounded bg-white/10"
            :style="{ width: `${70 + Math.random() * 30}%` }"
          />
        </div>
      </div>

      <!-- Shiki highlighted output -->
      <div
        v-else-if="highlightedHtml"
        class="text-sm [&>pre]:overflow-x-auto [&>pre]:p-4 [&>pre]:leading-relaxed"
        v-html="highlightedHtml"
      />

      <!-- Plain fallback -->
      <pre
        v-else
        class="overflow-x-auto p-4 text-sm leading-relaxed text-neutral-200"
      ><code>{{ code }}</code></pre>
    </div>

    <!-- Line numbers gutter (overlay approach for simple rendering) -->
    <div
      v-if="showLineNumbers && !isLoading && !highlightedHtml"
      class="pointer-events-none absolute left-0 top-[41px] select-none px-3 py-4 text-right text-sm leading-relaxed text-neutral-600"
    >
      <div v-for="(_, i) in lines" :key="i">{{ i + 1 }}</div>
    </div>
  </div>
</template>
