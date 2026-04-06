<script setup>
  import { onMounted, onUnmounted } from 'vue'
  import { X } from 'lucide-vue-next'

  const props = defineProps({
    show: {
      type: Boolean,
      required: true,
    },
  })

  const emit = defineEmits(['update:show'])

  function close() {
    emit('update:show', false)
  }

  function onKeydown(e) {
    if (e.key === 'Escape' && props.show) {
      close()
    }
  }

  onMounted(() => window.addEventListener('keydown', onKeydown))
  onUnmounted(() => window.removeEventListener('keydown', onKeydown))

  const sections = [
    {
      title: 'Navigation',
      shortcuts: [
        { keys: ['⌘', 'K'], description: 'Open command palette' },
        { keys: ['⌘', 'N'], description: 'New conversation' },
        { keys: ['⌘', '⇧', 'S'], description: 'Toggle sidebar' },
      ],
    },
    {
      title: 'Chat',
      shortcuts: [
        { keys: ['↵'], description: 'Send message' },
        { keys: ['⇧', '↵'], description: 'New line' },
        { keys: ['↑'], description: 'Edit last message' },
        { keys: ['Esc'], description: 'Cancel' },
      ],
    },
    {
      title: 'General',
      shortcuts: [
        { keys: ['⌘', '/'], description: 'Show shortcuts' },
        { keys: ['⌘', ','], description: 'Open settings' },
      ],
    },
  ]
</script>

<template>
  <Teleport to="body">
    <Transition name="fade">
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 backdrop-blur-sm dark:bg-black/60"
        @click.self="close"
      >
        <div
          class="w-full max-w-md rounded-2xl border border-gray-200 bg-white shadow-2xl dark:border-gray-700 dark:bg-gray-900"
        >
          <!-- Header -->
          <div
            class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700"
          >
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-50">
              Keyboard Shortcuts
            </h2>
            <button
              class="rounded-lg p-1.5 text-gray-400 transition-colors hover:bg-gray-100 hover:text-gray-600 dark:text-gray-500 dark:hover:bg-gray-800 dark:hover:text-gray-300"
              aria-label="Close"
              @click="close"
            >
              <X class="h-4 w-4" />
            </button>
          </div>

          <!-- Shortcut sections -->
          <div class="flex flex-col gap-6 p-6">
            <div v-for="section in sections" :key="section.title" class="flex flex-col gap-3">
              <h3
                class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500"
              >
                {{ section.title }}
              </h3>
              <div class="flex flex-col gap-2">
                <div
                  v-for="shortcut in section.shortcuts"
                  :key="shortcut.description"
                  class="flex items-center justify-between"
                >
                  <span class="text-sm text-gray-600 dark:text-gray-300">
                    {{ shortcut.description }}
                  </span>
                  <div class="flex items-center gap-1">
                    <kbd
                      v-for="key in shortcut.keys"
                      :key="key"
                      class="inline-flex min-w-[1.75rem] items-center justify-center rounded border border-gray-300 bg-gray-100 px-1.5 py-0.5 font-mono text-xs font-medium text-gray-700 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    >
                      {{ key }}
                    </kbd>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
  .fade-enter-active,
  .fade-leave-active {
    transition: opacity 0.15s ease;
  }

  .fade-enter-from,
  .fade-leave-to {
    opacity: 0;
  }
</style>
