<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import { Bot, Zap, Code2, Eye, Brain, Info, Loader2, CheckCircle } from 'lucide-vue-next'
  import { useModelsStore } from '@/stores/models'
  import { useSettingsStore } from '@/stores/settings'
  import { useUiStore } from '@/stores/ui'

  const modelsStore = useModelsStore()
  const settingsStore = useSettingsStore()
  const uiStore = useUiStore()

  const saving = ref(false)

  const form = reactive({
    default_model_id: null,
    prefer_local: false,
    auto_routing: false,
    task_model_code: null,
    task_model_vision: null,
    task_model_reasoning: null,
  })

  const activeModels = computed(() => modelsStore.availableModels)

  onMounted(async () => {
    await Promise.all([modelsStore.fetch(), settingsStore.fetch()])
    const s = settingsStore.settings
    if (s) {
      form.default_model_id = s.default_model_id ?? modelsStore.defaultModel?.id ?? null
      form.prefer_local = s.prefer_local ?? false
      form.auto_routing = s.auto_routing ?? false
      form.task_model_code = s.task_model_code ?? null
      form.task_model_vision = s.task_model_vision ?? null
      form.task_model_reasoning = s.task_model_reasoning ?? null
    } else if (modelsStore.defaultModel) {
      form.default_model_id = modelsStore.defaultModel.id
    }
  })

  async function save() {
    saving.value = true
    try {
      await settingsStore.update({
        default_model_id: form.default_model_id,
        prefer_local: form.prefer_local,
        auto_routing: form.auto_routing,
        task_model_code: form.task_model_code,
        task_model_vision: form.task_model_vision,
        task_model_reasoning: form.task_model_reasoning,
      })
      uiStore.addToast({ type: 'success', message: 'Model preferences saved.' })
    } catch (err) {
      const msg = err?.response?.data?.message ?? 'Failed to save preferences.'
      uiStore.addToast({ type: 'error', message: msg })
    } finally {
      saving.value = false
    }
  }

  const TASK_MODELS = [
    {
      key: 'task_model_code',
      icon: Code2,
      label: 'Code',
      description: 'Best model for coding tasks, debugging, and technical questions.',
    },
    {
      key: 'task_model_vision',
      icon: Eye,
      label: 'Vision',
      description: 'Best model for image analysis, OCR, and visual reasoning.',
    },
    {
      key: 'task_model_reasoning',
      icon: Brain,
      label: 'Reasoning',
      description: 'Best model for complex reasoning, math, and multi-step problems.',
    },
  ]
</script>

<template>
  <div class="max-w-2xl mx-auto p-6 space-y-8">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Model Preferences</h1>

    <!-- Default Model -->
    <section
      class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-4"
    >
      <div class="flex items-center gap-2 mb-1">
        <Bot class="w-5 h-5 text-gray-500 dark:text-gray-400" />
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Default Model</h2>
      </div>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        This model is used for all new conversations unless overridden.
      </p>

      <div
        v-if="modelsStore.isLoading"
        class="flex items-center gap-2 text-sm text-gray-400 dark:text-gray-500 py-2"
      >
        <Loader2 class="w-4 h-4 animate-spin" /> Loading models...
      </div>

      <select
        v-else
        v-model="form.default_model_id"
        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
      >
        <option :value="null" disabled>-- Select a model --</option>
        <option v-for="m in activeModels" :key="m.id" :value="m.id">
          {{ m.name }} ({{ m.provider }})
        </option>
      </select>
    </section>

    <!-- Routing Toggles -->
    <section
      class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5"
    >
      <div class="flex items-center gap-2 mb-1">
        <Zap class="w-5 h-5 text-gray-500 dark:text-gray-400" />
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Routing</h2>
      </div>

      <!-- Prefer local -->
      <div class="flex items-start gap-4">
        <button
          type="button"
          role="switch"
          :aria-checked="form.prefer_local"
          class="mt-0.5 relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          :class="form.prefer_local ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'"
          @click="form.prefer_local = !form.prefer_local"
        >
          <span
            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
            :class="form.prefer_local ? 'translate-x-4' : 'translate-x-0.5'"
          />
        </button>
        <div class="flex-1">
          <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Prefer local models</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex items-start gap-1">
            <Info class="w-3 h-3 mt-0.5 flex-shrink-0" />
            Routes requests to your local Ollama instance before trying cloud providers. Falls back
            to cloud if the local model is unavailable.
          </p>
        </div>
      </div>

      <!-- Auto routing -->
      <div class="flex items-start gap-4">
        <button
          type="button"
          role="switch"
          :aria-checked="form.auto_routing"
          class="mt-0.5 relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          :class="form.auto_routing ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'"
          @click="form.auto_routing = !form.auto_routing"
        >
          <span
            class="inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform"
            :class="form.auto_routing ? 'translate-x-4' : 'translate-x-0.5'"
          />
        </button>
        <div class="flex-1">
          <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Auto-routing</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 flex items-start gap-1">
            <Info class="w-3 h-3 mt-0.5 flex-shrink-0" />
            Automatically selects the best model for each message based on the detected task type
            (code, vision, reasoning, etc.). Ignores the default model setting when enabled.
          </p>
        </div>
      </div>
    </section>

    <!-- Task-specific models -->
    <section
      class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5"
    >
      <div class="flex items-center gap-2 mb-1">
        <Brain class="w-5 h-5 text-gray-500 dark:text-gray-400" />
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Task-Specific Models</h2>
      </div>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Override the default model for specific task categories. Leave blank to use the default.
      </p>

      <div v-for="task in TASK_MODELS" :key="task.key" class="space-y-1.5">
        <label
          class="flex items-center gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-300"
        >
          <component :is="task.icon" class="w-4 h-4 text-gray-400 dark:text-gray-500" />
          {{ task.label }}
        </label>
        <p class="text-xs text-gray-500 dark:text-gray-400">{{ task.description }}</p>
        <select
          v-model="form[task.key]"
          class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
        >
          <option :value="null">Use default model</option>
          <option v-for="m in activeModels" :key="m.id" :value="m.id">
            {{ m.name }} ({{ m.provider }})
          </option>
        </select>
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
        {{ saving ? 'Saving...' : 'Save Preferences' }}
      </button>
    </div>
  </div>
</template>
