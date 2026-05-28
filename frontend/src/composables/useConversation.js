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
    // Fallback poll: even if the WebSocket missed the inference broadcast
    // (race between job dispatch and channel subscription on a brand-new
    // conversation), refetch every 3s until the assistant message arrives
    // or 60s elapses. Cheap, and guarantees the user sees a response.
    pollUntilAssistant(conversationId, 60000)
  }

  function pollUntilAssistant(conversationId, timeoutMs) {
    const start = Date.now()
    const tick = async () => {
      if (Date.now() - start > timeoutMs) {
        messages.cancelStream()
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
        setTimeout(tick, 3000)
      } catch {
        setTimeout(tick, 3000)
      }
    }
    setTimeout(tick, 3000)
  }



  function cancel() {
    messages.cancelStream()
  }

  return { activeConversation, activeMessages, isStreaming, send, cancel }
}
