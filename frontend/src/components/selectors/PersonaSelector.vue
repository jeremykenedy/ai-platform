<script setup>
  import { ref, computed, watch, nextTick } from 'vue'
  import { useRouter } from 'vue-router'
  import { Search, Check, UserCircle, X, Plus } from 'lucide-vue-next'
  import { usePersonasStore } from '@/stores/personas'

  const props = defineProps({
    modelValue: {
      type: String,
      default: null,
    },
    show: {
      type: Boolean,
      default: false,
    },
  })

  const emit = defineEmits(['update:modelValue', 'update:show'])

  const router = useRouter()
  const personasStore = usePersonasStore()
  const searchQuery = ref('')
  const searchInput = ref(null)

  watch(
    () => props.show,
    async (val) => {
      if (val) {
        searchQuery.value = ''
        await nextTick()
        searchInput.value?.focus()
      }
    }
  )

  const filteredPersonas = computed(() => {
    const q = searchQuery.value.toLowerCase().trim()
    if (!q) return personasStore.sortedPersonas
    return personasStore.sortedPersonas.filter(
      (p) =>
        p.name.toLowerCase().includes(q) ||
        p.system_prompt?.toLowerCase().includes(q) ||
        p.description?.toLowerCase().includes(q)
    )
  })

  function truncate(text, max = 100) {
    if (!text) return ''
    return text.length <= max ? text : text.slice(0, max) + '...'
  }

  function select(id) {
    emit('update:modelValue', id)
    emit('update:show', false)
  }

  function clearPersona() {
    emit('update:modelValue', null)
    emit('update:show', false)
  }

  function close() {
    emit('update:show', false)
  }

  function goCreatePersona() {
    close()
    router.push('/settings/personas')
  }
</script>

<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition duration-150 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-100 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-start justify-center pt-[10vh]"
        @mousedown.self="close"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/40 dark:bg-black/60" @click="close" />

        <!-- Panel -->
        <div
          class="relative z-10 w-full max-w-lg rounded-xl border border-neutral-200 bg-white shadow-2xl dark:border-neutral-700 dark:bg-neutral-900"
        >
          <!-- Search -->
          <div
            class="flex items-center gap-2 border-b border-neutral-200 px-3 dark:border-neutral-700"
          >
            <Search class="h-4 w-4 shrink-0 text-neutral-400" />
            <input
              ref="searchInput"
              v-model="searchQuery"
              placeholder="Search personas..."
              class="h-11 flex-1 bg-transparent text-sm text-neutral-900 placeholder-neutral-400 outline-none dark:text-neutral-100"
              @keydown.escape="close"
            />
            <button
              class="rounded p-1 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-300"
              @click="close"
            >
              <X class="h-4 w-4" />
            </button>
          </div>

          <!-- List -->
          <div class="max-h-[55vh] overflow-y-auto py-2">
            <!-- No persona option -->
            <button
              class="flex w-full items-center gap-3 px-3 py-2.5 text-left transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-800"
              :class="{ 'bg-neutral-100 dark:bg-neutral-800': modelValue === null }"
              @click="clearPersona"
            >
              <div class="h-4 w-4 shrink-0">
                <Check
                  v-if="modelValue === null"
                  class="h-4 w-4 text-blue-600 dark:text-blue-400"
                />
              </div>
              <UserCircle class="h-5 w-5 shrink-0 text-neutral-400" />
              <div>
                <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                  No Persona
                </div>
                <div class="text-xs text-neutral-400 dark:text-neutral-500">
                  Use default assistant behavior
                </div>
              </div>
            </button>

            <div
              v-if="filteredPersonas.length"
              class="mx-3 my-1 border-t border-neutral-100 dark:border-neutral-800"
            />

            <template v-if="filteredPersonas.length">
              <button
                v-for="persona in filteredPersonas"
                :key="persona.id"
                class="flex w-full items-start gap-3 px-3 py-2.5 text-left transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-800"
                :class="{ 'bg-neutral-100 dark:bg-neutral-800': modelValue === persona.id }"
                @click="select(persona.id)"
              >
                <div class="mt-0.5 h-4 w-4 shrink-0">
                  <Check
                    v-if="modelValue === persona.id"
                    class="h-4 w-4 text-blue-600 dark:text-blue-400"
                  />
                </div>

                <!-- Avatar letter -->
                <div
                  class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-violet-100 text-xs font-semibold text-violet-700 dark:bg-violet-900/40 dark:text-violet-300"
                >
                  {{ persona.name.charAt(0).toUpperCase() }}
                </div>

                <div class="min-w-0 flex-1">
                  <div class="text-sm font-medium text-neutral-900 dark:text-neutral-100">
                    {{ persona.name }}
                  </div>
                  <p
                    v-if="persona.system_prompt || persona.description"
                    class="mt-0.5 text-xs leading-relaxed text-neutral-500 dark:text-neutral-400"
                  >
                    {{ truncate(persona.system_prompt || persona.description) }}
                  </p>
                </div>
              </button>
            </template>

            <div
              v-else-if="searchQuery"
              class="px-3 py-6 text-center text-sm text-neutral-400 dark:text-neutral-500"
            >
              No personas found for "{{ searchQuery }}"
            </div>

            <div
              v-else-if="!personasStore.isLoading && !filteredPersonas.length"
              class="px-3 py-6 text-center text-sm text-neutral-400 dark:text-neutral-500"
            >
              No personas yet
            </div>
          </div>

          <!-- Footer: create button -->
          <div class="border-t border-neutral-200 p-2 dark:border-neutral-700">
            <button
              class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium text-blue-600 transition-colors hover:bg-blue-50 dark:text-blue-400 dark:hover:bg-blue-900/20"
              @click="goCreatePersona"
            >
              <Plus class="h-4 w-4" />
              Create New Persona
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
