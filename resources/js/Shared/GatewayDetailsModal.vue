<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @click.self="close">
      <div class="flex items-center justify-center min-h-screen px-4 py-8">
        <div class="fixed inset-0 bg-black opacity-75" @click="close"></div>
        
        <div class="relative bg-dark-800 rounded-xl shadow-2xl w-full max-w-2xl border border-primary-700">
          <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-700 flex justify-between items-center">
            <div>
              <h3 class="text-xl font-bold text-white">Gateway Integration Details</h3>
              <p class="text-sm text-gray-400 mt-1">Enter details from {{ gatewayPartnerName }}</p>
            </div>
            <button @click="close" class="text-gray-400 hover:text-white transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
  
          <form @submit.prevent="submit" class="p-6 space-y-6">
            <!-- MID -->
            <div>
              <label class="block text-sm font-semibold text-gray-300 mb-2">
                Merchant ID (MID) <span class="text-red-400">*</span>
              </label>
              <input
                v-model="form.gateway_mid"
                type="text"
                required
                class="w-full px-4 py-2 bg-dark-900 text-white border border-primary-700 rounded-lg focus:border-magenta-500 focus:outline-none focus:ring-2 focus:ring-magenta-500/20"
                placeholder="Enter Merchant ID"
              />
              <p v-if="errors.gateway_mid" class="text-red-400 text-sm mt-1">{{ errors.gateway_mid }}</p>
            </div>
  
            <!-- API Key -->
            <div>
              <label class="block text-sm font-semibold text-gray-300 mb-2">
                API Key
              </label>
              <input
                v-model="form.api_key"
                type="text"
                class="w-full px-4 py-2 bg-dark-900 text-white border border-primary-700 rounded-lg focus:border-magenta-500 focus:outline-none focus:ring-2 focus:ring-magenta-500/20"
                placeholder="Enter API Key (if provided)"
              />
            </div>
  
            <!-- API Secret -->
            <div>
              <label class="block text-sm font-semibold text-gray-300 mb-2">
                API Secret
              </label>
              <input
                v-model="form.api_secret"
                type="password"
                class="w-full px-4 py-2 bg-dark-900 text-white border border-primary-700 rounded-lg focus:border-magenta-500 focus:outline-none focus:ring-2 focus:ring-magenta-500/20"
                placeholder="Enter API Secret (if provided)"
              />
            </div>
  
            <!-- Integration URL -->
            <div>
              <label class="block text-sm font-semibold text-gray-300 mb-2">
                Integration URL
              </label>
              <input
                v-model="form.integration_url"
                type="url"
                class="w-full px-4 py-2 bg-dark-900 text-white border border-primary-700 rounded-lg focus:border-magenta-500 focus:outline-none focus:ring-2 focus:ring-magenta-500/20"
                placeholder="https://gateway.example.com/api"
              />
            </div>
  
            <!-- Additional Notes -->
            <div>
              <label class="block text-sm font-semibold text-gray-300 mb-2">
                Additional Notes
              </label>
              <textarea
                v-model="form.additional_notes"
                rows="4"
                class="w-full px-4 py-2 bg-dark-900 text-white border border-primary-700 rounded-lg focus:border-magenta-500 focus:outline-none focus:ring-2 focus:ring-magenta-500/20"
                placeholder="Any additional setup notes or requirements..."
              ></textarea>
            </div>
  
            <!-- Action Buttons -->
            <div class="flex gap-3 pt-4">
              <button
                type="button"
                @click="close"
                class="flex-1 px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
              >
                Cancel
              </button>
              <button
                type="submit"
                :disabled="processing"
                class="flex-1 px-4 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                <svg v-if="processing" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ processing ? 'Saving...' : 'Save Gateway Details' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  export default {
    props: {
      show: Boolean,
      applicationId: Number,
      gatewayPartnerName: String,
    },
    emits: ['close', 'saved'],
    data() {
      return {
        processing: false,
        errors: {},
        form: {
          gateway_mid: '',
          api_key: '',
          api_secret: '',
          integration_url: '',
          additional_notes: '',
        },
      }
    },
    methods: {
      async submit() {
        this.processing = true
        this.errors = {}
  
        try {
          const response = await fetch(`/applications/${this.applicationId}/gateway-details`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
            },
            body: JSON.stringify(this.form),
          })
  
          const data = await response.json()
  
          if (!response.ok) {
            if (data.errors) {
              this.errors = data.errors
            }
            throw new Error(data.message || 'Failed to save gateway details')
          }
  
          this.$emit('saved')
          this.close()
          
          // Reload page to show updated status
          window.location.reload()
        } catch (error) {
          console.error('Save error:', error)
          alert(error.message || 'Failed to save gateway details')
        } finally {
          this.processing = false
        }
      },
      close() {
        this.form = {
          gateway_mid: '',
          api_key: '',
          api_secret: '',
          integration_url: '',
          additional_notes: '',
        }
        this.errors = {}
        this.$emit('close')
      },
    },
  }
  </script>