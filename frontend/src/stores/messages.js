import { ref, reactive, computed } from 'vue'
import { defineStore } from 'pinia'
import { useConversationsStore } from '@/stores/conversations'
import api from '@/services/api'

export const useMessagesStore = defineStore('messages', () => {
  // Plain reactive object keyed by conversationId. Map mutations are not
  // deeply reactive in Vue 3 the way object property writes are, so use
  // an object so activeMessages re-evaluates on every set.
  const messages = reactive({})
  const streamingMessageId = ref(null)
  const pendingTokens = ref('')
  const isStreaming = ref(false)
  const error = ref(null)

  const isGenerating = computed(() => isStreaming.value)

  const activeMessages = computed(() => {
    const conversationsStore = useConversationsStore()
    const id = conversationsStore.activeId
    if (!id) return []
    const msgs = messages[id] ?? []
    return [...msgs].sort((a, b) => new Date(a.created_at) - new Date(b.created_at))
  })

  async function fetchForConversation(conversationId, cursor = null) {
    const params = cursor ? { cursor } : {}
    let response
    try {
      response = await api.get(`/conversations/${conversationId}/messages`, { params })
    } catch (err) {
      if (err?.response?.status === 404) {
        messages[conversationId] = []
        return []
      }
      throw err
    }
    const fetched = response.data.data ?? response.data
    const existing = messages[conversationId] ?? []

    if (cursor) {
      const existingIds = new Set(existing.map((m) => m.id))
      const older = fetched.filter((m) => !existingIds.has(m.id))
      messages[conversationId] = [...older, ...existing]
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
    messages[conversationId] = [...fetched, ...stillPending]
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

    const current = messages[conversationId] ?? []
    messages[conversationId] = [...current, optimistic]

    try {
      const response = await api.post(`/conversations/${conversationId}/messages`, {
        content,
        ...options,
      })
      const saved = response.data.data ?? response.data
      const now = messages[conversationId] ?? []
      // Drop the optimistic unconditionally and append saved if missing.
      const withoutOptimistic = now.filter((m) => m.id !== optimistic.id)
      const hasSaved = withoutOptimistic.some((m) => m.id === saved.id)
      messages[conversationId] = hasSaved
        ? withoutOptimistic
        : [...withoutOptimistic, saved]
      // Keep isStreaming TRUE here — the assistant is now being generated
      // server-side. It will flip to false in finalizeMessage when WS
      // StreamCompleted fires, or via the timeout watchdog below.
      armStreamingWatchdog()
      return saved
    } catch (err) {
      handleStreamError(err)
      messages[conversationId] = (messages[conversationId] ?? []).filter(
        (m) => m.id !== optimistic.id
      )
      isStreaming.value = false
      throw err
    }
  }

  let streamingWatchdog = null
  function armStreamingWatchdog() {
    if (streamingWatchdog) clearTimeout(streamingWatchdog)
    // After 2 minutes with no StreamCompleted, give up and clear the
    // streaming flag so the UI doesn't spin forever.
    streamingWatchdog = setTimeout(() => {
      isStreaming.value = false
      streamingMessageId.value = null
      streamingWatchdog = null
    }, 120000)
  }
  function clearStreamingWatchdog() {
    if (streamingWatchdog) {
      clearTimeout(streamingWatchdog)
      streamingWatchdog = null
    }
  }

  async function deleteMessage(id) {
    for (const convId of Object.keys(messages)) {
      const msgs = messages[convId]
      const index = msgs.findIndex((m) => m.id === id)
      if (index !== -1) {
        await api.delete(`/conversations/${convId}/messages/${id}`)
        messages[convId] = msgs.filter((m) => m.id !== id)
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

    const existing = messages[conversationId] ?? []
    if (existing.some((m) => m.id === messageId)) return

    const placeholder = {
      id: messageId,
      conversation_id: conversationId,
      role,
      content,
      created_at: new Date().toISOString(),
      isStreaming: true,
    }
    messages[conversationId] = [...existing, placeholder]
  }

  function appendToken(token) {
    pendingTokens.value += token

    if (!streamingMessageId.value) return

    for (const convId of Object.keys(messages)) {
      const msgs = messages[convId]
      const index = msgs.findIndex((m) => m.id === streamingMessageId.value)
      if (index !== -1) {
        const updated = [...msgs]
        updated[index] = {
          ...updated[index],
          content: pendingTokens.value,
          isStreaming: true,
        }
        messages[convId] = updated
        return
      }
    }
  }

  function finalizeMessage(message) {
    if (streamingMessageId.value === message.id) {
      streamingMessageId.value = null
      pendingTokens.value = ''
      isStreaming.value = false
      clearStreamingWatchdog()
    }

    for (const convId of Object.keys(messages)) {
      const msgs = messages[convId]
      const index = msgs.findIndex((m) => m.id === message.id)
      if (index !== -1) {
        const updated = [...msgs]
        updated[index] = { ...message, isStreaming: false }
        messages[convId] = updated
        return
      }
    }

    if (message.conversation_id) {
      const existing = messages[message.conversation_id] ?? []
      messages[message.conversation_id] = [...existing, { ...message, isStreaming: false }]
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
