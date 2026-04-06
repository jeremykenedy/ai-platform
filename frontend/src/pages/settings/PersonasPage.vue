<script setup>
  import { ref, reactive, onMounted } from 'vue'
  import {
    Plus,
    Edit2,
    Trash2,
    ChevronUp,
    Loader2,
    Star,
    Bot,
    X,
    Check,
    AlertTriangle,
  } from 'lucide-vue-next'
  import { usePersonasStore } from '@/stores/personas'
  import { useModelsStore } from '@/stores/models'
  import { useUiStore } from '@/stores/ui'

  const personasStore = usePersonasStore()
  const modelsStore = useModelsStore()
  const uiStore = useUiStore()

  onMounted(async () => {
    await Promise.all([personasStore.fetch(), modelsStore.fetch()])
  })

  const showCreateForm = ref(false)
  const expandedId = ref(null)
  const deleteConfirmId = ref(null)
  const savingId = ref(null)
  const deletingId = ref(null)
  const creatingNew = ref(false)

  const blankForm = () => ({
    name: '',
    description: '',
    system_prompt: '',
    model_id: null,
    temperature: 0.7,
    top_p: 0.9,
    top_k: 40,
    repeat_penalty: 1.1,
    is_default: false,
  })

  const createForm = reactive(blankForm())
  const createErrors = reactive({})

  const editForms = ref({})

  function openCreate() {
    Object.assign(createForm, blankForm())
    Object.keys(createErrors).forEach((k) => delete createErrors[k])
    showCreateForm.value = true
  }

  function closeCreate() {
    showCreateForm.value = false
  }

  function toggleExpand(persona) {
    if (expandedId.value === persona.id) {
      expandedId.value = null
      return
    }
    expandedId.value = persona.id
    editForms.value[persona.id] = {
      name: persona.name,
      description: persona.description ?? '',
      system_prompt: persona.system_prompt ?? '',
      model_id: persona.model_id ?? null,
      temperature: persona.temperature ?? 0.7,
      top_p: persona.top_p ?? 0.9,
      top_k: persona.top_k ?? 40,
      repeat_penalty: persona.repeat_penalty ?? 1.1,
      is_default: persona.is_default ?? false,
      errors: {},
    }
  }

  function validateForm(form, errors) {
    Object.keys(errors).forEach((k) => delete errors[k])
    if (!form.name.trim()) errors.name = 'Name is required.'
    if (!form.system_prompt.trim()) errors.system_prompt = 'System prompt is required.'
    return Object.keys(errors).length === 0
  }

  async function saveCreate() {
    if (!validateForm(createForm, createErrors)) return
    creatingNew.value = true
    try {
      await personasStore.create({ ...createForm })
      showCreateForm.value = false
      uiStore.addToast({ type: 'success', message: 'Persona created.' })
    } catch (err) {
      const msg = err?.response?.data?.message ?? 'Failed to create persona.'
      uiStore.addToast({ type: 'error', message: msg })
    } finally {
      creatingNew.value = false
    }
  }

  async function saveEdit(persona) {
    const form = editForms.value[persona.id]
    if (!form) return
    if (!validateForm(form, form.errors)) return
    savingId.value = persona.id
    try {
      await personasStore.update(persona.id, {
        name: form.name,
        description: form.description,
        system_prompt: form.system_prompt,
        model_id: form.model_id,
        temperature: form.temperature,
        top_p: form.top_p,
        top_k: form.top_k,
        repeat_penalty: form.repeat_penalty,
        is_default: form.is_default,
      })
      expandedId.value = null
      uiStore.addToast({ type: 'success', message: 'Persona updated.' })
    } catch (err) {
      const msg = err?.response?.data?.message ?? 'Failed to update persona.'
      uiStore.addToast({ type: 'error', message: msg })
    } finally {
      savingId.value = null
    }
  }

  async function confirmDelete(persona) {
    deletingId.value = persona.id
    try {
      await personasStore.destroy(persona.id)
      deleteConfirmId.value = null
      if (expandedId.value === persona.id) expandedId.value = null
      uiStore.addToast({ type: 'success', message: 'Persona deleted.' })
    } catch (err) {
      const msg = err?.response?.data?.message ?? 'Failed to delete persona.'
      uiStore.addToast({ type: 'error', message: msg })
    } finally {
      deletingId.value = null
    }
  }

  function modelName(modelId) {
    const m = modelsStore.models.find((m) => m.id === modelId)
    return m ? m.name : 'Default model'
  }
</script>

<template>
  <div class="max-w-3xl mx-auto p-6 space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Personas</h1>
      <button
        type="button"
        class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium transition"
        @click="openCreate"
      >
        <Plus class="w-4 h-4" /> Create New Persona
      </button>
    </div>

    <!-- Create Form -->
    <div
      v-if="showCreateForm"
      class="bg-white dark:bg-gray-800 rounded-xl border border-indigo-300 dark:border-indigo-600 p-6 space-y-4 shadow-sm"
    >
      <div class="flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">New Persona</h2>
        <button
          type="button"
          class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200"
          @click="closeCreate"
        >
          <X class="w-5 h-5" />
        </button>
      </div>

      <PersonaFormFields :form="createForm" :errors="createErrors" :models="modelsStore.models" />

      <div class="flex justify-end gap-2 pt-2">
        <button
          type="button"
          class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
          @click="closeCreate"
        >
          Cancel
        </button>
        <button
          type="button"
          :disabled="creatingNew"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-medium transition"
          @click="saveCreate"
        >
          <Loader2 v-if="creatingNew" class="w-4 h-4 animate-spin" />
          <Check v-else class="w-4 h-4" />
          {{ creatingNew ? 'Creating...' : 'Create Persona' }}
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-if="!personasStore.isLoading && personasStore.personas.length === 0 && !showCreateForm"
      class="flex flex-col items-center justify-center py-20 text-center"
    >
      <Bot class="w-14 h-14 text-gray-300 dark:text-gray-600 mb-4" />
      <p class="text-gray-500 dark:text-gray-400 text-sm">
        No personas yet. Create one to customize AI behavior.
      </p>
    </div>

    <!-- Loading -->
    <div
      v-if="personasStore.isLoading"
      class="flex items-center gap-2 text-sm text-gray-400 dark:text-gray-500 py-8 justify-center"
    >
      <Loader2 class="w-5 h-5 animate-spin" /> Loading personas...
    </div>

    <!-- Persona Cards -->
    <div
      v-for="persona in personasStore.sortedPersonas"
      :key="persona.id"
      class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden"
    >
      <!-- Card header -->
      <div class="flex items-start gap-3 p-4">
        <div
          class="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center flex-shrink-0"
        >
          <Bot class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
              {{ persona.name }}
            </h3>
            <span
              v-if="persona.is_default"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-300"
            >
              <Star class="w-3 h-3" /> Default
            </span>
          </div>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
            {{ (persona.system_prompt ?? '').slice(0, 100)
            }}{{ (persona.system_prompt ?? '').length > 100 ? '...' : '' }}
          </p>
          <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
            {{ modelName(persona.model_id) }} &middot; temp {{ persona.temperature ?? 0.7 }}
          </p>
        </div>
        <div class="flex items-center gap-1 flex-shrink-0">
          <button
            type="button"
            class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
            :title="expandedId === persona.id ? 'Collapse' : 'Edit'"
            @click="toggleExpand(persona)"
          >
            <Edit2 v-if="expandedId !== persona.id" class="w-4 h-4" />
            <ChevronUp v-else class="w-4 h-4" />
          </button>
          <button
            type="button"
            class="p-1.5 rounded-lg text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition"
            title="Delete"
            @click="deleteConfirmId = persona.id"
          >
            <Trash2 class="w-4 h-4" />
          </button>
        </div>
      </div>

      <!-- Delete confirmation -->
      <div
        v-if="deleteConfirmId === persona.id"
        class="mx-4 mb-4 flex items-center gap-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3 text-sm"
      >
        <AlertTriangle class="w-4 h-4 text-red-500 flex-shrink-0" />
        <span class="flex-1 text-red-700 dark:text-red-300"
          >Delete "{{ persona.name }}"? This cannot be undone.</span
        >
        <button
          type="button"
          class="px-3 py-1 rounded-md text-xs font-medium bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition"
          @click="deleteConfirmId = null"
        >
          Cancel
        </button>
        <button
          type="button"
          :disabled="deletingId === persona.id"
          class="inline-flex items-center gap-1 px-3 py-1 rounded-md text-xs font-medium bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white transition"
          @click="confirmDelete(persona)"
        >
          <Loader2 v-if="deletingId === persona.id" class="w-3 h-3 animate-spin" />
          Delete
        </button>
      </div>

      <!-- Edit Form -->
      <div
        v-if="expandedId === persona.id && editForms[persona.id]"
        class="border-t border-gray-100 dark:border-gray-700 p-4 space-y-4"
      >
        <PersonaFormFields
          :form="editForms[persona.id]"
          :errors="editForms[persona.id].errors"
          :models="modelsStore.models"
        />
        <div class="flex justify-end gap-2 pt-1">
          <button
            type="button"
            class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
            @click="expandedId = null"
          >
            Cancel
          </button>
          <button
            type="button"
            :disabled="savingId === persona.id"
            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-medium transition"
            @click="saveEdit(persona)"
          >
            <Loader2 v-if="savingId === persona.id" class="w-4 h-4 animate-spin" />
            <Check v-else class="w-4 h-4" />
            {{ savingId === persona.id ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<!-- Inline sub-component for persona form fields -->
<script>
  import { defineComponent, h } from 'vue'
  import { AlertCircle, Star as StarIcon } from 'lucide-vue-next'

  const PersonaFormFields = defineComponent({
    name: 'PersonaFormFields',
    props: {
      form: { type: Object, required: true },
      errors: { type: Object, default: () => ({}) },
      models: { type: Array, default: () => [] },
    },
    setup(props) {
      return () => {
        const f = props.form
        const e = props.errors

        const inputCls = (field) =>
          `w-full rounded-lg border px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition ${
            e[field] ? 'border-red-400 dark:border-red-500' : 'border-gray-300 dark:border-gray-600'
          }`

        const errorNode = (field) =>
          e[field]
            ? h('p', { class: 'mt-1 text-xs text-red-500 flex items-center gap-1' }, [
                h(AlertCircle, { class: 'w-3 h-3' }),
                e[field],
              ])
            : null

        const label = (text) =>
          h(
            'label',
            { class: 'block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1' },
            text
          )

        return h('div', { class: 'space-y-4' }, [
          // Name
          h('div', [
            label('Name'),
            h('input', {
              value: f.name,
              onInput: (ev) => (f.name = ev.target.value),
              type: 'text',
              class: inputCls('name'),
              placeholder: 'e.g. Code Review Assistant',
            }),
            errorNode('name'),
          ]),

          // Description
          h('div', [
            label('Description (optional)'),
            h('input', {
              value: f.description,
              onInput: (ev) => (f.description = ev.target.value),
              type: 'text',
              class: inputCls('description'),
              placeholder: 'Short description of this persona',
            }),
          ]),

          // System Prompt
          h('div', [
            label('System Prompt'),
            h('textarea', {
              value: f.system_prompt,
              onInput: (ev) => (f.system_prompt = ev.target.value),
              class: inputCls('system_prompt') + ' min-h-[120px] resize-y',
              placeholder: 'You are a helpful assistant...',
              rows: 5,
            }),
            errorNode('system_prompt'),
          ]),

          // Model
          h('div', [
            label('Model'),
            h(
              'select',
              {
                value: f.model_id,
                onChange: (ev) => (f.model_id = ev.target.value || null),
                class: inputCls('model_id'),
              },
              [
                h('option', { value: '' }, 'Use default model'),
                ...props.models.map((m) =>
                  h('option', { key: m.id, value: m.id }, `${m.name} (${m.provider})`)
                ),
              ]
            ),
          ]),

          // Temperature
          h('div', [
            h('div', { class: 'flex items-center justify-between mb-1' }, [
              label('Temperature'),
              h(
                'span',
                { class: 'text-xs font-mono text-gray-500 dark:text-gray-400' },
                f.temperature.toFixed(2)
              ),
            ]),
            h('input', {
              type: 'range',
              min: 0,
              max: 2,
              step: 0.05,
              value: f.temperature,
              onInput: (ev) => (f.temperature = parseFloat(ev.target.value)),
              class: 'w-full accent-indigo-600',
            }),
            h(
              'div',
              { class: 'flex justify-between text-xs text-gray-400 dark:text-gray-500 mt-0.5' },
              [h('span', '0 (precise)'), h('span', '2 (creative)')]
            ),
          ]),

          // Top P
          h('div', [
            h('div', { class: 'flex items-center justify-between mb-1' }, [
              label('Top P'),
              h(
                'span',
                { class: 'text-xs font-mono text-gray-500 dark:text-gray-400' },
                f.top_p.toFixed(2)
              ),
            ]),
            h('input', {
              type: 'range',
              min: 0,
              max: 1,
              step: 0.05,
              value: f.top_p,
              onInput: (ev) => (f.top_p = parseFloat(ev.target.value)),
              class: 'w-full accent-indigo-600',
            }),
          ]),

          // Top K + Repeat Penalty
          h('div', { class: 'grid grid-cols-2 gap-4' }, [
            h('div', [
              label('Top K'),
              h('input', {
                type: 'number',
                value: f.top_k,
                onInput: (ev) => (f.top_k = parseInt(ev.target.value) || 0),
                min: 0,
                max: 200,
                class: inputCls('top_k'),
              }),
            ]),
            h('div', [
              h('div', { class: 'flex items-center justify-between mb-1' }, [
                label('Repeat Penalty'),
                h(
                  'span',
                  { class: 'text-xs font-mono text-gray-500 dark:text-gray-400' },
                  f.repeat_penalty.toFixed(2)
                ),
              ]),
              h('input', {
                type: 'range',
                min: 0.5,
                max: 2,
                step: 0.05,
                value: f.repeat_penalty,
                onInput: (ev) => (f.repeat_penalty = parseFloat(ev.target.value)),
                class: 'w-full accent-indigo-600',
              }),
            ]),
          ]),

          // Set as default
          h('div', { class: 'flex items-center gap-3' }, [
            h(
              'button',
              {
                type: 'button',
                role: 'switch',
                'aria-checked': f.is_default,
                class: `relative inline-flex h-5 w-9 flex-shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 ${
                  f.is_default ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'
                }`,
                onClick: () => (f.is_default = !f.is_default),
              },
              [
                h('span', {
                  class: `inline-block h-4 w-4 transform rounded-full bg-white shadow transition-transform ${
                    f.is_default ? 'translate-x-4' : 'translate-x-0.5'
                  }`,
                }),
              ]
            ),
            h(
              'div',
              {
                class:
                  'flex items-center gap-1.5 text-sm font-medium text-gray-700 dark:text-gray-300',
              },
              [h(StarIcon, { class: 'w-4 h-4 text-amber-400' }), 'Set as default persona']
            ),
          ]),
        ])
      }
    },
  })

  export default { components: { PersonaFormFields } }
</script>
