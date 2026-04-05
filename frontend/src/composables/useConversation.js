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
  }

  function cancel() {
    messages.cancelStream()
  }

  return { activeConversation, activeMessages, isStreaming, send, cancel }
}
