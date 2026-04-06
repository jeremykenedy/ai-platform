<script setup>
  import { ref, watch, nextTick, defineAsyncComponent } from 'vue'
  import MarkdownIt from 'markdown-it'
  import { useWebWorker } from '@/composables/useWebWorker'

  const CodeBlock = defineAsyncComponent(() => import('./CodeBlock.vue'))

  const props = defineProps({
    content: {
      type: String,
      default: '',
    },
    messageId: {
      type: [String, Number],
      default: null,
    },
  })

  const SHORT_THRESHOLD = 500

  const html = ref('')
  const isProcessing = ref(false)
  const containerRef = ref(null)

  // Inline MarkdownIt for short content
  const md = new MarkdownIt({
    html: false,
    linkify: true,
    typographer: true,
    breaks: true,
  })

  // Web worker for long content
  const { send } = useWebWorker(
    () => new Worker(new URL('@/workers/markdown.worker.js', import.meta.url), { type: 'module' })
  )

  async function render(content) {
    if (!content) {
      html.value = ''
      return
    }

    if (content.length < SHORT_THRESHOLD) {
      html.value = md.render(content)
      await nextTick()
      enhanceCodeBlocks()
      return
    }

    isProcessing.value = true
    try {
      const result = await send({ content, id: props.messageId ?? 0 })
      html.value = result.html
      await nextTick()
      enhanceCodeBlocks()
    } finally {
      isProcessing.value = false
    }
  }

  // Find all pre>code blocks in rendered HTML and store their data for CodeBlock components
  const codeBlocks = ref([])

  function enhanceCodeBlocks() {
    if (!containerRef.value) return
    const preElements = containerRef.value.querySelectorAll('pre > code')
    const blocks = []
    preElements.forEach((codeEl, index) => {
      const lang =
        Array.from(codeEl.classList)
          .find((c) => c.startsWith('language-'))
          ?.replace('language-', '') ?? ''
      blocks.push({
        id: index,
        code: codeEl.textContent ?? '',
        language: lang,
      })
      // Replace the pre element with a placeholder div
      const pre = codeEl.parentElement
      const placeholder = document.createElement('div')
      placeholder.dataset.codeblockId = index
      pre.replaceWith(placeholder)
    })
    codeBlocks.value = blocks
  }

  watch(() => props.content, render, { immediate: true })
</script>

<template>
  <div>
    <!-- Skeleton while processing long content -->
    <div v-if="isProcessing && !html" class="space-y-2 py-1">
      <div class="h-4 w-full animate-pulse rounded bg-neutral-200 dark:bg-neutral-700" />
      <div class="h-4 w-4/5 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700" />
      <div class="h-4 w-3/5 animate-pulse rounded bg-neutral-200 dark:bg-neutral-700" />
    </div>

    <!-- Rendered markdown -->
    <div
      v-else
      ref="containerRef"
      class="prose prose-neutral max-w-none dark:prose-invert prose-sm prose-p:leading-relaxed prose-pre:p-0 prose-pre:bg-transparent prose-code:before:content-none prose-code:after:content-none"
      v-html="html"
    />

    <!-- Teleport CodeBlock components into the placeholders -->
    <template v-if="codeBlocks.length">
      <Teleport
        v-for="block in codeBlocks"
        :key="block.id"
        :to="`[data-codeblock-id='${block.id}']`"
        :disabled="!containerRef"
      >
        <CodeBlock :code="block.code" :language="block.language" />
      </Teleport>
    </template>
  </div>
</template>
