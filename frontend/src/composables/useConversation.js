import { computed } from 'vue'
import { useConversationsStore } from '@/stores/conversations'
import { useMessagesStore } from '@/stores/messages'
import { useRouter } from 'vue-router'

export function useConversation() {
  const conversations = useConversationsStore()
  const messages = useMessagesStore()
  const router = useRouter()

  const activeConversation = computed(() => conversations.activeConversation)
  const activeMessages = computed(() => messages.activeMessages)
  const isStreaming = computed(() => messages.isStreaming)

  async function send(content, options = {}) {
    let conversationId = conversations.activeId
    if (!conversationId) {
      const conversation = await conversations.create({
        model_name: options.model,
        persona_id: options.personaId,
        project_id: options.projectId,
      })
      conversationId = conversation.id
      router.push(`/c/${conversationId}`)
    }
    await messages.send(conversationId, content, options)
    // Fallback poll: matches the backend StreamInferenceJob timeout (900s).
    // Long outputs on CPU-only Ollama can take 5-10 minutes.
    pollUntilAssistant(conversationId, 900000)
  }

  function pollUntilAssistant(conversationId, timeoutMs) {
    const start = Date.now()
    const interval = 1500
    const tick = async () => {
      if (Date.now() - start > timeoutMs) {
        messages.handleStreamError(new Error(
          'Response exceeded 15 minutes. Try a shorter prompt or smaller model.'
        ))
        return
      }
      try {
        const fetched = await messages.fetchForConversation(conversationId)
        const finished = (fetched ?? []).find(
          (m) => m.role === 'assistant' && m.finish_reason && m.content
        )
        if (finished) {
          // Clears isStreaming, streamingMessageId, and watchdog.
          messages.finalizeMessage(finished)
          // Refresh sidebar so the auto-generated title shows up.
          conversations.fetch().catch(() => {})
          return
        }
        // Also surface partial progress: if there's an assistant message
        // with content but no finish_reason yet, show it as streaming.
        const partial = (fetched ?? []).find(
          (m) => m.role === 'assistant' && m.content && !m.finish_reason
        )
        if (partial) {
          messages.beginAssistantStream(
            conversationId,
            partial.id,
            'assistant',
            partial.content
          )
        }
        setTimeout(tick, interval)
      } catch {
        setTimeout(tick, interval)
      }
    }
    setTimeout(tick, interval)
  }

  function cancel() {
    messages.cancelStream()
  }

  return { activeConversation, activeMessages, isStreaming, send, cancel }
}
