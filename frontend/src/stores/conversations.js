import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { isToday, isYesterday, isWithinInterval, subDays, startOfDay } from 'date-fns'
import api from '@/services/api'

export const useConversationsStore = defineStore('conversations', () => {
  const conversations = ref([])
  const activeId = ref(null)
  const isLoading = ref(false)
  const pagination = ref({ nextCursor: null, hasMore: false })

  const activeConversation = computed(() =>
    conversations.value.find((c) => c.id === activeId.value) ?? null,
  )

  const sortedConversations = computed(() =>
    [...conversations.value].sort(
      (a, b) => new Date(b.updated_at) - new Date(a.updated_at),
    ),
  )

  const grouped = computed(() => {
    const groups = {
      Today: [],
      Yesterday: [],
      'Last 7 days': [],
      Older: [],
    }

    const sevenDaysAgo = startOfDay(subDays(new Date(), 7))

    for (const convo of sortedConversations.value) {
      const date = new Date(convo.updated_at)
      if (isToday(date)) {
        groups['Today'].push(convo)
      } else if (isYesterday(date)) {
        groups['Yesterday'].push(convo)
      } else if (isWithinInterval(date, { start: sevenDaysAgo, end: new Date() })) {
        groups['Last 7 days'].push(convo)
      } else {
        groups['Older'].push(convo)
      }
    }

    return groups
  })

  async function fetch(cursor = null) {
    isLoading.value = true
    try {
      const params = cursor ? { cursor } : {}
      const response = await api.get('/api/v1/conversations', { params })
      conversations.value = response.data.data ?? response.data
      pagination.value = {
        nextCursor: response.data.meta?.next_cursor ?? null,
        hasMore: response.data.meta?.has_more ?? false,
      }
    } finally {
      isLoading.value = false
    }
  }

  async function fetchMore() {
    if (!pagination.value.hasMore || isLoading.value) return
    isLoading.value = true
    try {
      const response = await api.get('/api/v1/conversations', {
        params: { cursor: pagination.value.nextCursor },
      })
      const incoming = response.data.data ?? response.data
      conversations.value.push(...incoming)
      pagination.value = {
        nextCursor: response.data.meta?.next_cursor ?? null,
        hasMore: response.data.meta?.has_more ?? false,
      }
    } finally {
      isLoading.value = false
    }
  }

  async function create(data) {
    const response = await api.post('/api/v1/conversations', data)
    const convo = response.data.data ?? response.data
    conversations.value.unshift(convo)
    return convo
  }

  async function update(id, data) {
    const response = await api.patch(`/api/v1/conversations/${id}`, data)
    const updated = response.data.data ?? response.data
    const index = conversations.value.findIndex((c) => c.id === id)
    if (index !== -1) conversations.value[index] = updated
    return updated
  }

  async function destroy(id) {
    await api.delete(`/api/v1/conversations/${id}`)
    conversations.value = conversations.value.filter((c) => c.id !== id)
    if (activeId.value === id) activeId.value = null
  }

  function setActive(id) {
    activeId.value = id
  }

  return {
    conversations,
    activeId,
    isLoading,
    pagination,
    activeConversation,
    sortedConversations,
    grouped,
    fetch,
    fetchMore,
    create,
    update,
    destroy,
    setActive,
  }
})
