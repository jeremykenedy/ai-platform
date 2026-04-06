<script setup>
  import { computed } from 'vue'
  import { cn } from '@/lib/utils'
  import { ChevronDown, FolderOpen } from 'lucide-vue-next'
  import { useProjectsStore } from '@/stores/projects'
  import { useConversationsStore } from '@/stores/conversations'

  const projects = useProjectsStore()
  const conversations = useConversationsStore()

  const selectedProjectId = computed({
    get: () => projects.activeId,
    set: (val) => {
      projects.setActive(val || null)
    },
  })

  const conversationCountByProject = computed(() => {
    const counts = {}
    for (const c of conversations.conversations) {
      const pid = c.project_id ?? '__none__'
      counts[pid] = (counts[pid] ?? 0) + 1
    }
    return counts
  })

  function countFor(id) {
    return conversationCountByProject.value[id] ?? 0
  }
</script>

<template>
  <div class="relative px-3 py-2">
    <div class="relative">
      <FolderOpen
        class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500"
      />
      <select
        v-model="selectedProjectId"
        :class="
          cn(
            'w-full appearance-none rounded-md border py-1.5 pl-8 pr-7 text-sm font-medium',
            'border-gray-200 dark:border-gray-700',
            'bg-white dark:bg-gray-800',
            'text-gray-700 dark:text-gray-300',
            'focus:outline-none focus:ring-2 focus:ring-primary/50',
            'cursor-pointer transition-colors',
            'hover:bg-gray-50 dark:hover:bg-gray-750'
          )
        "
      >
        <option :value="null">All Conversations ({{ conversations.conversations.length }})</option>
        <option v-for="project in projects.sortedProjects" :key="project.id" :value="project.id">
          {{ project.name }} ({{ countFor(project.id) }})
        </option>
      </select>
      <ChevronDown
        class="pointer-events-none absolute right-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400 dark:text-gray-500"
      />
    </div>
  </div>
</template>
