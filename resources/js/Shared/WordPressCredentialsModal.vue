<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @click.self="close">
      <div class="flex items-center justify-center min-h-screen px-4 py-8">
        <div class="fixed inset-0 bg-black opacity-75" @click="close"></div>
        
        <div class="relative bg-dark-800 rounded-xl shadow-2xl w-full max-w-2xl border border-primary-700">
          <div class="px-6 py-4 bg-gradient-to-r from-green-900/50 to-emerald-900/50 border-b border-primary-700 flex justify-between items-center">
            <div>
              <h3 class="text-xl font-bold text-white">WordPress Integration Details</h3>
              <p class="text-sm text-gray-400 mt-1">We need your WordPress admin credentials</p>
            </div>
            <button @click="close" class="text-gray-400 hover:text-white transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
  
          <form @submit.prevent="submit" class="p-6 space-y-6">
            <!-- Info Box -->
            <div class="bg-blue-900/20 border border-blue-700/30 rounded-lg p-4">
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1">
                  <div class="text-blue-300 font-semibold mb-1">Why We Need This</div>
                  <div class="text-sm text-gray-400">
                    We'll use these credentials to integrate your payment gateway directly on your WordPress site. After setup, you can change your password if desired.
                  </div>
                </div>
              </div>
            </div>
  
            <!-- WordPress URL -->
            <div>
              <label class="block text-sm font-semibold text-gray-300 mb-2">
                WordPress Website URL <span class="text-red-400">*</span>
              </label>
              <input
                v-model="form.wordpress_url"
                type="url"
                required
                class="w-full px-4 py-2 bg-dark-900 text-white border border-primary-700 rounded-lg focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                placeholder="https://yourwebsite.com"
              />
              <p v-if="errors.wordpress_url" class="text-red-400 text-sm mt-1">{{ errors.wordpress_url }}</p>
              <p class="text-gray-500 text-xs mt-1">Your full website URL including https://</p>
            </div>
  
            <!-- Admin Email -->
            <div>
              <label class="block text-sm font-semibold text-gray-300 mb-2">
                WordPress Admin Email <span class="text-red-400">*</span>
              </label>
              <input
                v-model="form.wordpress_admin_email"
                type="email"
                required
                class="w-full px-4 py-2 bg-dark-900 text-white border border-primary-700 rounded-lg focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                placeholder="admin@yourwebsite.com"
              />
              <p v-if="errors.wordpress_admin_email" class="text-red-400 text-sm mt-1">{{ errors.wordpress_admin_email }}</p>
              <p class="text-gray-500 text-xs mt-1">The email address used for your WordPress admin account</p>
            </div>
  
            <!-- Admin Username -->
            <div>
              <label class="block text-sm font-semibold text-gray-300 mb-2">
                WordPress Admin Username <span class="text-red-400">*</span>
              </label>
              <input
                v-model="form.wordpress_admin_username"
                type="text"
                required
                class="w-full px-4 py-2 bg-dark-900 text-white border border-primary-700 rounded-lg focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500/20"
                placeholder="admin"
              />
              <p v-if="errors.wordpress_admin_username" class="text-red-400 text-sm mt-1">{{ errors.wordpress_admin_username }}</p>
              <p class="text-gray-500 text-xs mt-1">Your WordPress admin username</p>
            </div>
  
            <!-- Security Note -->
            <div class="bg-green-900/20 border border-green-700/30 rounded-lg p-4">
              <div class="flex items-start gap-3">
                <svg class="w-5 h-5 text-green-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
                <div class="flex-1 text-sm text-gray-400">
                  🔒 Your credentials are encrypted and securely stored. We only need temporary access for integration.
                </div>
              </div>
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
                class="flex-1 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2"
              >
                <svg v-if="processing" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>{{ processing ? 'Submitting...' : 'Submit Details' }}</span>
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
    },
    emits: ['close', 'saved'],
    data() {
      return {
        processing: false,
        errors: {},
        form: {
          wordpress_url: '',
          wordpress_admin_email: '',
          wordpress_admin_username: '',
        },
      }
    },
    methods: {
      async submit() {
        this.processing = true
        this.errors = {}
  
        try {
          const response = await fetch(`/applications/${this.applicationId}/wordpress-credentials`, {
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
            throw new Error(data.message || 'Failed to save WordPress credentials')
          }
  
          this.$emit('saved')
          this.close()
          
          // Reload page to show updated status
          window.location.reload()
        } catch (error) {
          console.error('Save error:', error)
          alert(error.message || 'Failed to save WordPress credentials')
        } finally {
          this.processing = false
        }
      },
      close() {
        this.form = {
          wordpress_url: '',
          wordpress_admin_email: '',
          wordpress_admin_username: '',
        }
        this.errors = {}
        this.$emit('close')
      },
    },
  }
  </script>