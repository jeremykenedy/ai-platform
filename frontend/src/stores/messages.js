import { ref, computed } from 'vue'
import { defineStore } from 'pinia'
import { useConversationsStore } from '@/stores/conversations'
import api from '@/services/api'

export const useMessagesStore = defineStore('messages', () => {
  const messages = ref(new Map())
  const streamingMessageId = ref(null)
  const pendingTokens = ref('')
  const isStreaming = ref(false)
  const error = ref(null)

  const isGenerating = computed(() => isStreaming.value)

  const activeMessages = computed(() => {
    const conversationsStore = useConversationsStore()
    const id = conversationsStore.activeId
    if (!id) return []
    const msgs = messages.value.get(id) ?? []
    return [...msgs].sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
  })

  async function fetchForConversation(conversationId, cursor = null) {
    const params = cursor ? { cursor } : {}
    let response
    try {
      response = await api.get(`/conversations/${conversationId}/messages`, { params })
    } catch (err) {
      if (err?.response?.status === 404) {
        messages.value.set(conversationId, [])
        return []
      }
      throw err
    }
    const fetched = response.data.data ?? response.data
    const existing = messages.value.get(conversationId) ?? []

    if (cursor) {
      const existingIds = new Set(existing.map((m) => m.id))
      const older = fetched.filter((m) => !existingIds.has(m.id))
      messages.value.set(conversationId, [...older, ...existing])
      return fetched
    }

    // Initial load: the server is the source of truth for persisted
    // messages. Keep only optimistic messages whose content is not
    // already represented in the fetched set (i.e., still in flight
    // server-side). De-dupe by trimmed content+role rather than id,
    // since the optimistic id ("pending-…") never matches a real ULID.
    const fetchedContent = new Set(
      fetched.map((m) => `${m.role}::${(m.content || '').trim()}`)
    )
    const stillPending = existing.filter(
      (m) =>
        m.pending &&
        !fetchedContent.has(`${m.role}::${(m.content || '').trim()}`)
    )
    messages.value.set(conversationId, [...fetched, ...stillPending])
    return fetched
  }

  async function send(conversationId, content, options = {}) {
    error.value = null
    isStreaming.value = true
    pendingTokens.value = ''

    const optimistic = {
      id: `pending-${Date.now()}`,
      conversation_id: conversationId,
      role: 'user',
      content,
      created_at: new Date().toISOString(),
      pending: true,
    }

    const current = messages.value.get(conversationId) ?? []
    messages.value.set(conversationId, [...current, optimistic])

    try {
      const response = await api.post(`/conversations/${conversationId}/messages`, {
        content,
        ...options,
      })
      const saved = response.data.data ?? response.data
      const now = messages.value.get(conversationId) ?? []
      // Drop the optimistic unconditionally and append saved if missing.
      const withoutOptimistic = now.filter((m) => m.id !== optimistic.id)
      const hasSaved = withoutOptimistic.some((m) => m.id === saved.id)
      const next = hasSaved ? withoutOptimistic : [...withoutOptimistic, saved]
      messages.value.set(conversationId, next)
      return saved
    } catch (err) {
      handleStreamError(err)
      const rollback = (messages.value.get(conversationId) ?? []).filter(
        (m) => m.id !== optimistic.id
      )
      messages.value.set(conversationId, rollback)
      throw err
    } finally {
      isStreaming.value = false
    }
  }

  async function deleteMessage(id) {
    for (const [convId, msgs] of messages.value.entries()) {
      const index = msgs.findIndex((m) => m.id === id)
      if (index !== -1) {
        await api.delete(`/conversations/${convId}/messages/${id}`)
        const updated = msgs.filter((m) => m.id !== id)
        messages.value.set(convId, updated)
        return
      }
    }
  }

  async function regenerate(conversationId, messageId) {
    error.value = null
    isStreaming.value = true
    pendingTokens.value = ''
    streamingMessageId.value = messageId

    try {
      const response = await api.post(
        `/conversations/${conversationId}/messages/${messageId}/regenerate`
      )
      const updated = response.data.data ?? response.data
      if (updated) finalizeMessage(updated)
      return updated
    } catch (err) {
      handleStreamError(err)
      throw err
    } finally {
      isStreaming.value = false
      streamingMessageId.value = null
    }
  }

  function beginAssistantStream(conversationId, messageId, role = 'assistant', content = '') {
    streamingMessageId.value = messageId
    pendingTokens.value = content
    isStreaming.value = true
    error.value = null

    const existing = messages.value.get(conversationId) ?? []
    if (existing.some((m) => m.id === messageId)) return

    const placeholder = {
      id: messageId,
      conversation_id: conversationId,
      role,
      content,
      created_at: new Date().toISOString(),
      isStreaming: true,
    }
    messages.value.set(conversationId, [...existing, placeholder])
  }

  function appendToken(token) {
    pendingTokens.value += token

    if (streamingMessageId.value) {
      for (const [convId, msgs] of messages.value.entries()) {
        const index = msgs.findIndex((m) => m.id === streamingMessageId.value)
        if (index !== -1) {
          const updated = [...msgs]
          updated[index] = {
            ...updated[index],
            content: pendingTokens.value,
            isStreaming: true,
          }
          messages.value.set(convId, updated)
          return
        }
      }
    }
  }

  function finalizeMessage(message) {
    if (streamingMessageId.value === message.id) {
      streamingMessageId.value = null
      pendingTokens.value = ''
      isStreaming.value = false
    }

    for (const [convId, msgs] of messages.value.entries()) {
      const index = msgs.findIndex((m) => m.id === message.id)
      if (index !== -1) {
        const updated = [...msgs]
        updated[index] = { ...message, isStreaming: false }
        messages.value.set(convId, updated)
        return
      }
    }

    if (message.conversation_id) {
      const existing = messages.value.get(message.conversation_id) ?? []
      messages.value.set(message.conversation_id, [...existing, { ...message, isStreaming: false }])
    }
  }

  function handleStreamError(err) {
    error.value = err?.response?.data?.message ?? err?.message ?? 'An error occurred'
    isStreaming.value = false
    streamingMessageId.value = null
  }

  function cancelStream() {
    isStreaming.value = false
    streamingMessageId.value = null
    pendingTokens.value = ''
  }

  return {
    messages,
    streamingMessageId,
    pendingTokens,
    isStreaming,
    error,
    isGenerating,
    activeMessages,
    fetchForConversation,
    send,
    deleteMessage,
    regenerate,
    beginAssistantStream,
    appendToken,
    finalizeMessage,
    handleStreamError,
    cancelStream,
  }
})
