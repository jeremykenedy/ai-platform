export async function streamMessage(conversationId, content, options = {}) {
  const controller = new AbortController()
  const { onToken, onComplete, onError } = options

  try {
    const csrfToken = document.cookie
      .split('; ')
      .find((c) => c.startsWith('XSRF-TOKEN='))
      ?.split('=')[1]

    const response = await fetch(`/api/v1/conversations/${conversationId}/messages`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'text/event-stream',
        'X-XSRF-TOKEN': csrfToken ? decodeURIComponent(csrfToken) : '',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'include',
      body: JSON.stringify({ content, stream: true, ...options }),
      signal: controller.signal,
    })

    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: 'Stream failed' }))
      onError?.(error)
      return controller
    }

    // Streaming is handled via WebSocket (Reverb), not SSE from HTTP response
    // The POST returns 202 Accepted with the message ID
    const data = await response.json()
    onComplete?.(data)
  } catch (err) {
    if (err.name !== 'AbortError') {
      onError?.(err)
    }
  }

  return controller
}

export function cancelStream(controller) {
  if (controller) {
    controller.abort()
  }
}
