<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Link2, Link2Off, Key, CheckCircle2, XCircle, Loader2, AlertCircle, Clock, X, TestTube2 } from 'lucide-vue-next'
import { useIntegrationsStore } from '@/stores/integrations'
import { useUiStore } from '@/stores/ui'

const integrationsStore = useIntegrationsStore()
const uiStore = useUiStore()

onMounted(() => integrationsStore.fetch())

const AUTH_TYPE_LABELS = {
  oauth: 'OAuth',
  api_key: 'API Key',
  none: 'None',
}

const AUTH_TYPE_COLORS = {
  oauth: 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300',
  api_key: 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300',
  none: 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300',
}

const loadingNames = ref(new Set())
const disconnectConfirmName = ref(null)
const apiKeyModal = reactive({ open: false, integration: null, key: '', error: '', testing: false, saving: false })

function setLoading(name, val) {
  if (val) {
    loadingNames.value.add(name)
  } else {
    loadingNames.value.delete(name)
  }
  loadingNames.value = new Set(loadingNames.value)
}

async function handleConnect(integration) {
  if (integration.auth_type === 'oauth') {
    // Open OAuth redirect
    if (integration.oauth_url) {
      window.location.href = integration.oauth_url
    } else {
      uiStore.addToast({ type: 'error', message: 'OAuth URL not configured for this integration.' })
    }
    return
  }
  if (integration.auth_type === 'api_key') {
    apiKeyModal.integration = integration
    apiKeyModal.key = ''
    apiKeyModal.error = ''
    apiKeyModal.open = true
    return
  }
  // None type: direct connect
  setLoading(integration.name, true)
  try {
    await integrationsStore.connect(integration.name, {})
    uiStore.addToast({ type: 'success', message: `${integration.display_name} connected.` })
  } catch (err) {
    const msg = err?.response?.data?.message ?? 'Connection failed.'
    uiStore.addToast({ type: 'error', message: msg })
  } finally {
    setLoading(integration.name, false)
  }
}

async function saveApiKey() {
  if (!apiKeyModal.key.trim()) {
    apiKeyModal.error = 'API key is required.'
    return
  }
  apiKeyModal.saving = true
  apiKeyModal.error = ''
  try {
    await integrationsStore.connect(apiKeyModal.integration.name, { api_key: apiKeyModal.key })
    apiKeyModal.open = false
    uiStore.addToast({ type: 'success', message: `${apiKeyModal.integration.display_name} connected.` })
  } catch (err) {
    apiKeyModal.error = err?.response?.data?.message ?? 'Failed to connect.'
  } finally {
    apiKeyModal.saving = false
  }
}

async function testApiKey() {
  if (!apiKeyModal.key.trim()) {
    apiKeyModal.error = 'Enter an API key to test.'
    return
  }
  apiKeyModal.testing = true
  apiKeyModal.error = ''
  try {
    await integrationsStore.executeTool(apiKeyModal.integration.name, 'test', { api_key: apiKeyModal.key })
    uiStore.addToast({ type: 'success', message: 'Connection test successful.' })
  } catch (err) {
    apiKeyModal.error = err?.response?.data?.message ?? 'Test failed. Check your API key.'
  } finally {
    apiKeyModal.testing = false
  }
}

async function handleDisconnect(integration) {
  setLoading(integration.name, true)
  try {
    await integrationsStore.disconnect(integration.name)
    disconnectConfirmName.value = null
    uiStore.addToast({ type: 'success', message: `${integration.display_name} disconnected.` })
  } catch (err) {
    const msg = err?.response?.data?.message ?? 'Failed to disconnect.'
    uiStore.addToast({ type: 'error', message: msg })
  } finally {
    setLoading(integration.name, false)
  }
}

function connectedCount() {
  return integrationsStore.connected.length
}

function formatDate(d) {
  if (!d) return null
  return new Date(d).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

function avatarLetter(name) {
  return (name ?? '?')[0].toUpperCase()
}

const CATEGORY_ORDER = ['Productivity', 'Developer', 'Design', 'Finance', 'Search', 'Career', 'Legal', 'Entertainment', 'Local', 'Other']
const sortedCategories = computed(() => {
  const keys = Object.keys(integrationsStore.byCategory)
  return [...CATEGORY_ORDER.filter((k) => keys.includes(k)), ...keys.filter((k) => !CATEGORY_ORDER.includes(k))]
})
</script>

<template>
  <div class="max-w-4xl mx-auto p-6 space-y-8">
    <!-- Header -->
    <div class="flex items-center gap-3">
      <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Integrations</h1>
      <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 dark:bg-green-900/50 text-green-700 dark:text-green-300">
        {{ connectedCount() }} connected
      </span>
    </div>

    <!-- Loading -->
    <div v-if="integrationsStore.isLoading" class="flex items-center justify-center gap-2 py-16 text-gray-400 dark:text-gray-500 text-sm">
      <Loader2 class="w-5 h-5 animate-spin" /> Loading integrations...
    </div>

    <!-- Categories -->
    <div v-else class="space-y-8">
      <div v-for="category in sortedCategories" :key="category">
        <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">{{ category }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="integration in integrationsStore.byCategory[category]"
            :key="integration.name"
            class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-4 flex flex-col gap-3 hover:border-gray-300 dark:hover:border-gray-600 transition"
          >
            <!-- Top row -->
            <div class="flex items-start gap-3">
              <!-- Icon avatar -->
              <div class="w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center flex-shrink-0 text-indigo-700 dark:text-indigo-300 font-bold text-base select-none">
                {{ avatarLetter(integration.display_name ?? integration.name) }}
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-1.5 flex-wrap">
                  <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ integration.display_name ?? integration.name }}</p>
                  <span
                    class="text-xs px-1.5 py-0.5 rounded font-medium"
                    :class="AUTH_TYPE_COLORS[integration.auth_type] ?? AUTH_TYPE_COLORS.none"
                  >{{ AUTH_TYPE_LABELS[integration.auth_type] ?? integration.auth_type }}</span>
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 line-clamp-2">{{ integration.description }}</p>
              </div>
            </div>

            <!-- Status & meta -->
            <div class="flex items-center gap-1.5 text-xs">
              <component
                :is="integration.connected ? CheckCircle2 : XCircle"
                class="w-3.5 h-3.5 flex-shrink-0"
                :class="integration.connected ? 'text-green-500' : 'text-gray-400 dark:text-gray-500'"
              />
              <span :class="integration.connected ? 'text-green-600 dark:text-green-400 font-medium' : 'text-gray-400 dark:text-gray-500'">
                {{ integration.connected ? 'Connected' : 'Not connected' }}
              </span>
              <template v-if="integration.connected && integration.last_used_at">
                <span class="text-gray-300 dark:text-gray-600">|</span>
                <Clock class="w-3 h-3 text-gray-400 dark:text-gray-500" />
                <span class="text-gray-400 dark:text-gray-500">{{ formatDate(integration.last_used_at) }}</span>
              </template>
            </div>

            <!-- Error -->
            <div v-if="integration.last_error" class="flex items-start gap-1.5 text-xs text-red-500 dark:text-red-400 bg-red-50 dark:bg-red-900/20 rounded-lg p-2">
              <AlertCircle class="w-3.5 h-3.5 mt-0.5 flex-shrink-0" />
              <span class="line-clamp-2">{{ integration.last_error }}</span>
            </div>

            <!-- Disconnect confirmation -->
            <div v-if="disconnectConfirmName === integration.name" class="flex items-center gap-2 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 rounded-lg p-2 text-xs">
              <span class="flex-1 text-red-700 dark:text-red-300">Disconnect {{ integration.display_name }}?</span>
              <button
                type="button"
                class="px-2 py-1 rounded bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-200 hover:bg-gray-300 dark:hover:bg-gray-600 transition"
                @click="disconnectConfirmName = null"
              >No</button>
              <button
                type="button"
                :disabled="loadingNames.has(integration.name)"
                class="inline-flex items-center gap-1 px-2 py-1 rounded bg-red-600 hover:bg-red-700 disabled:opacity-60 text-white transition"
                @click="handleDisconnect(integration)"
              >
                <Loader2 v-if="loadingNames.has(integration.name)" class="w-3 h-3 animate-spin" />
                Yes
              </button>
            </div>

            <!-- Action buttons -->
            <div v-else class="flex items-center gap-2 mt-auto">
              <button
                v-if="!integration.connected"
                type="button"
                :disabled="loadingNames.has(integration.name)"
                class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white transition"
                @click="handleConnect(integration)"
              >
                <Loader2 v-if="loadingNames.has(integration.name)" class="w-3 h-3 animate-spin" />
                <Link2 v-else class="w-3 h-3" />
                Connect
              </button>
              <button
                v-else
                type="button"
                :disabled="loadingNames.has(integration.name)"
                class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 disabled:opacity-60 text-gray-700 dark:text-gray-200 transition"
                @click="disconnectConfirmName = integration.name"
              >
                <Link2Off class="w-3 h-3" />
                Disconnect
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- API Key Modal -->
    <Teleport to="body">
      <div
        v-if="apiKeyModal.open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
        @click.self="apiKeyModal.open = false"
      >
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 w-full max-w-md p-6 space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
              <Key class="w-4 h-4 text-gray-400 dark:text-gray-500" />
              Connect {{ apiKeyModal.integration?.display_name }}
            </h3>
            <button
              type="button"
              class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition"
              @click="apiKeyModal.open = false"
            >
              <X class="w-5 h-5" />
            </button>
          </div>

          <p class="text-sm text-gray-500 dark:text-gray-400">
            Enter your API key for {{ apiKeyModal.integration?.display_name }}. This is stored encrypted and never shared.
          </p>

          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">API Key</label>
            <input
              v-model="apiKeyModal.key"
              type="password"
              placeholder="Paste your API key here"
              class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 font-mono"
              :class="apiKeyModal.error ? 'border-red-400 dark:border-red-500' : ''"
            />
            <p v-if="apiKeyModal.error" class="mt-1 text-xs text-red-500 flex items-center gap-1">
              <AlertCircle class="w-3 h-3" /> {{ apiKeyModal.error }}
            </p>
          </div>

          <div class="flex gap-2 pt-1">
            <button
              type="button"
              :disabled="apiKeyModal.testing"
              class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-60 transition"
              @click="testApiKey"
            >
              <Loader2 v-if="apiKeyModal.testing" class="w-3.5 h-3.5 animate-spin" />
              <TestTube2 v-else class="w-3.5 h-3.5" />
              Test
            </button>
            <div class="flex-1" />
            <button
              type="button"
              class="px-4 py-2 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition"
              @click="apiKeyModal.open = false"
            >Cancel</button>
            <button
              type="button"
              :disabled="apiKeyModal.saving"
              class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-medium bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white transition"
              @click="saveApiKey"
            >
              <Loader2 v-if="apiKeyModal.saving" class="w-3.5 h-3.5 animate-spin" />
              <CheckCircle2 v-else class="w-3.5 h-3.5" />
              {{ apiKeyModal.saving ? 'Connecting...' : 'Connect' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
