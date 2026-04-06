<script setup>
  import { ref, onMounted, onUnmounted } from 'vue'
  import {
    Upload,
    Play,
    X,
    FileText,
    Trash2,
    RefreshCw,
    ScrollText,
    StopCircle,
  } from 'lucide-vue-next'
  import api from '@/services/api'
  import { getEcho, leaveChannel } from '@/services/echo'
  import { useToast } from '@/composables/useToast'

  const { toast } = useToast()

  const activeTab = ref('datasets')

  // --- Datasets ---
  const datasets = ref([])
  const loadingDatasets = ref(true)
  const uploadFile = ref(null)
  const uploadProgress = ref(0)
  const uploading = ref(false)
  const dragOver = ref(false)
  const uploadForm = ref({ name: '', description: '', format: 'sharegpt' })
  const fileInput = ref(null)
  const deletingDatasetId = ref(null)

  function onDragOver(e) {
    e.preventDefault()
    dragOver.value = true
  }

  function onDragLeave() {
    dragOver.value = false
  }

  function onDrop(e) {
    e.preventDefault()
    dragOver.value = false
    const file = e.dataTransfer.files[0]
    if (file) selectFile(file)
  }

  function onFileInput(e) {
    const file = e.target.files[0]
    if (file) selectFile(file)
  }

  function selectFile(file) {
    uploadFile.value = file
    if (!uploadForm.value.name) {
      uploadForm.value.name = file.name.replace(/\.[^.]+$/, '')
    }
  }

  function formatBytes(bytes) {
    if (!bytes) return '0 B'
    const units = ['B', 'KB', 'MB', 'GB']
    let i = 0
    let val = bytes
    while (val >= 1024 && i < units.length - 1) {
      val /= 1024
      i++
    }
    return `${val.toFixed(1)} ${units[i]}`
  }

  function formatDate(dateStr) {
    if (!dateStr) return ''
    return new Date(dateStr).toLocaleDateString(undefined, {
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    })
  }

  async function fetchDatasets() {
    loadingDatasets.value = true
    try {
      const response = await api.get('/admin/training/datasets')
      datasets.value = response?.data?.data ?? response?.data ?? []
    } catch {
      toast({ title: 'Failed to load datasets', variant: 'destructive' })
    } finally {
      loadingDatasets.value = false
    }
  }

  async function uploadDataset() {
    if (!uploadFile.value || !uploadForm.value.name.trim()) return
    uploading.value = true
    uploadProgress.value = 0

    const form = new FormData()
    form.append('file', uploadFile.value)
    form.append('name', uploadForm.value.name)
    form.append('description', uploadForm.value.description)
    form.append('format', uploadForm.value.format)

    try {
      await api.post('/admin/training/datasets', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress: (e) => {
          uploadProgress.value = e.total ? Math.round((e.loaded / e.total) * 100) : 0
        },
      })
      toast({ title: 'Dataset uploaded' })
      uploadFile.value = null
      uploadForm.value = { name: '', description: '', format: 'sharegpt' }
      uploadProgress.value = 0
      if (fileInput.value) fileInput.value.value = ''
      await fetchDatasets()
    } catch {
      toast({ title: 'Upload failed', variant: 'destructive' })
    } finally {
      uploading.value = false
    }
  }

  async function deleteDataset(dataset) {
    if (!confirm(`Delete dataset "${dataset.name}"?`)) return
    deletingDatasetId.value = dataset.id
    try {
      await api.delete(`/admin/training/datasets/${dataset.id}`)
      datasets.value = datasets.value.filter((d) => d.id !== dataset.id)
      toast({ title: 'Dataset deleted' })
    } catch {
      toast({ title: 'Failed to delete dataset', variant: 'destructive' })
    } finally {
      deletingDatasetId.value = null
    }
  }

  // --- Training Jobs ---
  const jobs = ref([])
  const loadingJobs = ref(true)
  const showStartForm = ref(false)
  const startingJob = ref(false)
  const cancellingId = ref(null)
  const logModal = ref({ open: false, title: '', content: '' })
  const loadingLog = ref(false)

  const jobForm = ref({
    dataset_id: '',
    base_model_id: '',
    output_model_name: '',
    config: '{\n  "epochs": 3,\n  "learning_rate": 0.0001,\n  "batch_size": 4\n}',
  })

  const statusBadge = {
    pending: 'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700 dark:text-yellow-400',
    running: 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400',
    completed: 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400',
    failed: 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400',
    cancelled: 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
  }

  async function fetchJobs() {
    loadingJobs.value = true
    try {
      const response = await api.get('/admin/training/jobs')
      jobs.value = response?.data?.data ?? response?.data ?? []
    } catch {
      toast({ title: 'Failed to load jobs', variant: 'destructive' })
    } finally {
      loadingJobs.value = false
    }
  }

  async function startJob() {
    if (
      !jobForm.value.dataset_id ||
      !jobForm.value.base_model_id ||
      !jobForm.value.output_model_name.trim()
    ) {
      toast({ title: 'Please fill in all required fields', variant: 'destructive' })
      return
    }

    let config = {}
    try {
      config = JSON.parse(jobForm.value.config)
    } catch {
      toast({ title: 'Invalid JSON in config', variant: 'destructive' })
      return
    }

    startingJob.value = true
    try {
      const response = await api.post('/admin/training/jobs', {
        dataset_id: jobForm.value.dataset_id,
        base_model_id: jobForm.value.base_model_id,
        output_model_name: jobForm.value.output_model_name,
        config,
      })
      const job = response?.data?.data ?? response?.data
      jobs.value.unshift(job)
      showStartForm.value = false
      jobForm.value = {
        dataset_id: '',
        base_model_id: '',
        output_model_name: '',
        config: '{\n  "epochs": 3,\n  "learning_rate": 0.0001,\n  "batch_size": 4\n}',
      }
      toast({ title: 'Training job started' })
    } catch (err) {
      const msg = err?.response?.data?.message ?? 'Failed to start job'
      toast({ title: msg, variant: 'destructive' })
    } finally {
      startingJob.value = false
    }
  }

  async function cancelJob(job) {
    if (!confirm(`Cancel job "${job.output_model_name}"?`)) return
    cancellingId.value = job.id
    try {
      await api.post(`/admin/training/jobs/${job.id}/cancel`)
      job.status = 'cancelled'
      toast({ title: 'Job cancelled' })
    } catch {
      toast({ title: 'Failed to cancel job', variant: 'destructive' })
    } finally {
      cancellingId.value = null
    }
  }

  async function viewLog(job) {
    logModal.value = { open: true, title: `Log: ${job.output_model_name}`, content: '' }
    loadingLog.value = true
    try {
      const response = await api.get(`/admin/training/jobs/${job.id}/log`)
      logModal.value.content = response?.data?.log ?? response?.data ?? 'No log output.'
    } catch {
      logModal.value.content = 'Failed to load log.'
    } finally {
      loadingLog.value = false
    }
  }

  function subscribeToEvents() {
    const echo = getEcho()
    echo.private('admin').listen('TrainingJobStatusChanged', (e) => {
      const idx = jobs.value.findIndex((j) => j.id === e.job_id)
      if (idx !== -1) {
        if (e.status) jobs.value[idx].status = e.status
        if (e.progress !== undefined) jobs.value[idx].progress = e.progress
        if (e.completed_at) jobs.value[idx].completed_at = e.completed_at
      }
    })
  }

  onMounted(async () => {
    await Promise.all([fetchDatasets(), fetchJobs()])
    subscribeToEvents()
  })

  onUnmounted(() => {
    leaveChannel('admin')
  })
</script>

<template>
  <div class="space-y-5">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-50">Training</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">
          {{ jobs.filter((j) => j.status === 'running').length }} active job(s)
        </p>
      </div>
    </div>

    <!-- Tabs -->
    <div
      class="flex gap-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-100 dark:bg-gray-800 p-1 w-fit"
    >
      <button
        class="rounded-md px-4 py-1.5 text-sm font-medium transition-colors"
        :class="
          activeTab === 'datasets'
            ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-50 shadow-sm'
            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
        "
        @click="activeTab = 'datasets'"
      >
        Datasets
      </button>
      <button
        class="rounded-md px-4 py-1.5 text-sm font-medium transition-colors"
        :class="
          activeTab === 'jobs'
            ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-50 shadow-sm'
            : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
        "
        @click="activeTab = 'jobs'"
      >
        Jobs
      </button>
    </div>

    <!-- Datasets Tab -->
    <div v-if="activeTab === 'datasets'" class="space-y-5">
      <!-- Upload Area -->
      <div
        class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 space-y-4"
      >
        <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-50">Upload Dataset</h2>

        <!-- Drop zone -->
        <div
          class="flex flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 transition-colors cursor-pointer"
          :class="
            dragOver
              ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
              : 'border-gray-300 dark:border-gray-700 hover:border-gray-400 dark:hover:border-gray-600'
          "
          @dragover="onDragOver"
          @dragleave="onDragLeave"
          @drop="onDrop"
          @click="fileInput?.click()"
        >
          <Upload class="h-8 w-8 text-gray-400 dark:text-gray-500 mb-2" />
          <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
            {{ uploadFile ? uploadFile.name : 'Drop file here or click to browse' }}
          </p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
            {{ uploadFile ? formatBytes(uploadFile.size) : 'Supports JSON, CSV, JSONL' }}
          </p>
          <input
            ref="fileInput"
            type="file"
            accept=".json,.csv,.jsonl"
            class="hidden"
            @change="onFileInput"
          />
        </div>

        <!-- Form fields -->
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300"
              >Dataset Name</label
            >
            <input
              v-model="uploadForm.name"
              type="text"
              placeholder="My dataset"
              class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300"
              >Format</label
            >
            <select
              v-model="uploadForm.format"
              class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="sharegpt">ShareGPT</option>
              <option value="alpaca">Alpaca</option>
            </select>
          </div>
          <div class="sm:col-span-2">
            <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300"
              >Description</label
            >
            <textarea
              v-model="uploadForm.description"
              placeholder="Optional description..."
              rows="2"
              class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
            />
          </div>
        </div>

        <!-- Upload progress -->
        <div v-if="uploading" class="space-y-1">
          <div class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400">
            <span>Uploading...</span>
            <span>{{ uploadProgress }}%</span>
          </div>
          <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
            <div
              class="h-full rounded-full bg-blue-600 transition-all duration-300"
              :style="{ width: uploadProgress + '%' }"
            />
          </div>
        </div>

        <button
          :disabled="uploading || !uploadFile || !uploadForm.name.trim()"
          class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
          @click="uploadDataset"
        >
          <Upload class="h-4 w-4" />
          {{ uploading ? 'Uploading...' : 'Upload Dataset' }}
        </button>
      </div>

      <!-- Datasets List -->
      <div
        class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 overflow-hidden"
      >
        <div class="border-b border-gray-200 dark:border-gray-800 px-5 py-3">
          <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-50">Uploaded Datasets</h2>
        </div>

        <div v-if="loadingDatasets" class="divide-y divide-gray-100 dark:divide-gray-800">
          <div v-for="i in 3" :key="i" class="flex items-center gap-4 px-5 py-4">
            <div class="h-4 w-32 rounded bg-gray-200 dark:bg-gray-700 animate-pulse" />
            <div class="h-4 w-20 rounded bg-gray-200 dark:bg-gray-700 animate-pulse" />
            <div class="ml-auto h-4 w-16 rounded bg-gray-200 dark:bg-gray-700 animate-pulse" />
          </div>
        </div>

        <div
          v-else-if="datasets.length === 0"
          class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400"
        >
          No datasets uploaded yet
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr
                class="border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800/50"
              >
                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">
                  Name
                </th>
                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">
                  Format
                </th>
                <th
                  class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400 hidden sm:table-cell"
                >
                  Rows
                </th>
                <th
                  class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400 hidden sm:table-cell"
                >
                  Size
                </th>
                <th
                  class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400 hidden md:table-cell"
                >
                  Created
                </th>
                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
              <tr
                v-for="d in datasets"
                :key="d.id"
                class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors"
              >
                <td class="px-5 py-3">
                  <div class="flex items-center gap-2">
                    <FileText class="h-4 w-4 shrink-0 text-gray-400 dark:text-gray-500" />
                    <div>
                      <p class="font-medium text-gray-900 dark:text-gray-100">{{ d.name }}</p>
                      <p
                        v-if="d.description"
                        class="text-xs text-gray-500 dark:text-gray-400 mt-0.5"
                      >
                        {{ d.description }}
                      </p>
                    </div>
                  </div>
                </td>
                <td class="px-5 py-3">
                  <span
                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 capitalize"
                  >
                    {{ d.format }}
                  </span>
                </td>
                <td class="px-5 py-3 text-gray-600 dark:text-gray-400 hidden sm:table-cell">
                  {{ d.row_count?.toLocaleString() ?? '-' }}
                </td>
                <td class="px-5 py-3 text-gray-600 dark:text-gray-400 hidden sm:table-cell">
                  {{ d.file_size ? formatBytes(d.file_size) : '-' }}
                </td>
                <td class="px-5 py-3 text-gray-500 dark:text-gray-400 hidden md:table-cell">
                  {{ formatDate(d.created_at) }}
                </td>
                <td class="px-5 py-3 text-right">
                  <button
                    :disabled="deletingDatasetId === d.id"
                    class="rounded-lg p-1.5 text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-50 transition-colors"
                    title="Delete dataset"
                    @click="deleteDataset(d)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Jobs Tab -->
    <div v-else class="space-y-5">
      <!-- Start button -->
      <div class="flex justify-end">
        <button
          class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 transition-colors"
          @click="showStartForm = !showStartForm"
        >
          <Play class="h-4 w-4" />
          Start Training
        </button>
      </div>

      <!-- Start Form -->
      <div
        v-if="showStartForm"
        class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-5 space-y-4"
      >
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-100">New Training Job</h2>
          <button
            class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-200"
            @click="showStartForm = false"
          >
            <X class="h-4 w-4" />
          </button>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300"
              >Dataset <span class="text-red-500">*</span></label
            >
            <select
              v-model="jobForm.dataset_id"
              class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Select dataset...</option>
              <option v-for="d in datasets" :key="d.id" :value="d.id">{{ d.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300"
              >Base Model <span class="text-red-500">*</span></label
            >
            <input
              v-model="jobForm.base_model_id"
              type="text"
              placeholder="Model ID or name..."
              class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div class="sm:col-span-2">
            <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300"
              >Output Model Name <span class="text-red-500">*</span></label
            >
            <input
              v-model="jobForm.output_model_name"
              type="text"
              placeholder="e.g. my-fine-tuned-model"
              class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 dark:placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div class="sm:col-span-2">
            <label class="mb-1 block text-xs font-medium text-gray-700 dark:text-gray-300"
              >Config (JSON)</label
            >
            <textarea
              v-model="jobForm.config"
              rows="5"
              class="w-full rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 font-mono text-xs text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
            />
          </div>
        </div>

        <button
          :disabled="startingJob"
          class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50 transition-colors"
          @click="startJob"
        >
          <Play class="h-4 w-4" />
          {{ startingJob ? 'Starting...' : 'Start Job' }}
        </button>
      </div>

      <!-- Jobs List -->
      <div v-if="loadingJobs" class="space-y-3">
        <div
          v-for="i in 3"
          :key="i"
          class="h-32 animate-pulse rounded-xl bg-gray-200 dark:bg-gray-800"
        />
      </div>

      <div
        v-else-if="jobs.length === 0"
        class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 px-5 py-12 text-center text-sm text-gray-500 dark:text-gray-400"
      >
        No training jobs yet. Click "Start Training" to begin.
      </div>

      <div v-else class="space-y-3">
        <div
          v-for="job in jobs"
          :key="job.id"
          class="rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-5"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h3 class="font-semibold text-gray-900 dark:text-gray-50 truncate">
                  {{ job.output_model_name }}
                </h3>
                <span
                  class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                  :class="statusBadge[job.status] ?? statusBadge.pending"
                >
                  {{ job.status }}
                </span>
              </div>
              <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                Base: {{ job.base_model?.name ?? job.base_model_id ?? '-' }}
                <span class="mx-1.5">|</span>
                Dataset: {{ job.dataset?.name ?? job.dataset_id ?? '-' }}
              </p>
              <div class="flex flex-wrap gap-3 mt-1 text-xs text-gray-500 dark:text-gray-400">
                <span v-if="job.started_at">Started {{ formatDate(job.started_at) }}</span>
                <span v-if="job.completed_at">Completed {{ formatDate(job.completed_at) }}</span>
              </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
              <button
                v-if="job.status === 'running' || job.status === 'pending'"
                :disabled="cancellingId === job.id"
                class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 dark:border-red-800 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 disabled:opacity-50 transition-colors"
                @click="cancelJob(job)"
              >
                <StopCircle class="h-3.5 w-3.5" />
                {{ cancellingId === job.id ? 'Cancelling...' : 'Cancel' }}
              </button>
              <button
                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                @click="viewLog(job)"
              >
                <ScrollText class="h-3.5 w-3.5" />
                View Log
              </button>
            </div>
          </div>

          <!-- Progress bar for running jobs -->
          <div
            v-if="job.status === 'running' || (job.progress > 0 && job.progress < 100)"
            class="mt-4"
          >
            <div
              class="flex items-center justify-between text-xs text-gray-600 dark:text-gray-400 mb-1"
            >
              <span>Progress</span>
              <span>{{ job.progress ?? 0 }}%</span>
            </div>
            <div class="h-2 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-500"
                :class="job.status === 'running' ? 'bg-blue-600' : 'bg-green-500'"
                :style="{ width: (job.progress ?? 0) + '%' }"
              />
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Log Modal -->
  <Teleport to="body">
    <div v-if="logModal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="logModal.open = false" />
      <div
        class="relative flex w-full max-w-2xl flex-col rounded-2xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-2xl max-h-[80vh]"
      >
        <div
          class="flex items-center justify-between border-b border-gray-200 dark:border-gray-800 px-6 py-4 shrink-0"
        >
          <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-50 truncate">
            {{ logModal.title }}
          </h2>
          <button
            class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
            @click="logModal.open = false"
          >
            <X class="h-4 w-4" />
          </button>
        </div>
        <div class="flex-1 overflow-y-auto p-5">
          <div v-if="loadingLog" class="flex items-center justify-center py-10">
            <RefreshCw class="h-5 w-5 animate-spin text-gray-400" />
          </div>
          <pre
            v-else
            class="whitespace-pre-wrap font-mono text-xs text-gray-800 dark:text-gray-200 leading-relaxed"
            >{{ logModal.content }}</pre
          >
        </div>
      </div>
    </div>
  </Teleport>
</template>
