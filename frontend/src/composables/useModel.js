import { computed } from 'vue'
import { useModelsStore } from '@/stores/models'

export function useModel() {
  const store = useModelsStore()

  const activeModel = computed(() => store.activeModel)
  const availableModels = computed(() => store.availableModels)

  function switchModel(modelId) {
    store.setActive(modelId)
  }

  return { activeModel, availableModels, switchModel }
}
