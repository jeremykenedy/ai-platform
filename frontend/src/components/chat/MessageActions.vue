<script setup>
import { ref } from 'vue'
import { Copy, Pencil, RefreshCw, Trash2, Check } from 'lucide-vue-next'

const props = defineProps({
  message: {
    type: Object,
    required: true,
  },
  isLast: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['edit', 'regenerate', 'delete'])

const copied = ref(false)

async function handleCopy() {
  try {
    await navigator.clipboard.writeText(props.message.content ?? '')
    copied.value = true
    setTimeout(() => {
      copied.value = false
    }, 2000)
  } catch {
    // clipboard not available
  }
}

function handleEdit() {
  emit('edit', props.message)
}

function handleRegenerate() {
  emit('regenerate', props.message)
}

function handleDelete() {
  emit('delete', props.message)
}
</script>

<template>
  <div class="flex items-center gap-0.5 rounded-lg border border-border bg-background shadow-sm dark:border-border dark:bg-background">
    <!-- Copy -->
    <button
      :title="copied ? 'Copied!' : 'Copy'"
      class="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground dark:text-muted-foreground dark:hover:bg-muted dark:hover:text-foreground"
      @click="handleCopy"
    >
      <Check v-if="copied" class="h-3.5 w-3.5 text-green-500 dark:text-green-400" />
      <Copy v-else class="h-3.5 w-3.5" />
    </button>

    <!-- Edit (user messages only) -->
    <button
      v-if="message.role === 'user'"
      title="Edit"
      class="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground dark:text-muted-foreground dark:hover:bg-muted dark:hover:text-foreground"
      @click="handleEdit"
    >
      <Pencil class="h-3.5 w-3.5" />
    </button>

    <!-- Regenerate (last assistant message only) -->
    <button
      v-if="message.role === 'assistant' && isLast"
      title="Regenerate"
      class="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground dark:text-muted-foreground dark:hover:bg-muted dark:hover:text-foreground"
      @click="handleRegenerate"
    >
      <RefreshCw class="h-3.5 w-3.5" />
    </button>

    <!-- Delete -->
    <button
      title="Delete"
      class="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground transition-colors hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-950/30 dark:hover:text-red-400"
      @click="handleDelete"
    >
      <Trash2 class="h-3.5 w-3.5" />
    </button>
  </div>
</template>
