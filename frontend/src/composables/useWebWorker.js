import { ref, onUnmounted } from 'vue'

export function useWebWorker(workerFactory) {
  const worker = ref(null)
  const callbacks = new Map()
  let idCounter = 0

  function init() {
    if (worker.value) return
    worker.value = workerFactory()
    worker.value.onmessage = ({ data }) => {
      if (data.id !== undefined && callbacks.has(data.id)) {
        callbacks.get(data.id)(data)
        callbacks.delete(data.id)
      }
    }
  }

  function send(payload) {
    init()
    return new Promise((resolve) => {
      const id = idCounter++
      callbacks.set(id, resolve)
      worker.value.postMessage({ id, ...payload })
    })
  }

  function post(payload) {
    init()
    worker.value.postMessage(payload)
  }

  onUnmounted(() => {
    if (worker.value) {
      worker.value.terminate()
      worker.value = null
    }
    callbacks.clear()
  })

  return { send, post, worker }
}
