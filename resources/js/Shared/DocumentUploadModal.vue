<template>
    <teleport to="body">
      <transition name="modal">
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeModal">
          <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75" @click="closeModal"></div>
  
            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-dark-800 rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full border border-primary-800/30">
              <!-- Header -->
              <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
                <div class="flex items-center justify-between">
                  <h3 class="text-xl font-bold text-magenta-400">Upload Document</h3>
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
                      class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 focus:border-magenta-500 focus:ring focus:ring-magenta-500/50"
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
  
                  <!-- File Upload -->
                  <div>
                    <label class="block text-gray-300 font-medium mb-2">
                      Select File <span class="text-red-400">*</span>
                    </label>
                    <input
                      type="file"
                      @change="handleFileChange"
                      accept=".pdf,.doc,.docx,.xlsx,.xls,.csv,.jpg,.jpeg,.png"
                      class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-magenta-600 file:text-white hover:file:bg-magenta-700"
                      required
                    />
                    <p class="text-xs text-gray-400 mt-1">
                      Accepted formats: PDF, Word, Excel, CSV, Images (max 10MB)
                    </p>
                    <p v-if="form.errors.file" class="text-red-400 text-sm mt-1">
                      {{ form.errors.file }}
                    </p>
                  </div>
  
                  <!-- File Preview -->
                  <div v-if="selectedFileName" class="bg-blue-900/20 border border-blue-700/30 rounded-lg p-4">
                    <div class="flex items-center">
                      <svg class="w-8 h-8 text-blue-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                      </svg>
                      <div class="flex-1">
                        <p class="text-sm font-medium text-gray-300">{{ selectedFileName }}</p>
                        <p class="text-xs text-gray-400">{{ selectedFileSize }}</p>
                      </div>
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
                    Cancel
                  </button>
                  <button
                    type="submit"
                    :disabled="form.processing || !form.document_category || !form.file"
                    class="px-4 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors text-sm font-medium disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                    <span v-if="form.processing">Uploading...</span>
                    <span v-else>Upload Document</span>
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
    },
    emits: ['close'],
    data() {
      return {
        form: this.$inertia.form({
          document_category: '',
          file: null,
        }),
        selectedFileName: '',
        selectedFileSize: '',
      }
    },
    watch: {
      show(newVal) {
        if (newVal) {
          // Reset form when modal opens
          this.form.reset()
          this.selectedFileName = ''
          this.selectedFileSize = ''
        }
      },
    },
    methods: {
      closeModal() {
        this.$emit('close')
      },
      handleFileChange(event) {
        const file = event.target.files[0]
        if (file) {
          this.form.file = file
          this.selectedFileName = file.name
          this.selectedFileSize = this.formatFileSize(file.size)
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
      submit() {
        this.form.post(`/applications/${this.applicationId}/documents`, {
          forceFormData: true,
          onSuccess: () => {
            this.$emit('close')
          },
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