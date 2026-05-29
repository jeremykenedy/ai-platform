<script setup>
  import { ref, onMounted, onUnmounted } from 'vue'
  import Echo from 'laravel-echo'
  import Pusher from 'pusher-js'

  // Self-diagnostic page. Runs entirely in this browser and shows
  // pass/fail for each layer of the stack. Hit /diagnose to use.

  const checks = ref([])
  const summary = ref('Running checks…')

  function add(name, status, detail = '') {
    checks.value.push({ name, status, detail, ts: new Date().toLocaleTimeString() })
  }

  function setStatus(name, status, detail = '') {
    const c = checks.value.find((x) => x.name === name)
    if (c) {
      c.status = status
      c.detail = detail
    } else {
      add(name, status, detail)
    }
  }

  function computeBundleId() {
    try {
      const scripts = Array.from(document.querySelectorAll('script[src]'))
      const idx = scripts.find((s) => /\/assets\/index\.[A-Za-z0-9_-]+\.js$/.test(s.src))
      return idx ? idx.src.split('/').pop() : '(not found)'
    } catch {
      return '(error)'
    }
  }

  const bundleId = computeBundleId()
  let echoInstance = null
  let channel = null

  async function runChecks() {
    add('Bundle file loaded', 'pass', bundleId)
    add('Page origin', 'pass', window.location.origin)
    add('User agent', 'info', navigator.userAgent.slice(0, 100))
    add('Service workers installed', 'pending', '')

    // Check SW
    if ('serviceWorker' in navigator) {
      const regs = await navigator.serviceWorker.getRegistrations()
      setStatus('Service workers installed', regs.length === 0 ? 'pass' : 'warn',
        regs.length === 0 ? 'none (good)' : `${regs.length} (may cause stale bundle)`)
    }

    // Health endpoint
    add('GET /api/health', 'pending', '')
    try {
      const r = await fetch('/api/health', { credentials: 'include' })
      setStatus('GET /api/health', r.ok ? 'pass' : 'fail', `${r.status} ${r.statusText}`)
    } catch (e) {
      setStatus('GET /api/health', 'fail', e.message)
    }

    // CSRF cookie
    add('GET /sanctum/csrf-cookie', 'pending', '')
    try {
      const r = await fetch('/sanctum/csrf-cookie', { credentials: 'include' })
      setStatus('GET /sanctum/csrf-cookie', r.ok ? 'pass' : 'fail', `${r.status}`)
    } catch (e) {
      setStatus('GET /sanctum/csrf-cookie', 'fail', e.message)
    }

    // Auth user (will be 401 if not logged in — that's OK)
    add('GET /api/v1/auth/user', 'pending', '')
    try {
      const r = await fetch('/api/v1/auth/user', { credentials: 'include' })
      const j = r.ok ? await r.json() : null
      setStatus('GET /api/v1/auth/user', r.ok ? 'pass' : 'warn',
        r.ok ? `${j?.data?.email ?? 'ok'}` : `${r.status} (not logged in is OK)`)
    } catch (e) {
      setStatus('GET /api/v1/auth/user', 'fail', e.message)
    }

    // WSS connection
    add('WSS connect to /app/...', 'pending', '')
    try {
      window.Pusher = Pusher
      echoInstance = new Echo({
        broadcaster: 'reverb',
        key: '4a4429e2c0a20697bd5739338f5aaa98',
        wsHost: window.location.hostname,
        wsPort: Number(window.location.port) || 443,
        wssPort: Number(window.location.port) || 443,
        forceTLS: window.location.protocol === 'https:',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
      })

      const pusher = echoInstance.connector.pusher
      pusher.connection.bind('connected', () => {
        setStatus('WSS connect to /app/...', 'pass', `socket_id=${pusher.connection.socket_id}`)
        runPostWssChecks()
      })
      pusher.connection.bind('error', (err) => {
        setStatus('WSS connect to /app/...', 'fail',
          `code=${err?.error?.data?.code ?? err?.type ?? 'unknown'}  msg=${err?.error?.data?.message ?? err?.message ?? JSON.stringify(err).slice(0,150)}`)
      })
      pusher.connection.bind('disconnected', () => {
        setStatus('WSS connect to /app/...', 'fail', 'disconnected')
      })

      // 10s timeout
      setTimeout(() => {
        const c = checks.value.find((x) => x.name === 'WSS connect to /app/...')
        if (c?.status === 'pending') setStatus('WSS connect to /app/...', 'fail', 'timeout (10s)')
      }, 10000)
    } catch (e) {
      setStatus('WSS connect to /app/...', 'fail', e.message)
    }

    summary.value = 'Done — read each row below.'
  }

  function runPostWssChecks() {
    // Quick raw fetch to /app/... endpoint — should 426 Upgrade Required
    // for a plain GET (proof the path is reachable and not 502/500)
    add('GET /app/{key} (plain HTTP)', 'pending', '')
    fetch('/app/4a4429e2c0a20697bd5739338f5aaa98?protocol=7', { credentials: 'include' })
      .then((r) => setStatus('GET /app/{key} (plain HTTP)',
        r.status >= 200 && r.status < 500 ? 'pass' : 'fail', `${r.status}`))
      .catch((e) => setStatus('GET /app/{key} (plain HTTP)', 'fail', e.message))
  }

  onMounted(runChecks)

  onUnmounted(() => {
    if (channel && echoInstance) {
      try { echoInstance.leave('diagnose') } catch {}
    }
    if (echoInstance) {
      try { echoInstance.disconnect() } catch {}
    }
  })
</script>

<template>
  <div class="mx-auto max-w-3xl space-y-4 p-6 text-foreground dark:text-foreground">
    <h1 class="text-2xl font-bold">AI Platform — Self-Diagnose</h1>
    <p class="text-sm text-muted-foreground dark:text-muted-foreground">{{ summary }}</p>

    <table class="w-full overflow-hidden rounded-lg border border-border dark:border-border">
      <thead class="bg-muted dark:bg-muted">
        <tr>
          <th class="px-3 py-2 text-left text-xs uppercase tracking-wide">Check</th>
          <th class="px-3 py-2 text-left text-xs uppercase tracking-wide">Status</th>
          <th class="px-3 py-2 text-left text-xs uppercase tracking-wide">Detail</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="c in checks" :key="c.name" class="border-t border-border dark:border-border">
          <td class="px-3 py-2 text-sm">{{ c.name }}</td>
          <td class="px-3 py-2">
            <span
              class="rounded px-2 py-0.5 text-xs font-medium"
              :class="{
                'bg-green-100 text-green-800 dark:bg-green-950/40 dark:text-green-300': c.status === 'pass',
                'bg-yellow-100 text-yellow-800 dark:bg-yellow-950/40 dark:text-yellow-300': c.status === 'warn',
                'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300': c.status === 'fail',
                'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300': c.status === 'pending',
                'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300': c.status === 'info',
              }"
            >
              {{ c.status.toUpperCase() }}
            </span>
          </td>
          <td class="px-3 py-2 text-xs text-muted-foreground dark:text-muted-foreground">{{ c.detail }}</td>
        </tr>
      </tbody>
    </table>

    <div class="rounded-lg border border-border bg-muted/30 p-4 text-sm dark:border-border dark:bg-muted/20">
      <p class="font-medium">How to read this:</p>
      <ul class="ml-4 mt-2 list-disc space-y-1 text-xs text-muted-foreground dark:text-muted-foreground">
        <li><b>WSS FAIL</b> = WebSocket can't connect. Streaming won't work but poll fallback will (slower).</li>
        <li><b>Service workers WARN</b> = old SW still installed. Unregister via DevTools → Application.</li>
        <li><b>/api/health FAIL</b> = backend unreachable from your browser entirely.</li>
        <li><b>All PASS but chat still broken</b> = bug in the SPA logic; copy this page and message me.</li>
      </ul>
    </div>
  </div>
</template>
