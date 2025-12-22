<template>
  <teleport to="body">
    <transition name="modal">
      <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeModal">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
          <!-- Background overlay -->
          <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75" @click="closeModal"></div>

          <!-- Modal panel -->
          <div class="inline-block align-bottom bg-dark-800 rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-primary-800/30">
            <!-- Header -->
            <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
              <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-magenta-400">Upload Documents</h3>
                <button @click="closeModal" class="text-gray-400 hover:text-gray-300 transition-colors">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Body -->
            <form @submit.prevent="submit" enctype="multipart/form-data">
              <div class="px-6 py-6 space-y-4">
                <!-- Document Category Selection -->
                <div>
                  <label class="block text-gray-300 font-medium mb-2">
                    Document Category <span class="text-red-400">*</span>
                  </label>
                  <select
                    v-model="form.document_category"
                    class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 focus:border-magenta-500 focus:ring focus:ring-magenta-500/50 disabled:opacity-60 disabled:cursor-not-allowed"
                    required
                  >
                    <option value="">Select category...</option>
                    <option
                      v-for="(label, value) in categories"
                      :key="value"
                      :value="value"
                    >
                      {{ label }}
                    </option>
                  </select>
                  <p v-if="form.document_category" class="text-xs text-gray-400 mt-1">
                    {{ getCategoryDescription(form.document_category) }}
                  </p>
                  <p v-if="form.errors.document_category" class="text-red-400 text-sm mt-1">
                    {{ form.errors.document_category }}
                  </p>
                </div>

                <!-- Multiple File Upload -->
                <div>
                  <label class="block text-gray-300 font-medium mb-2">
                    Select Files <span class="text-red-400">*</span>
                  </label>
                  <input
                    ref="fileInput"
                    type="file"
                    @change="handleFileChange"
                    accept=".pdf,.doc,.docx,.xlsx,.xls,.csv,.jpg,.jpeg,.png"
                    class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-magenta-600 file:text-white hover:file:bg-magenta-700"
                    multiple
                    required
                  />
                  <p class="text-xs text-gray-400 mt-1">
                    Accepted formats: PDF, Word, Excel, CSV, Images (max 10MB per file)
                  </p>
                  <p v-if="form.errors.file" class="text-red-400 text-sm mt-1">
                    {{ form.errors.file }}
                  </p>
                </div>

                <!-- Selected Files List -->
                <div v-if="selectedFiles.length > 0" class="space-y-2">
                  <p class="text-sm font-medium text-gray-300">Selected Files ({{ selectedFiles.length }}):</p>
                  <div
                    v-for="(file, index) in selectedFiles"
                    :key="index"
                    class="bg-blue-900/20 border border-blue-700/30 rounded-lg p-3"
                  >
                    <div class="flex items-center justify-between">
                      <div class="flex items-center flex-1 min-w-0">
                        <svg class="w-6 h-6 text-blue-400 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <div class="flex-1 min-w-0">
                          <p class="text-sm font-medium text-gray-300 truncate">{{ file.name }}</p>
                          <p class="text-xs text-gray-400">{{ formatFileSize(file.size) }}</p>
                        </div>
                      </div>
                      <button
                        type="button"
                        @click="removeFile(index)"
                        class="ml-3 text-red-400 hover:text-red-300 flex-shrink-0"
                      >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Upload Progress -->
                <div v-if="uploading" class="space-y-2">
                  <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-300">Uploading {{ uploadedCount }} of {{ totalFiles }} files...</span>
                    <span class="text-magenta-400 font-medium">{{ uploadProgress }}%</span>
                  </div>
                  <div class="bg-gray-700 rounded-full h-2 overflow-hidden">
                    <div 
                      class="h-full bg-gradient-to-r from-magenta-500 to-primary-500 transition-all duration-300"
                      :style="{ width: uploadProgress + '%' }"
                    ></div>
                  </div>
                </div>

                <!-- Success Messages -->
                <div v-if="successMessages.length > 0" class="space-y-2">
                  <div 
                    v-for="(message, index) in successMessages" 
                    :key="index"
                    class="bg-green-900/20 border border-green-700/50 rounded-lg p-3"
                  >
                    <p class="text-green-300 text-sm flex items-center">
                      <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                      </svg>
                      {{ message }}
                    </p>
                  </div>
                </div>

                <!-- Error Messages -->
                <div v-if="errorMessages.length > 0" class="space-y-2">
                  <div 
                    v-for="(message, index) in errorMessages" 
                    :key="index"
                    class="bg-red-900/20 border border-red-700/50 rounded-lg p-3"
                  >
                    <p class="text-red-300 text-sm flex items-center">
                      <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                      </svg>
                      {{ message }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Footer -->
              <div class="px-6 py-4 bg-dark-900/60 border-t border-primary-800/40 flex justify-end gap-3">
                <button 
                  type="button" 
                  @click="closeModal" 
                  class="px-4 py-2 text-gray-400 hover:text-gray-300 transition-colors"
                >
                  Close
                </button>
                <button
                  type="submit"
                  :disabled="uploading || !form.document_category || selectedFiles.length === 0"
                  class="px-4 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <span v-if="uploading">Uploading {{ uploadedCount }}/{{ totalFiles }}...</span>
                  <span v-else>Upload {{ selectedFiles.length }} File{{ selectedFiles.length !== 1 ? 's' : '' }}</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script>
import { router } from '@inertiajs/vue3'

export default {
  props: {
    show: {
      type: Boolean,
      default: false,
    },
    applicationId: {
      type: Number,
      required: true,
    },
    categories: {
      type: Object,
      required: true,
    },
    categoryDescriptions: {
      type: Object,
      required: true,
    },
    preselectedCategory: {
      type: String,
      default: null,
    },
  },
  emits: ['close'],
  data() {
    return {
      form: {
        document_category: '',
        errors: {},
      },
      selectedFiles: [],
      uploading: false,
      uploadedCount: 0,
      totalFiles: 0,
      uploadProgress: 0,
      successMessages: [],
      errorMessages: [],
    }
  },
  watch: {
    // Watch for when modal opens
    show: {
      handler(newVal) {
        console.log('Modal show changed:', newVal)
        if (newVal) {
          this.$nextTick(() => {
            console.log('Modal opened, preselectedCategory:', this.preselectedCategory)
            // Reset messages
            this.successMessages = []
            this.errorMessages = []
            
            // Apply preselected category
            if (this.preselectedCategory) {
              console.log('Setting category to:', this.preselectedCategory)
              this.form.document_category = this.preselectedCategory
              console.log('Category is now:', this.form.document_category)
            }
          })
        }
      },
      immediate: true,
    },
    
    // CRITICAL: Watch for category prop changes even when modal is already open
    preselectedCategory: {
      handler(newVal) {
        console.log('preselectedCategory prop changed:', newVal, 'show:', this.show)
        if (this.show && newVal) {
          this.$nextTick(() => {
            console.log('Updating category to:', newVal)
            this.form.document_category = newVal
            console.log('Category updated to:', this.form.document_category)
          })
        }
      },
      immediate: true,
    },
  },
  methods: {
    closeModal() {
      // Reset everything
      this.form.document_category = ''
      this.form.errors = {}
      this.selectedFiles = []
      this.successMessages = []
      this.errorMessages = []
      this.uploading = false
      this.uploadedCount = 0
      this.totalFiles = 0
      this.uploadProgress = 0
      
      // Reset file input
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = ''
      }
      
      this.$emit('close')
    },
    handleFileChange(event) {
      const files = Array.from(event.target.files)
      console.log('Files selected:', files.length)
      this.selectedFiles = files
      
      // Clear any previous messages when new files are selected
      this.successMessages = []
      this.errorMessages = []
    },
    removeFile(index) {
      this.selectedFiles.splice(index, 1)
      
      // Reset file input if no files remain
      if (this.selectedFiles.length === 0 && this.$refs.fileInput) {
        this.$refs.fileInput.value = ''
      }
    },
    formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes'
      const k = 1024
      const sizes = ['Bytes', 'KB', 'MB', 'GB']
      const i = Math.floor(Math.log(bytes) / Math.log(k))
      return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
    },
    getCategoryDescription(category) {
      return this.categoryDescriptions[category] || ''
    },
    async submit() {
      if (this.selectedFiles.length === 0 || !this.form.document_category) {
        return
      }

      this.uploading = true
      this.uploadedCount = 0
      this.totalFiles = this.selectedFiles.length
      this.uploadProgress = 0
      this.successMessages = []
      this.errorMessages = []
      this.form.errors = {}

      // Upload files sequentially
      for (let i = 0; i < this.selectedFiles.length; i++) {
        const file = this.selectedFiles[i]
        
        try {
          await this.uploadSingleFile(file)
          this.uploadedCount++
          this.uploadProgress = Math.round((this.uploadedCount / this.totalFiles) * 100)
        } catch (error) {
          console.error(`Error uploading ${file.name}:`, error)
        }
      }

      // All uploads complete
      this.uploading = false
      
      // Clear file selection
      this.selectedFiles = []
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = ''
      }

      // Auto-hide success messages after 5 seconds
      if (this.successMessages.length > 0 && this.errorMessages.length === 0) {
        setTimeout(() => {
          this.successMessages = []
        }, 5000)
      }
    },
    uploadSingleFile(file) {
      return new Promise((resolve, reject) => {
        const formData = new FormData()
        formData.append('document_category', this.form.document_category)
        formData.append('file', file)

        router.post(
          `/applications/${this.applicationId}/documents`,
          formData,
          {
            onSuccess: () => {
              this.successMessages.push(`✓ ${file.name} uploaded successfully`)
              resolve()
            },
            onError: (errors) => {
              console.error('Upload error:', errors)
              this.form.errors = errors
              this.errorMessages.push(`✗ ${file.name}: ${errors.file || 'Upload failed'}`)
              reject(errors)
            },
            preserveScroll: true,
            preserveState: true,
          }
        )
      })
    },
  },
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>