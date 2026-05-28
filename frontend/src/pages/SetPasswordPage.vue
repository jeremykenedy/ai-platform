<script setup>
  import { ref } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { Eye, EyeOff, Lock, AlertCircle, CheckCircle } from 'lucide-vue-next'
  import AuthLayout from '@/components/layout/AuthLayout.vue'
  import api from '@/services/api'

  const route = useRoute()
  const router = useRouter()

  const password = ref('')
  const passwordConfirmation = ref('')
  const showPassword = ref(false)
  const isLoading = ref(false)
  const error = ref('')
  const success = ref(false)

  const token = route.params.token ?? ''

  async function handleSubmit() {
    error.value = ''

    if (!password.value || password.value.length < 8) {
      error.value = 'Password must be at least 8 characters.'
      return
    }
    if (password.value !== passwordConfirmation.value) {
      error.value = 'Passwords do not match.'
      return
    }

    isLoading.value = true
    try {
      await api.post('/auth/password/set', {
        token,
        password: password.value,
        password_confirmation: passwordConfirmation.value,
      })
      success.value = true
      setTimeout(() => router.push('/c/new'), 2000)
    } catch (err) {
      error.value = err?.response?.data?.message ?? 'Invalid or expired token. Contact your administrator.'
    } finally {
      isLoading.value = false
    }
  }
</script>

<template>
  <AuthLayout title="Set your password" subtitle="Create a password for your account">
    <div v-if="success" class="space-y-4">
      <div
        class="flex items-start gap-2.5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/50 dark:bg-green-950/40 dark:text-green-400"
      >
        <CheckCircle class="mt-0.5 h-4 w-4 shrink-0" />
        <span>Password set successfully. Redirecting...</span>
      </div>
    </div>

    <div v-else-if="!token" class="space-y-4">
      <div
        class="flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-400"
      >
        <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
        <span>Invalid link. Please use the link from your welcome email.</span>
      </div>
      <router-link
        to="/login"
        class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
      >
        Go to login
      </router-link>
    </div>

    <form v-else class="space-y-4" @submit.prevent="handleSubmit">
      <div
        v-if="error"
        class="flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-400"
      >
        <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
        <span>{{ error }}</span>
      </div>

      <div class="space-y-1.5">
        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
          Password
        </label>
        <div class="relative">
          <input
            id="password"
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="new-password"
            placeholder="At least 8 characters"
            :disabled="isLoading"
            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 pr-10 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-500 dark:focus:border-blue-400"
          />
          <button
            type="button"
            tabindex="-1"
            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
            @click="showPassword = !showPassword"
          >
            <EyeOff v-if="showPassword" class="h-4 w-4" />
            <Eye v-else class="h-4 w-4" />
          </button>
        </div>
      </div>

      <div class="space-y-1.5">
        <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
          Confirm password
        </label>
        <input
          id="password_confirmation"
          v-model="passwordConfirmation"
          type="password"
          autocomplete="new-password"
          placeholder="Confirm your password"
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
        <Lock v-else class="h-4 w-4" />
        {{ isLoading ? 'Setting password...' : 'Set password' }}
      </button>
    </form>
  </AuthLayout>
</template>
