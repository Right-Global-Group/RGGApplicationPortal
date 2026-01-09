<template>
    <teleport to="body">
      <transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="show"
          class="fixed inset-0 z-50 overflow-y-auto"
          @click.self="$emit('close')"
        >
          <div class="flex min-h-screen items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="fixed inset-0 bg-black/75 backdrop-blur-sm"></div>
  
            <!-- Modal -->
            <div
              class="relative w-full max-w-2xl bg-dark-800 rounded-xl shadow-2xl border border-primary-800/30"
              @click.stop
            >
              <!-- Header -->
              <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
                <div class="flex items-center justify-between">
                  <h3 class="text-xl font-bold text-white">Upload Document</h3>
                  <button
                    @click="$emit('close')"
                    class="text-gray-400 hover:text-white transition-colors"
                  >
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                  </button>
                </div>
              </div>
  
              <!-- Body -->
              <form @submit.prevent="submitUpload">
                <div class="p-6 space-y-4">
                  <!-- Application Selection -->
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                      Select Application *
                    </label>
                    <div class="relative">
                      <select
                        v-model="form.application_id"
                        class="w-full px-4 py-2 pr-12 bg-dark-900/50 border border-primary-800/30 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-magenta-500 appearance-none"
                        required
                      >
                        <option value="">Choose an application...</option>
                        <option
                          v-for="app in applications"
                          :key="app.id"
                          :value="app.id"
                        >
                          {{ app.name }} - {{  app.account_name }}
                        </option>
                      </select>
                      <!-- Custom dropdown arrow -->
                      <div class="absolute inset-y-0 right-4 flex items-center pointer-events-none">
                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                      </div>
                    </div>
                    <p v-if="form.errors.application_id" class="mt-1 text-sm text-red-400">
                      {{ form.errors.application_id }}
                    </p>
                  </div>
  
                  <!-- Document Category -->
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                      Document Category *
                    </label>
                    <input
                      v-model="form.document_category"
                      type="text"
                      placeholder="e.g., Additional Document, Bank Statement, etc."
                      class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-magenta-500"
                      required
                    />
                    <p v-if="form.errors.document_category" class="mt-1 text-sm text-red-400">
                      {{ form.errors.document_category }}
                    </p>
                  </div>
  
                  <!-- File Upload -->
                  <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">
                      Select File *
                    </label>
                    <input
                      ref="fileInput"
                      type="file"
                      accept=".pdf,.doc,.docx,.xlsx,.xls,.csv,.jpg,.jpeg,.png"
                      @change="handleFileChange"
                      class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-white file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-magenta-600 file:text-white hover:file:bg-magenta-700 focus:outline-none focus:ring-2 focus:ring-magenta-500"
                      required
                    />
                    <p class="mt-1 text-xs text-gray-400">
                      Accepted formats: PDF, DOC, DOCX, XLSX, XLS, CSV, JPG, JPEG, PNG (Max 50MB)
                    </p>
                    <p v-if="form.errors.file" class="mt-1 text-sm text-red-400">
                      {{ form.errors.file }}
                    </p>
                  </div>
                </div>
  
                <!-- Footer -->
                <div class="px-6 py-4 bg-dark-900/60 border-t border-primary-800/40 flex justify-end gap-3">
                  <button
                    type="button"
                    @click="$emit('close')"
                    class="px-4 py-2 text-gray-400 hover:text-white transition-colors"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    :disabled="form.processing"
                    class="px-6 py-2 bg-magenta-600 hover:bg-magenta-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg transition-colors flex items-center gap-2"
                  >
                    <svg
                      v-if="form.processing"
                      class="animate-spin h-5 w-5"
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      viewBox="0 0 24 24"
                    >
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
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
      show: Boolean,
      applications: Array,
      preselectedApplicationId: Number,
    },
    data() {
      return {
        form: this.$inertia.form({
          application_id: this.preselectedApplicationId || '',
          document_category: '',
          file: null,
        }),
      }
    },
    watch: {
      // Update form when preselected ID changes
      preselectedApplicationId(newVal) {
        if (newVal) {
          this.form.application_id = newVal
        }
      },
      // Reset form when modal opens with preselected app
      show(newVal) {
        if (newVal && this.preselectedApplicationId) {
          this.form.application_id = this.preselectedApplicationId
        }
      }
    },
    methods: {
      handleFileChange(event) {
        this.form.file = event.target.files[0]
      },
      
      submitUpload() {
        this.form.post('/document-library/upload', {
          preserveScroll: true,
          onSuccess: () => {
            this.$emit('close')
            this.form.reset()
            this.$refs.fileInput.value = ''
          },
        })
      },
    },
  }
  </script>