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

api.interceptors.request.use(async (config) => {
  if (['post', 'put', 'patch', 'delete'].includes(config.method)) {
    await ensureCsrf()
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    // Silently swallow canceled requests (navigation, component unmount, etc.)
    if (axios.isCancel(error) || error?.code === 'ERR_CANCELED') {
      return new Promise(() => {}) // Never resolves, never rejects — just dies silently
    }

    if (error.response?.status === 401) {
      const url = error.config?.url ?? ''
      if (!url.includes('/auth/user')) {
        window.location.href = '/login'
      }
    }

    if (error.response?.status === 419) {
      csrfInitialized = false
    }

    return Promise.reject(error)
  }
)

export function cancelAllRequests() {
  // No-op: request cancellation removed to prevent UI crashes
}

export default api
