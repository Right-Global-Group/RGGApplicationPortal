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
                <h3 class="text-xl font-bold text-magenta-400">Submit to CardStream</h3>
                <button @click="closeModal" class="text-gray-400 hover:text-gray-300 transition-colors">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Body -->
            <form @submit.prevent="submitToCardStream">
              <div class="px-6 py-6 space-y-4">
                <!-- Info Box -->
                <div class="bg-blue-900/20 border border-blue-700/50 rounded-lg p-4">
                  <p class="text-blue-300 text-sm mb-2">
                    <strong>Ready to Submit:</strong>
                  </p>
                  <ul class="text-sm text-gray-300 space-y-1 ml-4">
                    <li>✓ All documents uploaded and verified</li>
                    <li>✓ Contract signed by all parties</li>
                    <li>✓ Application approved</li>
                  </ul>
                </div>

                <!-- Account Details -->
                <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4 space-y-3">
                  <h4 class="text-magenta-400 font-semibold mb-3">Account Details</h4>
                  
                  <div>
                    <label class="text-sm text-gray-400">Account Name</label>
                    <p class="text-white font-medium">{{ accountName }}</p>
                  </div>

                  <div>
                    <label class="text-sm text-gray-400">Email Address</label>
                    <p class="text-white font-medium">{{ accountEmail }}</p>
                  </div>

                  <div>
                    <label class="text-sm text-gray-400">Mobile Number</label>
                    <p class="text-white font-medium">{{ accountMobile || 'Not provided' }}</p>
                  </div>
                </div>

                <!-- Payout Options -->
                <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
                  <h4 class="text-magenta-400 font-semibold mb-3">Payout Options</h4>
                  <label class="block text-sm text-gray-400 mb-2">
                    How often should payouts be processed?
                  </label>
                  <select 
                    v-model="form.payout_option"
                    class="w-full bg-dark-700 border border-primary-700/50 rounded-lg px-4 py-2 text-white focus:border-magenta-500 focus:ring-2 focus:ring-magenta-500/20"
                    required
                  >
                    <option value="daily">Daily (T+1)</option>
                    <option value="every_3_days">Every 3 Days (T+3)</option>
                  </select>
                  <p class="text-xs text-gray-500 mt-2">
                    <span v-if="form.payout_option === 'daily'">Funds will be transferred the next business day</span>
                    <span v-else>Funds will be transferred every 3 business days</span>
                  </p>
                </div>

                <!-- Signing Status -->
                <div v-if="recipientStatus && recipientStatus.length > 0" class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
                  <h4 class="text-magenta-400 font-semibold mb-3">Contract Signing Status</h4>
                  <div class="space-y-2">
                    <div 
                      v-for="(recipient, index) in recipientStatus" 
                      :key="index"
                      class="flex items-center justify-between p-2 bg-dark-800 rounded"
                    >
                      <span class="text-gray-300">{{ recipient.name }}</span>
                      <span 
                        class="px-2 py-1 rounded text-xs font-semibold"
                        :class="{
                          'bg-green-900/50 text-green-300': ['completed', 'signed'].includes(recipient.status),
                          'bg-blue-900/50 text-blue-300': recipient.status === 'delivered',
                          'bg-yellow-900/50 text-yellow-300': recipient.status === 'sent',
                        }"
                      >
                        {{ formatStatus(recipient.status) }}
                      </span>
                    </div>
                  </div>
                </div>

                <!-- Warning -->
                <div class="bg-yellow-900/20 border border-yellow-700/50 rounded-lg p-4">
                  <p class="text-yellow-300 text-sm">
                    <strong>Note:</strong> This will send all documents and contract details to CardStream for processing. Make sure all information is correct before submitting.
                  </p>
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
                <loading-button 
                  :loading="form.processing" 
                  class="btn-primary" 
                  type="submit"
                >
                  Submit to CardStream
                </loading-button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script>
import LoadingButton from '@/Shared/LoadingButton.vue'

export default {
  components: {
    LoadingButton,
  },
  props: {
    show: {
      type: Boolean,
      default: false,
    },
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
    accountEmail: {
      type: String,
      required: true,
    },
    accountMobile: {
      type: String,
      default: null,
    },
    recipientStatus: {
      type: Array,
      default: () => [],
    },
  },
  emits: ['close'],
  data() {
    return {
      form: this.$inertia.form({
        payout_option: 'daily',
      }),
    }
  },
  watch: {
    show(newVal) {
      if (newVal) {
        // Reset form when modal opens
        this.form.payout_option = 'daily'
        this.form.clearErrors()
      }
    },
  },
  methods: {
    closeModal() {
      this.$emit('close')
    },
    submitToCardStream() {
      this.form.post(`/applications/${this.applicationId}/submit-to-cardstream`, {
        onSuccess: () => {
          this.$emit('close')
        },
      })
    },
    formatStatus(status) {
      return status.charAt(0).toUpperCase() + status.slice(1)
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