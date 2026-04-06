import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useModelsStore = defineStore('models', () => {
  const models = ref([])
  const activeModelId = ref(null)
  const isLoading = ref(false)

  const availableModels = computed(() => models.value.filter((m) => m.available !== false))

  const activeModel = computed(() => models.value.find((m) => m.id === activeModelId.value) ?? null)

  const localModels = computed(() => models.value.filter((m) => m.provider === 'ollama'))

  const remoteModels = computed(() => models.value.filter((m) => m.provider !== 'ollama'))

  const defaultModel = computed(
    () => models.value.find((m) => m.is_default) ?? models.value[0] ?? null
  )

  async function fetch() {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/models')
      models.value = response.data.data ?? response.data
      if (!activeModelId.value && defaultModel.value) {
        activeModelId.value = defaultModel.value.id
      }
    } finally {
      isLoading.value = false
    }
  }

  function setActive(id) {
    activeModelId.value = id
  }

  async function pull(modelName) {
    const response = await api.post('/api/v1/models/pull', { model: modelName })
    return response.data
  }

  async function deleteModel(id) {
    await api.delete(`/api/v1/models/${id}`)
    models.value = models.value.filter((m) => m.id !== id)
    if (activeModelId.value === id) {
      activeModelId.value = defaultModel.value?.id ?? null
    }
  }

  return {
    models,
    activeModelId,
    isLoading,
    availableModels,
    activeModel,
    localModels,
    remoteModels,
    defaultModel,
    fetch,
    setActive,
    pull,
    deleteModel,
  }
})
