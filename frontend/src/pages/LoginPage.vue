<script setup>
  import { ref } from 'vue'
  import { Eye, EyeOff, LogIn, AlertCircle } from 'lucide-vue-next'
  import AuthLayout from '@/components/layout/AuthLayout.vue'
  import { useAuth } from '@/composables/useAuth'

  const { login } = useAuth()

  const email = ref('')
  const password = ref('')
  const showPassword = ref(false)
  const isLoading = ref(false)
  const error = ref('')

  const emailError = ref('')
  const passwordError = ref('')

  function validateEmail(value) {
    if (!value) return 'Email is required'
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Enter a valid email address'
    return ''
  }

  function validatePassword(value) {
    if (!value) return 'Password is required'
    return ''
  }

  function validate() {
    emailError.value = validateEmail(email.value)
    passwordError.value = validatePassword(password.value)
    return !emailError.value && !passwordError.value
  }

  async function handleSubmit() {
    error.value = ''
    if (!validate()) return

    isLoading.value = true
    try {
      await login(email.value.trim(), password.value)
    } catch (err) {
      const status = err?.response?.status
      if (status === 401 || status === 422) {
        error.value = 'Invalid email or password. Please try again.'
      } else if (status === 429) {
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
  <AuthLayout title="Welcome back" subtitle="Sign in to your account to continue">
    <form class="space-y-4" @submit.prevent="handleSubmit">
      <!-- Global error -->
      <div
        v-if="error"
        class="flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-400"
      >
        <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
        <span>{{ error }}</span>
      </div>

      <!-- Email field -->
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
          class="w-full rounded-lg border px-3 py-2.5 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-gray-800 dark:text-gray-100 dark:placeholder:text-gray-500"
          :class="
            emailError
              ? 'border-red-400 bg-red-50 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-red-700 dark:bg-red-950/20'
              : 'border-gray-300 bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:focus:border-blue-400'
          "
          @blur="emailError = validateEmail(email)"
        />
        <p v-if="emailError" class="text-xs text-red-600 dark:text-red-400">{{ emailError }}</p>
      </div>

      <!-- Password field -->
      <div class="space-y-1.5">
        <div class="flex items-center justify-between">
          <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Password
          </label>
          <router-link
            to="/forgot-password"
            class="text-xs text-blue-600 hover:text-blue-700 hover:underline dark:text-blue-400 dark:hover:text-blue-300"
          >
            Forgot password?
          </router-link>
        </div>
        <div class="relative">
          <input
            id="password"
            v-model="password"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
            placeholder="Enter your password"
            :disabled="isLoading"
            class="w-full rounded-lg border px-3 py-2.5 pr-10 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 disabled:cursor-not-allowed disabled:opacity-60 dark:text-gray-100 dark:placeholder:text-gray-500"
            :class="
              passwordError
                ? 'border-red-400 bg-red-50 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-red-700 dark:bg-red-950/20'
                : 'border-gray-300 bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:focus:border-blue-400'
            "
            @blur="passwordError = validatePassword(password)"
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
        <p v-if="passwordError" class="text-xs text-red-600 dark:text-red-400">
          {{ passwordError }}
        </p>
      </div>

      <!-- Submit button -->
      <button
        type="submit"
        :disabled="isLoading"
        class="flex w-full items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-60 dark:bg-blue-600 dark:hover:bg-blue-500 dark:focus:ring-offset-gray-900"
      >
        <span
          v-if="isLoading"
          class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"
        />
        <LogIn v-else class="h-4 w-4" />
        {{ isLoading ? 'Signing in...' : 'Sign in' }}
      </button>
    </form>
  </AuthLayout>
</template>
