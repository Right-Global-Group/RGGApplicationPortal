<template>
    <modal @close="$emit('close')" max-width="2xl">
      <div class="p-6">
        <h2 class="text-2xl font-bold text-white mb-6">Submit Contract to CardStream</h2>
        
        <!-- Info Box -->
        <div class="mb-6 p-4 bg-green-900/20 border border-green-700/30 rounded-lg">
          <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-green-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <div class="flex-1">
              <p class="text-sm text-green-300">
                Both parties have signed the contract. You can now submit this application to CardStream for processing.
              </p>
            </div>
          </div>
        </div>
  
        <!-- Contract Summary -->
        <div class="mb-6 bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
          <h3 class="font-semibold text-white mb-3">Contract Summary</h3>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between">
              <span class="text-gray-400">Application:</span>
              <span class="text-white font-medium">{{ applicationName }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Account:</span>
              <span class="text-white font-medium">{{ accountName }}</span>
            </div>
            <div class="flex justify-between">
              <span class="text-gray-400">Gateway Provider:</span>
              <span class="text-white font-medium">CardStream</span>
            </div>
          </div>
        </div>
  
        <!-- Signing Status -->
        <div v-if="recipientStatus && recipientStatus.length > 0" class="mb-6 bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
          <h3 class="font-semibold text-white mb-3">Signing Status</h3>
          <div class="space-y-2">
            <div
              v-for="(recipient, index) in recipientStatus"
              :key="index"
              class="flex items-center justify-between p-2 bg-dark-800 rounded"
            >
              <div class="flex items-center gap-2">
                <svg v-if="['completed', 'signed'].includes(recipient.status)" class="w-5 h-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <svg v-else class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                </svg>
                <div>
                  <div class="text-white text-sm">{{ recipient.name }}</div>
                  <div class="text-gray-400 text-xs">{{ recipient.email }}</div>
                </div>
              </div>
              <span class="text-xs px-2 py-1 rounded" :class="getStatusClass(recipient.status)">
                {{ formatStatus(recipient.status) }}
              </span>
            </div>
          </div>
        </div>
  
        <!-- Warning -->
        <div class="mb-6 p-4 bg-yellow-900/20 border border-yellow-700/30 rounded-lg">
          <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-yellow-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
            </svg>
            <div class="flex-1">
              <p class="text-sm text-yellow-300">
                <strong>Important:</strong> Submitting this contract will send the application to CardStream for processing. This action cannot be undone.
              </p>
            </div>
          </div>
        </div>
  
        <!-- Action Buttons -->
        <div class="flex gap-3">
          <button
            @click="$emit('close')"
            class="flex-1 px-4 py-2 text-gray-400 hover:text-gray-300 border border-gray-700 hover:border-gray-600 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            @click="submitToCardStream"
            :disabled="submitting"
            class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg transition-colors font-medium flex items-center justify-center gap-2"
          >
            <svg v-if="submitting" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span v-if="submitting">Submitting...</span>
            <span v-else>Submit to CardStream</span>
          </button>
        </div>
      </div>
    </modal>
  </template>
  
  <script>
  import Modal from '@/Shared/Modal.vue'
  
  export default {
    components: { Modal },
    props: {
      applicationId: {
        type: Number,
        required: true,
      },
      applicationName: {
        type: String,
        required: true,
      },
      accountName: {
        type: String,
        required: true,
      },
      recipientStatus: {
        type: Array,
        default: () => [],
      },
    },
    data() {
      return {
        submitting: false,
      }
    },
    methods: {
      submitToCardStream() {
        if (confirm('Are you sure you want to submit this contract to CardStream? This action cannot be undone.')) {
          this.submitting = true
          this.$inertia.post(`/applications/${this.applicationId}/submit-to-cardstream`, {}, {
            onFinish: () => {
              this.submitting = false
              this.$emit('close')
            }
          })
        }
      },
      getStatusClass(status) {
        const classes = {
          'completed': 'bg-green-900/50 text-green-300',
          'signed': 'bg-green-900/50 text-green-300',
          'sent': 'bg-blue-900/50 text-blue-300',
          'delivered': 'bg-yellow-900/50 text-yellow-300',
        }
        return classes[status] || 'bg-gray-700 text-gray-300'
      },
      formatStatus(status) {
        return status.charAt(0).toUpperCase() + status.slice(1)
      },
    },
  }
  </script>