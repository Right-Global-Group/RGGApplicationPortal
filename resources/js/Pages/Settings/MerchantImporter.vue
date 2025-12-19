<template>
  <div>
    <Head title="Merchant Importer" />
    
    <!-- Upload Section -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-8 border border-primary-800/30 shadow-2xl mb-6">
      <h2 class="text-2xl font-bold text-magenta-400 mb-6">Import DocuSign Contracts</h2>
      
      <div class="mb-6 p-4 bg-blue-900/20 border border-blue-700/30 rounded-lg">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <p class="text-blue-300 font-medium mb-2">How to download contracts from DocuSign:</p>
            <ol class="text-sm text-gray-300 space-y-2 list-decimal list-inside">
              <li>Go to your DocuSign account and find the completed envelope</li>
              <li>Click on the envelope to open it</li>
              <li>Click the <strong>"Download"</strong> button</li>
              <li><strong class="text-yellow-300">Important:</strong> Select <strong>"Include Certificate of Completion"</strong> option</li>
              <li>Download as <strong>ZIP file</strong> (NOT combined PDF)</li>
              <li>The ZIP should contain: Contract PDF, Application Form PDF, and <strong>Summary.pdf</strong></li>
              <li>You can upload multiple ZIP files at once for bulk import</li>
            </ol>
          </div>
        </div>
      </div>

      <div class="mb-6 p-4 bg-yellow-900/20 border border-yellow-700/30 rounded-lg">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 text-yellow-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
          </svg>
          <div>
            <p class="text-yellow-300 font-medium mb-1">Important Requirements:</p>
            <ul class="text-sm text-gray-300 space-y-1 list-disc list-inside ml-4">
              <li>Only ZIP files are accepted (not combined PDFs)</li>
              <li>Each ZIP must contain <strong>Summary.pdf</strong> to extract merchant details</li>
              <li>Merchant name and email are automatically extracted from Summary.pdf</li>
              <li>You can upload up to 10 ZIP files at once</li>
            </ul>
          </div>
        </div>
      </div>

      <form @submit.prevent="uploadContracts">
        <div class="space-y-4">
          <!-- File Upload -->
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">
              Contract ZIP Files (Multiple files supported)
            </label>
            <input
              type="file"
              @change="handleFileChange"
              accept=".zip"
              multiple
              class="block w-full text-sm text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-magenta-600 file:text-white hover:file:bg-magenta-700 cursor-pointer bg-dark-900/50 border border-primary-800/30 rounded-lg"
              required
            />
            <p class="text-xs text-gray-500 mt-1">
              Upload 1-10 ZIP files from DocuSign (must include Summary.pdf in each ZIP)
            </p>
            <div v-if="form.files.length > 0" class="mt-2">
              <p class="text-sm text-gray-400">Selected files: {{ form.files.length }}</p>
              <ul class="text-xs text-gray-500 mt-1 space-y-1">
                <li v-for="(file, index) in form.files" :key="index">
                  {{ file.name }} ({{ formatFileSize(file.size) }})
                </li>
              </ul>
            </div>
          </div>

          <!-- Submit Button -->
          <div class="flex items-center gap-3">
            <button
              type="submit"
              :disabled="uploading || form.files.length === 0"
              class="px-6 py-2 bg-gradient-to-r from-magenta-600 to-primary-600 hover:from-magenta-500 hover:to-primary-500 text-white rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
            >
              <svg 
                v-if="uploading" 
                class="animate-spin h-5 w-5" 
                xmlns="http://www.w3.org/2000/svg" 
                fill="none" 
                viewBox="0 0 24 24"
              >
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              {{ uploading ? 'Importing...' : `Import ${form.files.length} Contract${form.files.length > 1 ? 's' : ''}` }}
            </button>
            
            <button
              v-if="form.files.length > 0"
              @click="clearForm"
              type="button"
              class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors"
            >
              Clear
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Import History Table -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-primary-800/30 shadow-2xl overflow-hidden">
      <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-xl font-bold text-magenta-400">Import History</h2>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full">
          <thead>
            <tr class="text-magenta-400 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
              <th class="px-6 py-3 text-left">Merchant Account</th>
              <th class="px-6 py-3 text-left">Application</th>
              <th class="px-6 py-3 text-left">Uploaded By</th>
              <th class="px-6 py-3 text-left">Status</th>
              <th class="px-6 py-3 text-left">Date</th>
              <th class="px-6 py-3 text-left">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="importRecord in imports.data"
              :key="importRecord.id"
              class="border-b border-primary-800/20 hover:bg-primary-900/30 transition-colors"
            >
              <td class="px-6 py-4">
                <Link
                  v-if="importRecord.account"
                  :href="`/accounts/${importRecord.account.id}/edit`"
                  class="text-blue-400 hover:text-blue-300"
                >
                  {{ importRecord.account.name }}
                </Link>
                <span v-else class="text-gray-500">—</span>
              </td>
              <td class="px-6 py-4">
                <Link
                  v-if="importRecord.application"
                  :href="`/applications/${importRecord.application.id}/status`"
                  class="text-blue-400 hover:text-blue-300"
                >
                  {{ importRecord.application.name }}
                </Link>
                <span v-else class="text-gray-500">—</span>
              </td>
              <td class="px-6 py-4 text-gray-300">
                {{ importRecord.uploaded_by }}
              </td>
              <td class="px-6 py-4">
                <span
                  class="px-3 py-1 rounded-full text-xs font-semibold"
                  :class="{
                    'bg-green-900/50 text-green-300': importRecord.status === 'success',
                    'bg-red-900/50 text-red-300': importRecord.status === 'failed',
                    'bg-yellow-900/50 text-yellow-300': importRecord.status === 'pending'
                  }"
                >
                  {{ importRecord.status }}
                </span>
              </td>
              <td class="px-6 py-4 text-gray-400 text-sm">
                {{ importRecord.created_at }}
              </td>
              <td class="px-6 py-4">
                <div class="flex items-center gap-2">
                  <Link
                    v-if="importRecord.application"
                    :href="`/applications/${importRecord.application.id}/status`"
                    class="text-magenta-400 hover:text-magenta-300 text-sm"
                  >
                    View Application
                  </Link>
                  <button
                    v-if="importRecord.error_message"
                    @click="showError(importRecord)"
                    class="text-red-400 hover:text-red-300 text-sm"
                  >
                    View Error
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="imports.links && imports.links.length > 3" class="px-6 py-4 border-t border-primary-800/30">
        <div class="flex flex-wrap gap-1">
          <component
            :is="link.url ? Link : 'span'"
            v-for="(link, index) in imports.links"
            :key="index"
            :href="link.url || undefined"
            :class="[
              'px-3 py-1 rounded text-sm',
              link.active
                ? 'bg-magenta-600 text-white'
                : link.url 
                  ? 'bg-dark-900/50 text-gray-400 hover:bg-primary-900/50 cursor-pointer'
                  : 'bg-dark-900/50 text-gray-500 cursor-not-allowed opacity-50'
            ]"
            v-html="link.label"
          />
        </div>
      </div>
    </div>

    <!-- Error Modal -->
    <div
      v-if="showErrorModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
      @click.self="showErrorModal = false"
    >
      <div class="bg-dark-800 rounded-xl border border-red-700/30 max-w-2xl w-full p-6">
        <div class="flex items-start justify-between mb-4">
          <h3 class="text-xl font-bold text-red-400">Import Error</h3>
          <button
            @click="showErrorModal = false"
            class="text-gray-400 hover:text-white"
          >
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="bg-red-900/20 border border-red-700/30 rounded-lg p-4">
          <p class="text-gray-300 whitespace-pre-wrap">{{ selectedError }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'

export default {
  components: {
    Head,
    Link,
  },
  props: {
    imports: {
      type: Object,
      default: () => ({ data: [], links: [] }) // Add default value
    },
  },
  data() {
    return {
      form: {
        files: [],
      },
      uploading: false,
      showErrorModal: false,
      selectedError: '',
    }
  },
  methods: {
    handleFileChange(event) {
      this.form.files = Array.from(event.target.files)
    },
    
    formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes'
      const k = 1024
      const sizes = ['Bytes', 'KB', 'MB', 'GB']
      const i = Math.floor(Math.log(bytes) / Math.log(k))
      return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
    },
    
    async uploadContracts() {
      if (this.form.files.length === 0) return

      this.uploading = true

      const formData = new FormData()
      this.form.files.forEach((file, index) => {
        formData.append(`files[${index}]`, file)
      })

      this.$inertia.post('/settings/merchant-importer/import', formData, {
        onSuccess: () => {
          this.clearForm()
        },
        onFinish: () => {
          this.uploading = false
        },
      })
    },
    
    clearForm() {
      this.form.files = []
      // Reset file input
      const fileInput = this.$el.querySelector('input[type="file"]')
      if (fileInput) {
        fileInput.value = ''
      }
    },
    
    showError(importRecord) {
      this.selectedError = importRecord.error_message
      this.showErrorModal = true
    },
  },
}
</script>