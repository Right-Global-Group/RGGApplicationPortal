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
              <form @submit.prevent="submit">
                <div class="px-6 py-6 space-y-4">
                  <div class="bg-yellow-900/20 border border-yellow-700/50 rounded-lg p-4 mb-4">
                    <p class="text-yellow-300 text-sm">
                      <strong>Note:</strong> Changing the fees will create a new application with the updated fee structure. 
                      The account will need to confirm the new fees.
                    </p>
                  </div>
  
                  <!-- Application Name Field -->
                  <div class="col-span-2">
                    <text-input 
                      v-model="form.name" 
                      :error="form.errors.name" 
                      label="Application Name" 
                      required
                      class="w-full"
                    />
                    <p class="text-sm text-gray-400 mt-1">This will be the name of the new application created with updated fees.</p>
                  </div>
  
                  <div class="grid grid-cols-2 gap-4">
                    <text-input 
                      v-model="form.setup_fee" 
                      :error="form.errors.setup_fee" 
                      type="number" 
                      step="0.01" 
                      label="Setup Fee (£)" 
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
                      label="Service Fee (£)" 
                      required
                    />
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
                    Create New Application with Updated Fees
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
          setup_fee: this.application.setup_fee,
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
          this.form.setup_fee = this.application.setup_fee
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
      submit() {
        // Check if anything changed (including name)
        const hasChanges = 
          this.form.name !== this.application.name ||
          parseFloat(this.form.setup_fee) !== parseFloat(this.application.setup_fee) ||
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