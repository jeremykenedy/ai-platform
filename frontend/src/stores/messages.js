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
    const response = await api.get(`/api/v1/conversations/${conversationId}/messages`, { params })
    const data = response.data.data ?? response.data
    messages.value.set(conversationId, data)
    return data
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
      const response = await api.post(`/api/v1/conversations/${conversationId}/messages`, {
        content,
        ...options,
      })
      const saved = response.data.data ?? response.data
      const updated = (messages.value.get(conversationId) ?? []).map((m) =>
        m.id === optimistic.id ? saved : m
      )
      messages.value.set(conversationId, updated)
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
        await api.delete(`/api/v1/messages/${id}`)
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
      const response = await api.post(`/api/v1/messages/${messageId}/regenerate`)
      const updated = response.data.data ?? response.data
      finalizeMessage(updated)
      return updated
    } catch (err) {
      handleStreamError(err)
      throw err
    } finally {
      isStreaming.value = false
      streamingMessageId.value = null
    }
  }

  function appendToken(token) {
    pendingTokens.value += token

    if (streamingMessageId.value) {
      for (const [convId, msgs] of messages.value.entries()) {
        const index = msgs.findIndex((m) => m.id === streamingMessageId.value)
        if (index !== -1) {
          const updated = [...msgs]
          updated[index] = { ...updated[index], content: pendingTokens.value }
          messages.value.set(convId, updated)
          return
        }
      }
    }
  }

  function finalizeMessage(message) {
    for (const [convId, msgs] of messages.value.entries()) {
      const index = msgs.findIndex((m) => m.id === message.id)
      if (index !== -1) {
        const updated = [...msgs]
        updated[index] = message
        messages.value.set(convId, updated)
        return
      }
    }

    if (message.conversation_id) {
      const existing = messages.value.get(message.conversation_id) ?? []
      messages.value.set(message.conversation_id, [...existing, message])
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
    appendToken,
    finalizeMessage,
    handleStreamError,
    cancelStream,
  }
})
