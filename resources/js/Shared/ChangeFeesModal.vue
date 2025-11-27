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
                <h3 class="text-xl font-bold text-magenta-400">Change Fee Structure</h3>
                <button @click="closeModal" class="text-gray-400 hover:text-gray-300 transition-colors">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Body -->
            <form @submit.prevent="submitCreateNew">
              <div class="px-6 py-6 space-y-4">
                <div class="bg-yellow-900/20 border border-yellow-700/50 rounded-lg p-4 mb-4">
                  <p class="text-yellow-300 text-sm">
                    <strong>Note:</strong> You can either update the fees directly on this application, or create a new application with the updated fee structure that requires account confirmation.
                  </p>
                </div>

                <!-- Application Name Field (only for creating new) -->
                <div class="col-span-2">
                  <text-input 
                    v-model="form.name" 
                    :error="form.errors.name" 
                    label="Application Name (for new application)" 
                    class="w-full"
                  />
                  <p class="text-sm text-gray-400 mt-1">This will be the name if you create a new application. Leave as-is to update current application.</p>
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <text-input 
                    v-model="form.scaling_fee" 
                    :error="form.errors.scaling_fee" 
                    type="number" 
                    step="0.01" 
                    label="Scaling fee (£)" 
                    required
                  />
                  
                  <text-input 
                    v-model="form.transaction_percentage" 
                    :error="form.errors.transaction_percentage" 
                    type="number" 
                    step="0.01" 
                    label="Transaction Percentage (%)" 
                    required
                  />
                  
                  <text-input 
                    v-model="form.transaction_fixed_fee" 
                    :error="form.errors.transaction_fixed_fee" 
                    type="number" 
                    step="0.01" 
                    label="Fixed Fee Per Transaction (£)" 
                    required
                  />
                  
                  <text-input 
                    v-model="form.monthly_fee" 
                    :error="form.errors.monthly_fee" 
                    type="number" 
                    step="0.01" 
                    label="Monthly Fee (£)" 
                    required
                  />
                  
                  <text-input 
                    v-model="form.monthly_minimum" 
                    :error="form.errors.monthly_minimum" 
                    type="number" 
                    step="0.01" 
                    label="Monthly Minimum (£)" 
                    required
                  />
                  
                  <text-input 
                    v-model="form.service_fee" 
                    :error="form.errors.service_fee" 
                    type="number" 
                    step="0.01" 
                    label="Setup Fee (£)" 
                    required
                  />
                </div>
              </div>

              <!-- Footer -->
              <div class="px-6 py-4 bg-dark-900/60 border-t border-primary-800/40 flex justify-between gap-3">
                <button 
                  type="button" 
                  @click="closeModal" 
                  class="px-4 py-2 text-gray-400 hover:text-gray-300 transition-colors"
                >
                  Cancel
                </button>
                <div class="flex gap-3">
                  <loading-button 
                    :loading="form.processing" 
                    class="btn-secondary" 
                    type="button"
                    @click="submitUpdateCurrent"
                  >
                    Update Current Application
                  </loading-button>
                  <loading-button 
                    :loading="form.processing" 
                    class="btn-primary" 
                    type="submit"
                  >
                    Create New Application
                  </loading-button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script>
import TextInput from '@/Shared/TextInput.vue'
import LoadingButton from '@/Shared/LoadingButton.vue'

export default {
  components: {
    TextInput,
    LoadingButton,
  },
  props: {
    show: {
      type: Boolean,
      default: false,
    },
    application: {
      type: Object,
      required: true,
    },
  },
  emits: ['close', 'submit'],
  data() {
    return {
      form: this.$inertia.form({
        name: this.application.name,
        scaling_fee: this.application.scaling_fee,
        transaction_percentage: this.application.transaction_percentage,
        transaction_fixed_fee: this.application.transaction_fixed_fee,
        monthly_fee: this.application.monthly_fee,
        monthly_minimum: this.application.monthly_minimum,
        service_fee: this.application.service_fee,
      }),
    }
  },
  watch: {
    show(newVal) {
      if (newVal) {
        // Reset form when modal opens
        this.form.name = this.application.name
        this.form.scaling_fee = this.application.scaling_fee
        this.form.transaction_percentage = this.application.transaction_percentage
        this.form.transaction_fixed_fee = this.application.transaction_fixed_fee
        this.form.monthly_fee = this.application.monthly_fee
        this.form.monthly_minimum = this.application.monthly_minimum
        this.form.service_fee = this.application.service_fee
        this.form.clearErrors()
      }
    },
  },
  methods: {
    closeModal() {
      this.$emit('close')
    },
    submitUpdateCurrent() {
      // Check if anything changed (excluding name since we're updating current)
      const hasChanges = 
        parseFloat(this.form.scaling_fee) !== parseFloat(this.application.scaling_fee) ||
        parseFloat(this.form.transaction_percentage) !== parseFloat(this.application.transaction_percentage) ||
        parseFloat(this.form.transaction_fixed_fee) !== parseFloat(this.application.transaction_fixed_fee) ||
        parseFloat(this.form.monthly_fee) !== parseFloat(this.application.monthly_fee) ||
        parseFloat(this.form.monthly_minimum) !== parseFloat(this.application.monthly_minimum) ||
        parseFloat(this.form.service_fee) !== parseFloat(this.application.service_fee)

      if (!hasChanges) {
        alert('No changes detected. Please modify at least one fee field.')
        return
      }

      this.form.put(`/applications/${this.application.id}/update-fees`, {
        onSuccess: () => {
          this.$emit('close')
        },
      })
    },
    submitCreateNew() {
      // Check if anything changed (including name)
      const hasChanges = 
        this.form.name !== this.application.name ||
        parseFloat(this.form.scaling_fee) !== parseFloat(this.application.scaling_fee) ||
        parseFloat(this.form.transaction_percentage) !== parseFloat(this.application.transaction_percentage) ||
        parseFloat(this.form.transaction_fixed_fee) !== parseFloat(this.application.transaction_fixed_fee) ||
        parseFloat(this.form.monthly_fee) !== parseFloat(this.application.monthly_fee) ||
        parseFloat(this.form.monthly_minimum) !== parseFloat(this.application.monthly_minimum) ||
        parseFloat(this.form.service_fee) !== parseFloat(this.application.service_fee)

      if (!hasChanges) {
        alert('No changes detected. Please modify at least one field.')
        return
      }

      this.form.post(`/applications/${this.application.id}/change-fees`, {
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