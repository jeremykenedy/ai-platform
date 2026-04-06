<script setup>
  import { ref, reactive, computed, onMounted } from 'vue'
  import {
    User,
    Mail,
    Globe,
    Lock,
    Camera,
    CheckCircle,
    AlertCircle,
    Loader2,
  } from 'lucide-vue-next'
  import { useAuthStore } from '@/stores/auth'
  import { useSettingsStore } from '@/stores/settings'
  import { useUiStore } from '@/stores/ui'

  const authStore = useAuthStore()
  const settingsStore = useSettingsStore()
  const uiStore = useUiStore()

  const TIMEZONES = [
    'UTC',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'America/Anchorage',
    'America/Honolulu',
    'America/Toronto',
    'America/Vancouver',
    'America/Sao_Paulo',
    'America/Argentina/Buenos_Aires',
    'Europe/London',
    'Europe/Paris',
    'Europe/Berlin',
    'Europe/Madrid',
    'Europe/Rome',
    'Europe/Amsterdam',
    'Europe/Stockholm',
    'Europe/Helsinki',
    'Europe/Moscow',
    'Asia/Dubai',
    'Asia/Kolkata',
    'Asia/Dhaka',
    'Asia/Bangkok',
    'Asia/Jakarta',
    'Asia/Singapore',
    'Asia/Hong_Kong',
    'Asia/Shanghai',
    'Asia/Tokyo',
    'Asia/Seoul',
    'Australia/Sydney',
    'Australia/Melbourne',
    'Australia/Perth',
    'Pacific/Auckland',
  ]

  const LOCALES = [
    { value: 'en', label: 'English' },
    { value: 'es', label: 'Spanish' },
    { value: 'fr', label: 'French' },
    { value: 'de', label: 'German' },
    { value: 'it', label: 'Italian' },
    { value: 'pt', label: 'Portuguese' },
    { value: 'ru', label: 'Russian' },
    { value: 'ja', label: 'Japanese' },
    { value: 'ko', label: 'Korean' },
    { value: 'zh', label: 'Chinese (Simplified)' },
    { value: 'ar', label: 'Arabic' },
    { value: 'nl', label: 'Dutch' },
    { value: 'pl', label: 'Polish' },
    { value: 'sv', label: 'Swedish' },
    { value: 'tr', label: 'Turkish' },
  ]

  const profile = reactive({
    name: '',
    email: '',
    avatar: null,
    avatarPreview: null,
  })

  const localization = reactive({
    timezone: 'UTC',
    locale: 'en',
  })

  const security = reactive({
    current_password: '',
    new_password: '',
    confirm_password: '',
  })

  const profileErrors = reactive({})
  const localizationErrors = reactive({})
  const securityErrors = reactive({})

  const savingProfile = ref(false)
  const savingLocalization = ref(false)
  const savingPassword = ref(false)

  const avatarInput = ref(null)

  onMounted(async () => {
    await Promise.all([authStore.fetchUser(), settingsStore.fetch()])
    if (authStore.user) {
      profile.name = authStore.user.name ?? ''
      profile.email = authStore.user.email ?? ''
      profile.avatarPreview = authStore.user.avatar ?? null
    }
    if (settingsStore.settings) {
      localization.timezone = settingsStore.settings.timezone ?? 'UTC'
      localization.locale = settingsStore.settings.locale ?? 'en'
    }
  })

  function validateProfile() {
    Object.keys(profileErrors).forEach((k) => delete profileErrors[k])
    if (!profile.name.trim()) profileErrors.name = 'Name is required.'
    if (!profile.email.trim()) profileErrors.email = 'Email is required.'
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(profile.email))
      profileErrors.email = 'Enter a valid email.'
    return Object.keys(profileErrors).length === 0
  }

  function validateLocalization() {
    Object.keys(localizationErrors).forEach((k) => delete localizationErrors[k])
    if (!localization.timezone) localizationErrors.timezone = 'Timezone is required.'
    if (!localization.locale) localizationErrors.locale = 'Locale is required.'
    return Object.keys(localizationErrors).length === 0
  }

  function validateSecurity() {
    Object.keys(securityErrors).forEach((k) => delete securityErrors[k])
    if (!security.current_password)
      securityErrors.current_password = 'Current password is required.'
    if (!security.new_password) securityErrors.new_password = 'New password is required.'
    else if (security.new_password.length < 8)
      securityErrors.new_password = 'Password must be at least 8 characters.'
    if (!security.confirm_password)
      securityErrors.confirm_password = 'Please confirm your password.'
    else if (security.new_password !== security.confirm_password)
      securityErrors.confirm_password = 'Passwords do not match.'
    return Object.keys(securityErrors).length === 0
  }

  function onAvatarChange(e) {
    const file = e.target.files[0]
    if (!file) return
    profile.avatar = file
    profile.avatarPreview = URL.createObjectURL(file)
  }

  function triggerAvatarUpload() {
    avatarInput.value?.click()
  }

  async function saveProfile() {
    if (!validateProfile()) return
    savingProfile.value = true
    try {
      const data = { name: profile.name, email: profile.email }
      if (profile.avatar) {
        const formData = new FormData()
        formData.append('avatar', profile.avatar)
        formData.append('name', profile.name)
        formData.append('email', profile.email)
        await authStore.updateProfile(formData)
      } else {
        await authStore.updateProfile(data)
      }
      uiStore.addToast({ type: 'success', message: 'Profile saved successfully.' })
    } catch (err) {
      const msg = err?.response?.data?.message ?? 'Failed to save profile.'
      uiStore.addToast({ type: 'error', message: msg })
    } finally {
      savingProfile.value = false
    }
  }

  async function saveLocalization() {
    if (!validateLocalization()) return
    savingLocalization.value = true
    try {
      await settingsStore.update({ timezone: localization.timezone, locale: localization.locale })
      uiStore.addToast({ type: 'success', message: 'Localization saved.' })
    } catch (err) {
      const msg = err?.response?.data?.message ?? 'Failed to save localization.'
      uiStore.addToast({ type: 'error', message: msg })
    } finally {
      savingLocalization.value = false
    }
  }

  async function savePassword() {
    if (!validateSecurity()) return
    savingPassword.value = true
    try {
      await authStore.updateProfile({
        current_password: security.current_password,
        password: security.new_password,
        password_confirmation: security.confirm_password,
      })
      security.current_password = ''
      security.new_password = ''
      security.confirm_password = ''
      uiStore.addToast({ type: 'success', message: 'Password updated.' })
    } catch (err) {
      const msg = err?.response?.data?.message ?? 'Failed to update password.'
      uiStore.addToast({ type: 'error', message: msg })
    } finally {
      savingPassword.value = false
    }
  }

  const userInitials = computed(() => {
    const n = profile.name || authStore.user?.name || ''
    return n
      .split(' ')
      .map((w) => w[0])
      .join('')
      .toUpperCase()
      .slice(0, 2)
  })
</script>

<template>
  <div class="max-w-2xl mx-auto p-6 space-y-8">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">General Settings</h1>

    <!-- Profile Section -->
    <section
      class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5"
    >
      <div class="flex items-center gap-2 mb-1">
        <User class="w-5 h-5 text-gray-500 dark:text-gray-400" />
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Profile</h2>
      </div>

      <!-- Avatar -->
      <div class="flex items-center gap-5">
        <button
          type="button"
          class="relative w-20 h-20 rounded-full overflow-hidden bg-indigo-100 dark:bg-indigo-900 flex items-center justify-center group cursor-pointer focus:outline-none focus:ring-2 focus:ring-indigo-500"
          @click="triggerAvatarUpload"
        >
          <img
            v-if="profile.avatarPreview"
            :src="profile.avatarPreview"
            alt="Avatar"
            class="w-full h-full object-cover"
          />
          <span v-else class="text-xl font-bold text-indigo-600 dark:text-indigo-300 select-none">{{
            userInitials || '?'
          }}</span>
          <span
            class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
          >
            <Camera class="w-6 h-6 text-white" />
          </span>
        </button>
        <div>
          <button
            type="button"
            class="text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:underline"
            @click="triggerAvatarUpload"
          >
            Upload photo
          </button>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">JPG, PNG or GIF, max 2 MB</p>
        </div>
        <input
          ref="avatarInput"
          type="file"
          accept="image/*"
          class="hidden"
          @change="onAvatarChange"
        />
      </div>

      <!-- Name -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
          >Full Name</label
        >
        <input
          v-model="profile.name"
          type="text"
          class="w-full rounded-lg border px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
          :class="
            profileErrors.name
              ? 'border-red-400 dark:border-red-500'
              : 'border-gray-300 dark:border-gray-600'
          "
          placeholder="Your full name"
        />
        <p v-if="profileErrors.name" class="mt-1 text-xs text-red-500 flex items-center gap-1">
          <AlertCircle class="w-3 h-3" /> {{ profileErrors.name }}
        </p>
      </div>

      <!-- Email -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
          >Email Address</label
        >
        <div class="relative">
          <Mail
            class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 dark:text-gray-500"
          />
          <input
            v-model="profile.email"
            type="email"
            class="w-full rounded-lg border pl-9 pr-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
            :class="
              profileErrors.email
                ? 'border-red-400 dark:border-red-500'
                : 'border-gray-300 dark:border-gray-600'
            "
            placeholder="you@example.com"
          />
        </div>
        <p v-if="profileErrors.email" class="mt-1 text-xs text-red-500 flex items-center gap-1">
          <AlertCircle class="w-3 h-3" /> {{ profileErrors.email }}
        </p>
      </div>

      <div class="flex justify-end">
        <button
          type="button"
          :disabled="savingProfile"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-medium transition"
          @click="saveProfile"
        >
          <Loader2 v-if="savingProfile" class="w-4 h-4 animate-spin" />
          <CheckCircle v-else class="w-4 h-4" />
          {{ savingProfile ? 'Saving...' : 'Save Profile' }}
        </button>
      </div>
    </section>

    <!-- Localization Section -->
    <section
      class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5"
    >
      <div class="flex items-center gap-2 mb-1">
        <Globe class="w-5 h-5 text-gray-500 dark:text-gray-400" />
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Localization</h2>
      </div>

      <!-- Timezone -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
          >Timezone</label
        >
        <select
          v-model="localization.timezone"
          class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
          :class="localizationErrors.timezone ? 'border-red-400 dark:border-red-500' : ''"
        >
          <option v-for="tz in TIMEZONES" :key="tz" :value="tz">{{ tz }}</option>
        </select>
        <p
          v-if="localizationErrors.timezone"
          class="mt-1 text-xs text-red-500 flex items-center gap-1"
        >
          <AlertCircle class="w-3 h-3" /> {{ localizationErrors.timezone }}
        </p>
      </div>

      <!-- Locale -->
      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
          >Language</label
        >
        <select
          v-model="localization.locale"
          class="w-full rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
          :class="localizationErrors.locale ? 'border-red-400 dark:border-red-500' : ''"
        >
          <option v-for="loc in LOCALES" :key="loc.value" :value="loc.value">
            {{ loc.label }}
          </option>
        </select>
        <p
          v-if="localizationErrors.locale"
          class="mt-1 text-xs text-red-500 flex items-center gap-1"
        >
          <AlertCircle class="w-3 h-3" /> {{ localizationErrors.locale }}
        </p>
      </div>

      <div class="flex justify-end">
        <button
          type="button"
          :disabled="savingLocalization"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-medium transition"
          @click="saveLocalization"
        >
          <Loader2 v-if="savingLocalization" class="w-4 h-4 animate-spin" />
          <CheckCircle v-else class="w-4 h-4" />
          {{ savingLocalization ? 'Saving...' : 'Save Localization' }}
        </button>
      </div>
    </section>

    <!-- Security Section -->
    <section
      class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-5"
    >
      <div class="flex items-center gap-2 mb-1">
        <Lock class="w-5 h-5 text-gray-500 dark:text-gray-400" />
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Security</h2>
      </div>
      <p class="text-sm text-gray-500 dark:text-gray-400">
        Change your account password. You will need to enter your current password to confirm.
      </p>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
          >Current Password</label
        >
        <input
          v-model="security.current_password"
          type="password"
          autocomplete="current-password"
          class="w-full rounded-lg border px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
          :class="
            securityErrors.current_password
              ? 'border-red-400 dark:border-red-500'
              : 'border-gray-300 dark:border-gray-600'
          "
          placeholder="Enter current password"
        />
        <p
          v-if="securityErrors.current_password"
          class="mt-1 text-xs text-red-500 flex items-center gap-1"
        >
          <AlertCircle class="w-3 h-3" /> {{ securityErrors.current_password }}
        </p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
          >New Password</label
        >
        <input
          v-model="security.new_password"
          type="password"
          autocomplete="new-password"
          class="w-full rounded-lg border px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
          :class="
            securityErrors.new_password
              ? 'border-red-400 dark:border-red-500'
              : 'border-gray-300 dark:border-gray-600'
          "
          placeholder="At least 8 characters"
        />
        <p
          v-if="securityErrors.new_password"
          class="mt-1 text-xs text-red-500 flex items-center gap-1"
        >
          <AlertCircle class="w-3 h-3" /> {{ securityErrors.new_password }}
        </p>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
          >Confirm New Password</label
        >
        <input
          v-model="security.confirm_password"
          type="password"
          autocomplete="new-password"
          class="w-full rounded-lg border px-3 py-2 text-sm bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition"
          :class="
            securityErrors.confirm_password
              ? 'border-red-400 dark:border-red-500'
              : 'border-gray-300 dark:border-gray-600'
          "
          placeholder="Repeat new password"
        />
        <p
          v-if="securityErrors.confirm_password"
          class="mt-1 text-xs text-red-500 flex items-center gap-1"
        >
          <AlertCircle class="w-3 h-3" /> {{ securityErrors.confirm_password }}
        </p>
      </div>

      <div class="flex justify-end">
        <button
          type="button"
          :disabled="savingPassword"
          class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm font-medium transition"
          @click="savePassword"
        >
          <Loader2 v-if="savingPassword" class="w-4 h-4 animate-spin" />
          <CheckCircle v-else class="w-4 h-4" />
          {{ savingPassword ? 'Updating...' : 'Update Password' }}
        </button>
      </div>
    </section>
  </div>
</template>
