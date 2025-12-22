<template>
  <teleport to="body">
    <transition name="modal">
      <div
        v-if="show"
        class="fixed inset-0 z-50 overflow-y-auto"
        @click.self="close"
      >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
          <!-- Background overlay -->
          <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75" @click="close"></div>

          <!-- Modal panel -->
          <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-dark-800 shadow-2xl rounded-2xl border border-primary-700/50">
            
            <!-- Close button -->
            <button
              @click="close"
              class="absolute top-4 right-4 text-gray-400 hover:text-white transition-colors"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>

            <!-- Header -->
            <div class="mb-6">
              <h3 class="text-xl font-bold text-white">Upload Document</h3>
              <p class="text-sm text-gray-400 mt-1">Select category and file to upload</p>
            </div>

            <!-- Form -->
            <form @submit.prevent="submit">
              <div class="space-y-4">
                <!-- Document Category -->
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">
                    Document Category <span class="text-red-400">*</span>
                  </label>
                  <select
                    v-model="form.document_category"
                    :disabled="!!preselectedCategory"
                    class="w-full px-4 py-2 bg-dark-900 border border-primary-700 rounded-lg text-white focus:outline-none focus:border-magenta-500 disabled:opacity-60 disabled:cursor-not-allowed"
                    required
                  >
                    <option value="">Select category...</option>
                    <option 
                      v-for="(label, key) in categories" 
                      :key="key" 
                      :value="key"
                    >
                      {{ label }}
                    </option>
                  </select>
                  <p v-if="form.document_category" class="mt-1 text-xs text-gray-400">
                    {{ categoryDescriptions[form.document_category] }}
                  </p>
                  <p v-if="form.errors.document_category" class="mt-1 text-sm text-red-400">
                    {{ form.errors.document_category }}
                  </p>
                </div>

                <!-- File Upload -->
                <div>
                  <label class="block text-sm font-medium text-gray-300 mb-2">
                    Select File <span class="text-red-400">*</span>
                  </label>
                  <div class="flex items-center gap-3">
                    <label class="flex-1 cursor-pointer">
                      <div class="flex items-center justify-center px-4 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        Choose File
                      </div>
                      <input
                        ref="fileInput"
                        type="file"
                        @change="handleFileChange"
                        accept=".pdf,.doc,.docx,.xlsx,.xls,.csv,.jpg,.jpeg,.png"
                        class="hidden"
                        required
                      />
                    </label>
                  </div>
                  <p v-if="selectedFileName" class="mt-2 text-sm text-gray-300">
                    Selected: {{ selectedFileName }}
                  </p>
                  <p class="mt-1 text-xs text-gray-500">
                    Accepted formats: PDF, Word, Excel, CSV, Images (max 10MB)
                  </p>
                  <p v-if="form.errors.file" class="mt-1 text-sm text-red-400">
                    {{ form.errors.file }}
                  </p>
                </div>

                <!-- Success Message -->
                <div v-if="uploadSuccess" class="bg-green-900/20 border border-green-700/50 rounded-lg p-3">
                  <p class="text-green-300 text-sm flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                      <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Document uploaded successfully!
                  </p>
                </div>
              </div>

              <!-- Actions -->
              <div class="mt-6 flex gap-3">
                <button
                  type="button"
                  @click="close"
                  class="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
                >
                  Close
                </button>
                <button
                  type="submit"
                  :disabled="form.processing || !form.document_category || !form.file"
                  class="flex-1 px-4 py-2 bg-primary-600 hover:bg-primary-700 disabled:bg-gray-600 disabled:cursor-not-allowed text-white rounded-lg transition-colors flex items-center justify-center"
                >
                  <svg 
                    v-if="form.processing" 
                    class="animate-spin h-5 w-5 mr-2" 
                    xmlns="http://www.w3.org/2000/svg" 
                    fill="none" 
                    viewBox="0 0 24 24"
                  >
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  {{ form.processing ? 'Uploading...' : 'Upload Document' }}
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
  emits: ['close'],
  props: {
    show: Boolean,
    applicationId: Number,
    categories: Object,
    categoryDescriptions: Object,
    preselectedCategory: {
      type: String,
      default: null,
    },
  },
  data() {
    return {
      selectedFileName: '',
      uploadSuccess: false,
      form: this.$inertia.form({
        document_category: '',
        file: null,
      }),
    }
  },
  watch: {
    // Watch for when modal opens AND category changes
    show: {
      handler(newVal) {
        if (newVal) {
          // Reset success message when modal opens
          this.uploadSuccess = false
          // Set the category from prop
          this.form.document_category = this.preselectedCategory || ''
        }
      },
      immediate: true,
    },
    // Also watch preselectedCategory separately in case it changes while modal is open
    preselectedCategory: {
      handler(newVal) {
        if (this.show) {
          this.form.document_category = newVal || ''
        }
      },
      immediate: true,
    },
  },
  methods: {
    handleFileChange(event) {
      const file = event.target.files[0]
      if (file) {
        this.form.file = file
        this.selectedFileName = file.name
      }
    },
    submit() {
      this.uploadSuccess = false
      
      this.form.post(`/applications/${this.applicationId}/documents`, {
        onSuccess: () => {
          // Show success message
          this.uploadSuccess = true
          
          // Reset file but keep category selected
          this.form.file = null
          this.selectedFileName = ''
          
          // Reset the file input using ref
          if (this.$refs.fileInput) {
            this.$refs.fileInput.value = ''
          }
          
          // Hide success message after 3 seconds
          setTimeout(() => {
            this.uploadSuccess = false
          }, 3000)
        },
        onError: () => {
          // Errors will be shown in the form
        },
        preserveScroll: true, // Prevents page from scrolling on success
      })
    },
    close() {
      // Reset form completely when closing
      this.form.reset()
      this.form.clearErrors()
      this.selectedFileName = ''
      this.uploadSuccess = false
      
      // Reset file input
      if (this.$refs.fileInput) {
        this.$refs.fileInput.value = ''
      }
      
      this.$emit('close')
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