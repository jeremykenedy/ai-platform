<script setup>
  import { ref, computed } from 'vue'
  import { cn } from '@/lib/utils'
  import { useRoute, useRouter } from 'vue-router'
  import { formatDistanceToNow } from 'date-fns'
  import { Trash2, MessageSquare, Pencil, FolderInput } from 'lucide-vue-next'

  const props = defineProps({
    conversation: {
      type: Object,
      required: true,
    },
  })

  const emit = defineEmits(['select', 'delete'])

  const route = useRoute()
  const router = useRouter()

  const showContextMenu = ref(false)
  const contextMenuX = ref(0)
  const contextMenuY = ref(0)
  const longPressTimer = ref(null)

  const isActive = computed(() => route.params.id === props.conversation.id)

  const relativeTime = computed(() => {
    try {
      return formatDistanceToNow(new Date(props.conversation.updated_at), { addSuffix: true })
    } catch {
      return ''
    }
  })

  function navigate() {
    router.push(`/c/${props.conversation.id}`)
    emit('select', props.conversation)
  }

  function onDelete(e) {
    if (e?.stopPropagation) e.stopPropagation()
    emit('delete', props.conversation)
  }

  function openContextMenu(e) {
    e.preventDefault()
    showContextMenu.value = true
    contextMenuX.value = e.clientX
    contextMenuY.value = e.clientY
  }

  function closeContextMenu() {
    showContextMenu.value = false
  }

  function onLongPressStart(e) {
    longPressTimer.value = setTimeout(() => {
      const touch = e.touches[0]
      contextMenuX.value = touch.clientX
      contextMenuY.value = touch.clientY
      showContextMenu.value = true
    }, 500)
  }

  function onLongPressEnd() {
    clearTimeout(longPressTimer.value)
  }

  function onRename() {
    closeContextMenu()
    // Rename handled by parent if needed
  }

  function onMoveToProject() {
    closeContextMenu()
    // Move to project handled by parent if needed
  }
</script>

<template>
  <div class="relative group">
    <button
      :class="
        cn(
          'w-full flex items-start gap-2 rounded-lg px-3 py-2 text-left transition-colors',
          isActive
            ? 'bg-gray-100 dark:bg-gray-800'
            : 'hover:bg-gray-100/70 dark:hover:bg-gray-800/70'
        )
      "
      @click="navigate"
      @contextmenu="openContextMenu"
      @touchstart.passive="onLongPressStart"
      @touchend="onLongPressEnd"
      @touchmove="onLongPressEnd"
    >
      <MessageSquare
        :class="
          cn(
            'mt-0.5 h-4 w-4 shrink-0',
            isActive ? 'text-primary' : 'text-gray-400 dark:text-gray-500'
          )
        "
      />
      <div class="flex min-w-0 flex-1 flex-col gap-0.5">
        <span
          :class="
            cn(
              'truncate text-sm font-medium leading-tight',
              isActive ? 'text-gray-900 dark:text-gray-50' : 'text-gray-700 dark:text-gray-300'
            )
          "
        >
          {{ conversation.title || 'New conversation' }}
        </span>
        <span class="text-xs text-gray-400 dark:text-gray-500">
          {{ relativeTime }}
        </span>
      </div>

      <button
        :class="
          cn(
            'ml-1 shrink-0 rounded p-0.5 opacity-0 transition-opacity',
            'text-gray-400 hover:text-red-500 dark:text-gray-500 dark:hover:text-red-400',
            'group-hover:opacity-100',
            isActive && 'opacity-100'
          )
        "
        aria-label="Delete conversation"
        @click.stop="onDelete"
      >
        <Trash2 class="h-3.5 w-3.5" />
      </button>
    </button>

    <Teleport to="body">
      <div
        v-if="showContextMenu"
        class="fixed inset-0 z-40"
        @click="closeContextMenu"
        @contextmenu.prevent="closeContextMenu"
      />
      <div
        v-if="showContextMenu"
        :style="{ top: contextMenuY + 'px', left: contextMenuX + 'px' }"
        :class="
          cn(
            'fixed z-50 min-w-36 rounded-lg border py-1 shadow-lg',
            'border-gray-200 dark:border-gray-700',
            'bg-white dark:bg-gray-800'
          )
        "
      >
        <button
          :class="
            cn(
              'flex w-full items-center gap-2 px-3 py-2 text-sm transition-colors',
              'text-gray-700 dark:text-gray-300',
              'hover:bg-gray-100 dark:hover:bg-gray-700'
            )
          "
          @click="onRename"
        >
          <Pencil class="h-3.5 w-3.5" />
          Rename
        </button>
        <button
          :class="
            cn(
              'flex w-full items-center gap-2 px-3 py-2 text-sm transition-colors',
              'text-gray-700 dark:text-gray-300',
              'hover:bg-gray-100 dark:hover:bg-gray-700'
            )
          "
          @click="onMoveToProject"
        >
          <FolderInput class="h-3.5 w-3.5" />
          Move to Project
        </button>
        <div class="my-1 border-t border-gray-200 dark:border-gray-700" />
        <button
          :class="
            cn(
              'flex w-full items-center gap-2 px-3 py-2 text-sm transition-colors',
              'text-red-600 dark:text-red-400',
              'hover:bg-red-50 dark:hover:bg-red-900/20'
            )
          "
          @click="
            () => {
              onDelete()
              closeContextMenu()
            }
          "
        >
          <Trash2 class="h-3.5 w-3.5" />
          Delete
        </button>
      </div>
    </Teleport>
  </div>
</template>
