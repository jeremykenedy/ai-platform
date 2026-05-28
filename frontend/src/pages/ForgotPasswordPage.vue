<script setup>
  import { ref } from 'vue'
  import { ArrowLeft, Mail, AlertCircle, CheckCircle } from 'lucide-vue-next'
  import AuthLayout from '@/components/layout/AuthLayout.vue'
  import api from '@/services/api'

  const email = ref('')
  const isLoading = ref(false)
  const error = ref('')
  const success = ref(false)

  async function handleSubmit() {
    error.value = ''
    if (!email.value) {
      error.value = 'Email is required.'
      return
    }

    isLoading.value = true
    try {
      await api.post('/auth/password/forgot', { email: email.value.trim() })
      success.value = true
    } catch (err) {
      const status = err?.response?.status
      if (status === 429) {
        error.value = 'Too many attempts. Please wait a moment and try again.'
      } else {
        error.value = err?.response?.data?.message ?? 'Something went wrong. Please try again.'
      }
    } finally {
      isLoading.value = false
    }
  }
</script>

<template>
  <AuthLayout title="Forgot password" subtitle="Enter your email and we'll send you a reset link">
    <!-- Success state -->
    <div v-if="success" class="space-y-4">
      <div
        class="flex items-start gap-2.5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/50 dark:bg-green-950/40 dark:text-green-400"
      >
        <CheckCircle class="mt-0.5 h-4 w-4 shrink-0" />
        <span>If an account exists with that email, we've sent a password reset link.</span>
      </div>
      <router-link
        to="/login"
        class="flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
      >
        <ArrowLeft class="h-3.5 w-3.5" />
        Back to sign in
      </router-link>
    </div>

    <!-- Form state -->
    <form v-else class="space-y-4" @submit.prevent="handleSubmit">
      <div
        v-if="error"
        class="flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-400"
      >
        <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
        <span>{{ error }}</span>
      </div>

      <div class="space-y-1.5">
        <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
          Email address
        </label>
        <input
          id="email"
          v-model="email"
          type="email"
          autocomplete="email"
          placeholder="you@example.com"
          :disabled="isLoading"
          class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-blue-400"
        />
      </div>

      <button
        type="submit"
        :disabled="isLoading"
        class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500 dark:focus:ring-offset-gray-900"
      >
        <span
          v-if="isLoading"
          class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
        />
        <Mail v-else class="h-4 w-4" />
        {{ isLoading ? 'Sending...' : 'Send reset link' }}
      </button>

      <router-link
        to="/login"
        class="flex items-center gap-1.5 text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
      >
        <ArrowLeft class="h-3.5 w-3.5" />
        Back to sign in
      </router-link>
    </form>
  </AuthLayout>
</template>
