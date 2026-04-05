let conversations = []
let messages = []

function fuzzyMatch(text, query) {
  const lower = text.toLowerCase()
  const q = query.toLowerCase()
  return lower.includes(q)
}

function searchConversations(query) {
  return conversations
    .filter(
      (c) =>
        fuzzyMatch(c.title || '', query) ||
        (c.messages || []).some((m) => fuzzyMatch(m.content || '', query))
    )
    .map((c) => ({ id: c.id, title: c.title, type: 'conversation' }))
}

function searchMessages(query) {
  return messages
    .filter((m) => fuzzyMatch(m.content || '', query))
    .map((m) => ({
      id: m.id,
      content: m.content.substring(0, 200),
      conversationId: m.conversation_id,
      type: 'message',
    }))
    .slice(0, 50)
}

self.onmessage = ({ data }) => {
  const { type, payload } = data

  if (type === 'setData') {
    conversations = payload.conversations || []
    messages = payload.messages || []
    return
  }

  if (type === 'search') {
    const results = [
      ...searchConversations(payload.query),
      ...searchMessages(payload.query),
    ]
    self.postMessage({ type: 'results', results })
  }
}
