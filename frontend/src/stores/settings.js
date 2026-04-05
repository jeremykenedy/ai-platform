import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useSettingsStore = defineStore('settings', () => {
  const settings = ref(null)
  const isLoading = ref(false)

  async function fetch() {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/settings')
      settings.value = response.data.data ?? response.data
    } finally {
      isLoading.value = false
    }
  }

  async function update(data) {
    const response = await api.patch('/api/v1/settings', data)
    settings.value = response.data.data ?? response.data
    return settings.value
  }

  return {
    settings,
    isLoading,
    fetch,
    update,
  }
})
