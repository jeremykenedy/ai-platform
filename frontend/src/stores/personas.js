import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const usePersonasStore = defineStore('personas', () => {
  const personas = ref([])
  const activeId = ref(null)
  const isLoading = ref(false)

  const activePersona = computed(() => personas.value.find((p) => p.id === activeId.value) ?? null)

  const sortedPersonas = computed(() =>
    [...personas.value].sort((a, b) => a.name.localeCompare(b.name))
  )

  async function fetch() {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/personas')
      personas.value = response.data.data ?? response.data
    } finally {
      isLoading.value = false
    }
  }

  async function create(data) {
    const response = await api.post('/api/v1/personas', data)
    const persona = response.data.data ?? response.data
    personas.value.push(persona)
    return persona
  }

  async function update(id, data) {
    const response = await api.patch(`/api/v1/personas/${id}`, data)
    const updated = response.data.data ?? response.data
    const index = personas.value.findIndex((p) => p.id === id)
    if (index !== -1) personas.value[index] = updated
    return updated
  }

  async function destroy(id) {
    await api.delete(`/api/v1/personas/${id}`)
    personas.value = personas.value.filter((p) => p.id !== id)
    if (activeId.value === id) activeId.value = null
  }

  function setActive(id) {
    activeId.value = id
  }

  return {
    personas,
    activeId,
    isLoading,
    activePersona,
    sortedPersonas,
    fetch,
    create,
    update,
    destroy,
    setActive,
  }
})
