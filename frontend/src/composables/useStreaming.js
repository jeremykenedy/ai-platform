import { ref, onUnmounted } from 'vue'
import { getEcho, leaveChannel } from '@/services/echo'

export function useStreaming(conversationId) {
  const isConnected = ref(false)
  const lastSequence = ref(0)
  let channel = null

  function connect(handlers = {}) {
    if (channel) disconnect()

    const echo = getEcho()
    channel = echo.private(`conversation.${conversationId.value || conversationId}`)

    channel
      .listen('.TokenReceived', (e) => {
        if (e.sequence > lastSequence.value) {
          lastSequence.value = e.sequence
          handlers.onToken?.(e.token, e.sequence)
        }
      })
      .listen('.StreamCompleted', (e) => {
        handlers.onComplete?.(e)
        lastSequence.value = 0
      })
      .listen('.StreamFailed', (e) => {
        handlers.onError?.(e)
        lastSequence.value = 0
      })

    isConnected.value = true
  }

  function disconnect() {
    const id = conversationId.value || conversationId
    if (channel) {
      leaveChannel(`conversation.${id}`)
      channel = null
    }
    isConnected.value = false
    lastSequence.value = 0
  }

  onUnmounted(() => {
    disconnect()
  })

  return { isConnected, lastSequence, connect, disconnect }
}
