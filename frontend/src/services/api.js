import axios from 'axios'

const api = axios.create({
  baseURL: '/api/v1',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
  withCredentials: true,
  withXSRFToken: true,
})

// CSRF cookie handling
let csrfInitialized = false
async function ensureCsrf() {
  if (!csrfInitialized) {
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true })
    csrfInitialized = true
  }
}

// Request cancellation tracking
const pendingRequests = new Map()

api.interceptors.request.use(async (config) => {
  if (['post', 'put', 'patch', 'delete'].includes(config.method)) {
    await ensureCsrf()
  }

  const controller = new AbortController()
  config.signal = controller.signal
  const key = `${config.method}:${config.url}`
  if (pendingRequests.has(key)) {
    pendingRequests.get(key).abort()
  }
  pendingRequests.set(key, controller)
  return config
})

api.interceptors.response.use(
  (response) => {
    const key = `${response.config.method}:${response.config.url}`
    pendingRequests.delete(key)
    return response
  },
  (error) => {
    if (axios.isCancel(error)) return Promise.resolve(null)

    if (error.response?.status === 401) {
      // Session expired, redirect to login
      window.location.href = '/login'
    }

    if (error.response?.status === 419) {
      // CSRF token mismatch, refresh and retry
      csrfInitialized = false
    }

    return Promise.reject(error)
  }
)

export function cancelAllRequests() {
  pendingRequests.forEach((controller) => controller.abort())
  pendingRequests.clear()
}

export default api
