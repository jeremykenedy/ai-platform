<script setup>
  import { ref, computed } from 'vue'
  import { useRoute, useRouter } from 'vue-router'
  import { Eye, EyeOff, UserPlus, AlertCircle, ShieldAlert } from 'lucide-vue-next'
  import AuthLayout from '@/components/layout/AuthLayout.vue'
  import { useAuthStore } from '@/stores/auth'

  const route = useRoute()
  const router = useRouter()
  const authStore = useAuthStore()

  const token = computed(() => route.params.token ?? '')

  const name = ref('')
  const email = ref('')
  const password = ref('')
  const confirmPassword = ref('')
  const showPassword = ref(false)
  const showConfirm = ref(false)
  const isLoading = ref(false)
  const error = ref('')

  const nameError = ref('')
  const emailError = ref('')
  const passwordError = ref('')
  const confirmError = ref('')

  function validateName(value) {
    if (!value.trim()) return 'Name is required'
    return ''
  }

  function validateEmail(value) {
    if (!value) return 'Email is required'
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Enter a valid email address'
    return ''
  }

  function validatePassword(value) {
    if (!value) return 'Password is required'
    if (value.length < 8) return 'Password must be at least 8 characters'
    return ''
  }

  function validateConfirm(value) {
    if (!value) return 'Please confirm your password'
    if (value !== password.value) return 'Passwords do not match'
    return ''
  }

  function validate() {
    nameError.value = validateName(name.value)
    emailError.value = validateEmail(email.value)
    passwordError.value = validatePassword(password.value)
    confirmError.value = validateConfirm(confirmPassword.value)
    return !nameError.value && !emailError.value && !passwordError.value && !confirmError.value
  }

  async function handleSubmit() {
    error.value = ''
    if (!validate()) return

    isLoading.value = true
    try {
      await authStore.register({
        name: name.value.trim(),
        email: email.value.trim(),
        password: password.value,
        password_confirmation: confirmPassword.value,
        invite_token: token.value,
      })
      router.push('/c/new')
    } catch (err) {
      const status = err?.response?.status
      const data = err?.response?.data
      if (status === 422) {
        const errors = data?.errors ?? {}
        if (errors.email) emailError.value = errors.email[0]
        if (errors.name) nameError.value = errors.name[0]
        if (errors.password) passwordError.value = errors.password[0]
        if (errors.invite_token) error.value = 'This invite link is invalid or has expired.'
        else if (!Object.keys(errors).length) error.value = data?.message ?? 'Validation failed.'
      } else if (status === 403) {
        error.value = 'This invite link is invalid or has expired.'
      } else {
        error.value = data?.message ?? 'Registration failed. Please try again.'
      }
    } finally {
      isLoading.value = false
    }
  }
</script>

<template>
  <AuthLayout title="Create your account" subtitle="Complete your registration below">
    <!-- No token: invite-only message -->
    <div v-if="!token" class="space-y-4">
      <div
        class="flex flex-col items-center gap-3 rounded-xl border border-amber-200 bg-amber-50 px-5 py-6 text-center dark:border-amber-900/50 dark:bg-amber-950/30"
      >
        <ShieldAlert class="h-8 w-8 text-amber-500 dark:text-amber-400" />
        <div>
          <p class="font-semibold text-amber-800 dark:text-amber-300">
            Registration is invite-only
          </p>
          <p class="mt-1 text-sm text-amber-700 dark:text-amber-400">
            You need a valid invite link to create an account. Please contact an administrator.
          </p>
        </div>
      </div>
      <div class="text-center text-sm text-gray-500 dark:text-gray-400">
        Already have an account?
        <router-link
          to="/login"
          class="font-medium text-blue-600 hover:text-blue-700 hover:underline dark:text-blue-400 dark:hover:text-blue-300"
        >
          Sign in
        </router-link>
      </div>
    </div>

    <!-- Registration form -->
    <form v-else class="space-y-4" @submit.prevent="handleSubmit">
      <!-- Invite token badge -->
      <div
        class="flex items-center gap-2 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-xs text-green-700 dark:border-green-900/50 dark:bg-green-950/30 dark:text-green-400"
      >
        <span class="h-1.5 w-1.5 rounded-full bg-green-500 dark:bg-green-400" />
        Valid invite link detected
      </div>

      <!-- Global error -->
      <div
        v-if="error"
        class="flex items-start gap-2.5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-400"
      >
        <AlertCircle class="mt-0.5 h-4 w-4 shrink-0" />
        <span>{{ error }}</span>
      </div>

      <!-- Name field -->
      <div class="space-y-1.5">
        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
          Full name
        </label>
        <input
          id="name"
          v-model="name"
          type="text"
          autocomplete="name"
          placeholder="Jane Smith"
          :disabled="isLoading"
          class="w-full rounded-lg border px-3 py-2.5 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 disabled:cursor-not-allowed disabled:opacity-60 dark:text-gray-100 dark:placeholder:text-gray-500"
          :class="
            nameError
              ? 'border-red-400 bg-red-50 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-red-700 dark:bg-red-950/20'
              : 'border-gray-300 bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:focus:border-blue-400'
          "
          @blur="nameError = validateName(name)"
        />
        <p v-if="nameError" class="text-xs text-red-600 dark:text-red-400">{{ nameError }}</p>
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
          class="w-full rounded-lg border px-3 py-2.5 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 disabled:cursor-not-allowed disabled:opacity-60 dark:text-gray-100 dark:placeholder:text-gray-500"
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

      <!-- Confirm password -->
      <div class="space-y-1.5">
        <label
          for="confirm-password"
          class="block text-sm font-medium text-gray-700 dark:text-gray-300"
        >
          Confirm password
        </label>
        <div class="relative">
          <input
            id="confirm-password"
            v-model="confirmPassword"
            :type="showConfirm ? 'text' : 'password'"
            autocomplete="new-password"
            placeholder="Re-enter your password"
            :disabled="isLoading"
            class="w-full rounded-lg border px-3 py-2.5 pr-10 text-sm text-gray-900 outline-none transition-colors placeholder:text-gray-400 disabled:cursor-not-allowed disabled:opacity-60 dark:text-gray-100 dark:placeholder:text-gray-500"
            :class="
              confirmError
                ? 'border-red-400 bg-red-50 focus:border-red-500 focus:ring-2 focus:ring-red-500/20 dark:border-red-700 dark:bg-red-950/20'
                : 'border-gray-300 bg-white focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 dark:border-gray-700 dark:bg-gray-800 dark:focus:border-blue-400'
            "
            @blur="confirmError = validateConfirm(confirmPassword)"
          />
          <button
            type="button"
            tabindex="-1"
            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300"
            @click="showConfirm = !showConfirm"
          >
            <EyeOff v-if="showConfirm" class="h-4 w-4" />
            <Eye v-else class="h-4 w-4" />
          </button>
        </div>
        <p v-if="confirmError" class="text-xs text-red-600 dark:text-red-400">{{ confirmError }}</p>
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
        <UserPlus v-else class="h-4 w-4" />
        {{ isLoading ? 'Creating account...' : 'Create account' }}
      </button>

      <!-- Login link -->
      <p class="text-center text-sm text-gray-500 dark:text-gray-400">
        Already have an account?
        <router-link
          to="/login"
          class="font-medium text-blue-600 hover:text-blue-700 hover:underline dark:text-blue-400 dark:hover:text-blue-300"
        >
          Sign in
        </router-link>
      </p>
    </form>
  </AuthLayout>
</template>
