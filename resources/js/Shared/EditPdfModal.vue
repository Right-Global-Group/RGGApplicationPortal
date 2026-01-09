<template>
    <Teleport to="body">
      <Transition
        enter-active-class="transition ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto">
          <!-- Backdrop -->
          <div 
            class="fixed inset-0 bg-black/75 backdrop-blur-sm transition-opacity"
            @click="$emit('close')"
          ></div>
  
          <!-- Modal Container -->
          <div class="flex min-h-full items-center justify-center p-4">
            <Transition
              enter-active-class="transition ease-out duration-300"
              enter-from-class="opacity-0 translate-y-4 scale-95"
              enter-to-class="opacity-100 translate-y-0 scale-100"
              leave-active-class="transition ease-in duration-200"
              leave-from-class="opacity-100 translate-y-0 scale-100"
              leave-to-class="opacity-0 translate-y-4 scale-95"
            >
              <div
                v-if="show"
                class="relative w-full max-w-4xl transform overflow-hidden rounded-2xl bg-dark-800 border border-primary-800/30 shadow-2xl transition-all"
                @click.stop
              >
                <!-- Header -->
                <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
                  <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-white">
                      Edit PDF: {{ document?.original_filename }}
                    </h2>
                    <button
                      @click="$emit('close')"
                      class="text-gray-400 hover:text-white transition-colors"
                      type="button"
                    >
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                      </svg>
                    </button>
                  </div>
                </div>
  
                <!-- Content -->
                <div class="px-6 py-6">
                  <!-- Loading State -->
                  <div v-if="loading" class="flex items-center justify-center py-12">
                    <div class="text-center">
                      <svg class="animate-spin h-12 w-12 text-magenta-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                      </svg>
                      <p class="text-gray-400">Loading PDF fields...</p>
                    </div>
                  </div>
  
                  <!-- Error State -->
                  <div v-else-if="error" class="text-center py-12">
                    <svg class="w-12 h-12 text-red-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <p class="text-red-400 mb-4">{{ error }}</p>
                    <button
                      @click="$emit('close')"
                      class="px-4 py-2 bg-dark-700 hover:bg-dark-600 text-white rounded-lg transition-colors"
                    >
                      Close
                    </button>
                  </div>
  
                  <!-- Fields Content -->
                  <div v-else>
                    <!-- Page Selector -->
                    <div v-if="Object.keys(fields).length > 1" class="flex gap-2 mb-6 overflow-x-auto pb-2">
                      <button
                        v-for="page in Object.keys(fields)"
                        :key="page"
                        @click="currentPage = parseInt(page)"
                        type="button"
                        :class="[
                          'px-4 py-2 rounded-lg font-medium transition-all whitespace-nowrap',
                          currentPage === parseInt(page)
                            ? 'bg-magenta-600 text-white shadow-lg'
                            : 'bg-dark-700 text-gray-300 hover:bg-dark-600'
                        ]"
                      >
                        Page {{ page }}
                      </button>
                    </div>
  
                    <!-- Fields for Current Page -->
                    <div class="space-y-4 max-h-[500px] overflow-y-auto pr-2 custom-scrollbar">
                      <div
                        v-for="(fieldValue, fieldName) in fields[currentPage]"
                        :key="fieldName"
                        class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4"
                      >
                        <label :for="`field-${fieldName}`" class="block text-sm font-medium text-gray-300 mb-2">
                          {{ formatFieldName(fieldName) }}
                        </label>
                        <input
                          :id="`field-${fieldName}`"
                          v-model="fields[currentPage][fieldName]"
                          type="text"
                          class="w-full px-4 py-2 bg-dark-800 border border-primary-800/30 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-magenta-500"
                        />
                      </div>
  
                      <div v-if="!fields[currentPage] || Object.keys(fields[currentPage]).length === 0" class="text-center py-8 text-gray-400">
                        No editable fields on this page
                      </div>
                    </div>
                  </div>
                </div>
  
                <!-- Footer -->
                <div v-if="!loading && !error" class="px-6 py-4 bg-dark-900/50 border-t border-primary-800/30 flex justify-end gap-3">
                  <button
                    @click="$emit('close')"
                    type="button"
                    class="px-6 py-2 bg-dark-700 hover:bg-dark-600 text-white rounded-lg transition-colors"
                  >
                    Cancel
                  </button>
                  <button
                    @click="handleSave"
                    :disabled="saving"
                    type="button"
                    class="px-6 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                  >
                    <svg v-if="saving" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span>{{ saving ? 'Saving...' : 'Save Edited Version' }}</span>
                  </button>
                </div>
              </div>
            </Transition>
          </div>
        </div>
      </Transition>
    </Teleport>
  </template>
  
  <script setup>
  import { ref, watch } from 'vue'
  import { router } from '@inertiajs/vue3'
  
  const props = defineProps({
    show: Boolean,
    document: Object,
    applicationId: Number,
  })
  
  const emit = defineEmits(['close', 'saved'])
  
  const fields = ref({})
  const loading = ref(false)
  const saving = ref(false)
  const error = ref(null)
  const currentPage = ref(1)
  
  // Watch for modal opening
  watch(() => props.show, (newValue) => {
    if (newValue && props.document) {
      error.value = null
      loadPdfFields()
    }
  })
  
  const loadPdfFields = async () => {
    loading.value = true
    error.value = null
    
    try {
      const response = await fetch(
        `/applications/${props.applicationId}/documents/${props.document.id}/pdf-fields`
      )
      
      // Check if response is actually JSON
      const contentType = response.headers.get('content-type')
      if (!contentType || !contentType.includes('application/json')) {
        // Backend returned HTML (error page)
        const text = await response.text()
        console.error('Backend returned non-JSON response:', text)
        
        if (response.status === 500) {
          error.value = 'Server error: pdftk may not be installed or accessible. Please contact support.'
        } else if (response.status === 403) {
          error.value = 'You do not have permission to edit this document.'
        } else if (response.status === 404) {
          error.value = 'Document not found.'
        } else {
          error.value = `Server error (${response.status}). Please try again or contact support.`
        }
        return
      }
      
      const data = await response.json()
      
      if (data.success) {
        // Organize fields by page
        const fieldsByPage = {}
        Object.entries(data.fields).forEach(([page, pageFields]) => {
          fieldsByPage[page] = {}
          pageFields.forEach(field => {
            fieldsByPage[page][field.name] = field.value || ''
          })
        })
        fields.value = fieldsByPage
        
        // Set to first page
        if (Object.keys(fieldsByPage).length > 0) {
          currentPage.value = parseInt(Object.keys(fieldsByPage)[0])
        }
      } else {
        error.value = data.message || 'Failed to load PDF fields'
      }
    } catch (err) {
      console.error('Failed to load PDF fields:', err)
      error.value = 'Failed to load PDF fields. Please try again.'
    } finally {
      loading.value = false
    }
  }
  
  const handleSave = async () => {
    // Flatten all fields into single object
    const allFields = {}
    Object.values(fields.value).forEach(pageFields => {
      Object.assign(allFields, pageFields)
    })
  
    saving.value = true
    try {
      const response = await fetch(
        `/applications/${props.applicationId}/documents/${props.document.id}/save-pdf-edits`,
        {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          },
          body: JSON.stringify({ field_values: allFields })
        }
      )
  
      const data = await response.json()
      
      if (data.success) {
        alert('✅ Document edited successfully! A new version has been created.')
        emit('saved')
        emit('close')
        
        // Reload the page to show the new version
        router.reload({ preserveScroll: true })
      } else {
        alert('❌ Failed to save: ' + data.message)
      }
    } catch (err) {
      console.error('Failed to save edits:', err)
      alert('❌ Failed to save edits. Please try again.')
    } finally {
      saving.value = false
    }
  }
  
  const formatFieldName = (fieldName) => {
    // Convert field names like "merchant_name" to "Merchant Name"
    return fieldName
      .split('_')
      .map(word => word.charAt(0).toUpperCase() + word.slice(1))
      .join(' ')
  }
  </script>
  
  <style scoped>
  /* Custom scrollbar styling */
  .custom-scrollbar::-webkit-scrollbar {
    width: 8px;
  }
  
  .custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 4px;
  }
  
  .custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(236, 72, 153, 0.5);
    border-radius: 4px;
  }
  
  .custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(236, 72, 153, 0.7);
  }
  </style>