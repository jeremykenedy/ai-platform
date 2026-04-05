import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import api from '@/services/api'

export const useProjectsStore = defineStore('projects', () => {
  const projects = ref([])
  const activeId = ref(null)
  const isLoading = ref(false)

  const activeProject = computed(() =>
    projects.value.find((p) => p.id === activeId.value) ?? null,
  )

  const sortedProjects = computed(() =>
    [...projects.value].sort(
      (a, b) => new Date(b.updated_at) - new Date(a.updated_at),
    ),
  )

  async function fetch() {
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/projects')
      projects.value = response.data.data ?? response.data
    } finally {
      isLoading.value = false
    }
  }

  async function create(data) {
    const response = await api.post('/api/v1/projects', data)
    const project = response.data.data ?? response.data
    projects.value.unshift(project)
    return project
  }

  async function update(id, data) {
    const response = await api.patch(`/api/v1/projects/${id}`, data)
    const updated = response.data.data ?? response.data
    const index = projects.value.findIndex((p) => p.id === id)
    if (index !== -1) projects.value[index] = updated
    return updated
  }

  async function destroy(id) {
    await api.delete(`/api/v1/projects/${id}`)
    projects.value = projects.value.filter((p) => p.id !== id)
    if (activeId.value === id) activeId.value = null
  }

  function setActive(id) {
    activeId.value = id
  }

  return {
    projects,
    activeId,
    isLoading,
    activeProject,
    sortedProjects,
    fetch,
    create,
    update,
    destroy,
    setActive,
  }
})
