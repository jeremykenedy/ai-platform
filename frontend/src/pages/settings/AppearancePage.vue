<script setup>
  import { ref, reactive, onMounted, watch } from 'vue'
  import {
    Sun,
    Moon,
    Monitor,
    Type,
    Rows3,
    Hash,
    CornerDownLeft,
    CheckCircle,
    Loader2,
  } from 'lucide-vue-next'
  import { useSettingsStore } from '@/stores/settings'
  import { useUiStore } from '@/stores/ui'

  const settingsStore = useSettingsStore()
  const uiStore = useUiStore()

  const saving = ref(false)

  const form = reactive({
    theme: 'system',
    font_size: 14,
    compact_mode: false,
    show_token_counts: false,
    send_on_enter: true,
  })

  onMounted(async () => {
    await settingsStore.fetch()
    const s = settingsStore.settings
    if (s) {
      form.theme = s.theme ?? uiStore.theme ?? 'system'
      form.font_size = s.font_size ?? 14
      form.compact_mode = s.compact_mode ?? false
      form.show_token_counts = s.show_token_counts ?? false
      form.send_on_enter = s.send_on_enter ?? true
    } else {
      form.theme = uiStore.theme
    }
  })

  watch(
    () => form.theme,
    (val) => {
      uiStore.setTheme(val)
    }
  )

  watch(
    () => form.font_size,
    (val) => {
      document.documentElement.style.setProperty('--app-font-size', `${val}px`)
    }
  )

  const THEMES = [
    {
      value: 'light',
      label: 'Light',
      icon: Sun,
      preview: {
        bg: 'bg-white',
        sidebar: 'bg-gray-100',
        header: 'bg-white border-b border-gray-200',
        message: 'bg-gray-100 text-gray-800',
        accent: 'bg-indigo-600',
      },
    },
    {
      value: 'dark',
      label: 'Dark',
      icon: Moon,
      preview: {
        bg: 'bg-gray-900',
        sidebar: 'bg-gray-800',
        header: 'bg-gray-900 border-b border-gray-700',
        message: 'bg-gray-700 text-gray-100',
        accent: 'bg-indigo-500',
      },
    },
    {
      value: 'system',
      label: 'System',
      icon: Monitor,
      preview: {
        bg: 'bg-gradient-to-br from-white to-gray-900',
        sidebar: 'bg-gradient-to-b from-gray-100 to-gray-800',
        header: 'bg-gradient-to-r from-white to-gray-900',
        message: 'bg-gradient-to-r from-gray-100 to-gray-700 text-gray-600',
        accent: 'bg-indigo-500',
      },
    },
  ]

  async function save() {
    saving.value = true
    try {
      await settingsStore.update({
        theme: form.theme,
        font_size: form.font_size,
        compact_mode: form.compact_mode,
        show_token_counts: form.show_token_counts,
        send_on_enter: form.send_on_enter,
      })
      uiStore.setTheme(form.theme)
      uiStore.addToast({ type: 'success', message: 'Appearance settings saved.' })
    } catch (err) {
      const msg = err?.response?.data?.message ?? 'Failed to save settings.'
      uiStore.addToast({ type: 'error', message: msg })
    } finally {
      saving.value = false
    }
  }
</script>

<template>
  <div class="max-w-2xl mx-auto p-6 space-y-8">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Appearance</h1>

    <!-- Theme -->
    <section
      class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4"
    >
      <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Theme</h2>
      <div class="grid grid-cols-3 gap-4">
        <button
          v-for="t in THEMES"
          :key="t.value"
          type="button"
          class="flex flex-col items-center gap-2.5 focus:outline-none"
          @click="form.theme = t.value"
        >
          <!-- Mini UI preview -->
          <div
            class="w-full aspect-video rounded-lg overflow-hidden border-2 transition"
            :class="
              form.theme === t.value
                ? 'border-indigo-500 ring-2 ring-indigo-400/40 dark:border-indigo-400'
                : 'border-gray-200 dark:border-gray-600 hover:border-gray-300 dark:hover:border-gray-500'
            "
          >
            <div class="w-full h-full flex" :class="t.preview.bg">
              <!-- Sidebar preview -->
              <div class="w-8 h-full flex flex-col gap-1 p-1" :class="t.preview.sidebar">
                <div class="w-full h-1 rounded" :class="t.preview.accent" />
                <div class="w-3/4 h-1 rounded bg-current opacity-20" />
                <div class="w-2/3 h-1 rounded bg-current opacity-20" />
              </div>
              <!-- Main area -->
              <div class="flex-1 flex flex-col">
                <div class="h-4 flex items-center px-1.5 gap-1" :class="t.preview.header">
                  <div class="w-3 h-1 rounded" :class="t.preview.accent" />
                </div>
                <div class="flex-1 flex flex-col justify-end gap-1 p-1.5">
                  <div class="self-end w-10 h-1.5 rounded" :class="t.preview.accent" />
                  <div
                    class="w-12 h-3 rounded text-[4px] flex items-center px-1"
                    :class="t.preview.message"
                  >
                    <div class="w-full h-0.5 rounded bg-current opacity-50" />
                  </div>
                </div>
              </div>
            </div>
          </div>
          <!-- Label -->
          <div class="flex items-center gap-1.5">
            <div
              class="w-3.5 h-3.5 rounded-full border-2 flex items-center justify-center transition"
              :class="
                form.theme === t.value
                  ? 'border-indigo-600 dark:border-indigo-400'
                  : 'border-gray-300 dark:border-gray-600'
              "
            >
              <div
                v-if="form.theme === t.value"
                class="w-1.5 h-1.5 rounded-full bg-indigo-600 dark:bg-indigo-400"
              />
            </div>
            <span
              class="text-sm font-medium text-gray-700 dark:text-gray-300 flex items-center gap-1"
            >
              <component :is="t.icon" class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" />
              {{ t.label }}
            </span>
          </div>
        </button>
      </div>
    </section>

    <!-- Typography -->
    <section
      class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5"
    >
      <div class="flex items-center gap-2">
        <Type class="w-5 h-5 text-gray-500 dark:text-gray-400" />
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Typography</h2>
      </div>

      <!-- Font size -->
      <div>
        <div class="flex items-center justify-between mb-1">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Font size</label>
          <span class="text-xs font-mono text-gray-500 dark:text-gray-400"
            >{{ form.font_size }}px</span
          >
        </div>
        <input
          v-model.number="form.font_size"
          type="range"
          min="10"
          max="24"
          step="1"
          class="w-full accent-indigo-600"
        />
        <div class="flex justify-between text-xs text-gray-400 dark:text-gray-500 mt-0.5">
          <span>10px</span>
          <span>24px</span>
        </div>
        <!-- Live preview -->
        <div
          class="mt-3 p-3 rounded-lg bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700 text-gray-700 dark:text-gray-300 leading-relaxed transition-all"
          :style="{ fontSize: `${form.font_size}px` }"
        >
          This is a live preview of your selected font size. The quick brown fox jumps over the lazy
          dog.
        </div>
      </div>
    </section>

    <!-- Display Options -->
    <section
      class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5"
    >
      <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Display Options</h2>

      <!-- Compact mode -->
      <div class="flex items-start gap-4">
        <button
          type="button"
          role="switch"
          :aria-checked="form.compact_mode"
          class="mt-0.5 relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
          :class="form.compact_mode ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'"
          @click="form.compact_mode = !form.compact_mode"
        >
          <span
            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
            :class="form.compact_mode ? 'translate-x-4' : 'translate-x-0.5'"
          />
        </button>
        <div class="flex items-start gap-2">
          <Rows3 class="w-4 h-4 mt-0.5 text-gray-400 dark:text-gray-500 flex-shrink-0" />
          <div>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Compact mode</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              Reduces padding and spacing between messages for a denser layout.
            </p>
          </div>
        </div>
      </div>

      <!-- Show token counts -->
      <div class="flex items-start gap-4">
        <button
          type="button"
          role="switch"
          :aria-checked="form.show_token_counts"
          class="mt-0.5 relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
          :class="form.show_token_counts ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'"
          @click="form.show_token_counts = !form.show_token_counts"
        >
          <span
            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
            :class="form.show_token_counts ? 'translate-x-4' : 'translate-x-0.5'"
          />
        </button>
        <div class="flex items-start gap-2">
          <Hash class="w-4 h-4 mt-0.5 text-gray-400 dark:text-gray-500 flex-shrink-0" />
          <div>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Show token counts</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              Display token usage below each message. Useful for monitoring costs.
            </p>
          </div>
        </div>
      </div>

      <!-- Send on Enter -->
      <div class="flex items-start gap-4">
        <button
          type="button"
          role="switch"
          :aria-checked="form.send_on_enter"
          class="mt-0.5 relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
          :class="form.send_on_enter ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'"
          @click="form.send_on_enter = !form.send_on_enter"
        >
          <span
            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
            :class="form.send_on_enter ? 'translate-x-4' : 'translate-x-0.5'"
          />
        </button>
        <div class="flex items-start gap-2">
          <CornerDownLeft class="w-4 h-4 mt-0.5 text-gray-400 dark:text-gray-500 flex-shrink-0" />
          <div>
            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Send on Enter</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
              {{
                form.send_on_enter
                  ? 'Press Enter to send. Use Shift+Enter for a new line.'
                  : 'Press Shift+Enter to send. Enter adds a new line.'
              }}
            </p>
          </div>
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
        {{ saving ? 'Saving...' : 'Save Appearance' }}
      </button>
    </div>
  </div>
</template>
