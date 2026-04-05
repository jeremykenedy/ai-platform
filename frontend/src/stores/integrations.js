import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useIntegrationsStore = defineStore('integrations', () => {
  const definitions = ref([])
  const isLoading = ref(false)

  const connected = computed(() => definitions.value.filter((d) => d.connected))

  const byCategory = computed(() => {
    const map = {}
    for (const def of definitions.value) {
      const cat = def.category ?? 'Other'
      if (!map[cat]) map[cat] = []
      map[cat].push(def)
    }
    return map
  })

  async function fetch() {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/integrations')
      definitions.value = response.data.data ?? response.data
    } finally {
      isLoading.value = false
    }
  }

  async function connect(name, credentials) {
    const response = await api.post(`/api/v1/integrations/${name}/connect`, credentials)
    const updated = response.data.data ?? response.data
    const index = definitions.value.findIndex((d) => d.name === name)
    if (index !== -1) {
      definitions.value[index] = { ...definitions.value[index], ...updated, connected: true }
    }
    return updated
  }

  async function disconnect(name) {
    await api.post(`/api/v1/integrations/${name}/disconnect`)
    const index = definitions.value.findIndex((d) => d.name === name)
    if (index !== -1) {
      definitions.value[index] = { ...definitions.value[index], connected: false }
    }
  }

  async function executeTool(name, tool, params) {
    const response = await api.post(`/api/v1/integrations/${name}/tools/${tool}`, params)
    return response.data
  }

  return {
    definitions,
    isLoading,
    connected,
    byCategory,
    fetch,
    connect,
    disconnect,
    executeTool,
  }
})
