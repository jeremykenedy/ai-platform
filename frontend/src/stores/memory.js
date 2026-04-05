import { ref } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useMemoryStore = defineStore('memory', () => {
  const memories = ref([])
  const conflicts = ref([])
  const isLoading = ref(false)
  const pagination = ref({ nextCursor: null, hasMore: false })

  async function fetch(params = {}) {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/memory', { params })
      memories.value = response.data.data ?? response.data
      pagination.value = {
        nextCursor: response.data.meta?.next_cursor ?? null,
        hasMore: response.data.meta?.has_more ?? false,
      }
    } finally {
      isLoading.value = false
    }
  }

  async function create(data) {
    const response = await api.post('/api/v1/memory', data)
    const memory = response.data.data ?? response.data
    memories.value.unshift(memory)
    return memory
  }

  async function update(id, data) {
    const response = await api.patch(`/api/v1/memory/${id}`, data)
    const updated = response.data.data ?? response.data
    const index = memories.value.findIndex((m) => m.id === id)
    if (index !== -1) memories.value[index] = updated
    return updated
  }

  async function destroy(id) {
    await api.delete(`/api/v1/memory/${id}`)
    memories.value = memories.value.filter((m) => m.id !== id)
  }

  async function bulkDestroy(ids) {
    await api.post('/api/v1/memory/bulk-destroy', { ids })
    memories.value = memories.value.filter((m) => !ids.includes(m.id))
  }

  async function fetchConflicts() {
    const response = await api.get('/api/v1/memory/conflicts')
    conflicts.value = response.data.data ?? response.data
    return conflicts.value
  }

  async function resolveConflict(conflictId, resolution) {
    const response = await api.post(`/api/v1/memory/conflicts/${conflictId}/resolve`, { resolution })
    conflicts.value = conflicts.value.filter((c) => c.id !== conflictId)
    return response.data
  }

  return {
    memories,
    conflicts,
    isLoading,
    pagination,
    fetch,
    create,
    update,
    destroy,
    bulkDestroy,
    fetchConflicts,
    resolveConflict,
  }
})
