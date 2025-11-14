<template>
    <modal @close="$emit('close')" max-width="2xl">
      <div class="p-6">
        <h2 class="text-2xl font-bold text-white mb-6">Send Contract Link</h2>
        
        <!-- Info Box -->
        <div class="mb-6 p-4 bg-blue-900/20 border border-blue-700/30 rounded-lg">
          <div class="flex items-start gap-2">
            <svg class="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
            </svg>
            <div class="flex-1">
              <p class="text-sm text-blue-300">
                Send a reminder email to the account holder to review and sign the contract.
              </p>
            </div>
          </div>
        </div>
  
        <!-- Send Now Section -->
        <div class="mb-6 p-4 bg-dark-900/50 border border-primary-800/30 rounded-lg">
          <div class="flex items-start justify-between mb-3">
            <div class="flex-1">
              <h3 class="font-semibold text-white mb-1">Send Contract Link</h3>
              <p class="text-sm text-gray-400">
                Sends the contract reminder immediately to the account holder.
              </p>
            </div>
          </div>
          <button
            @click="sendNow"
            class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors font-medium"
          >
            Send Contract Link Now
          </button>
        </div>
  
        <!-- Divider -->
        <div class="border-t border-primary-800/30 mb-6"></div>
  
        <!-- Schedule Reminders Section -->
        <div class="p-4 bg-dark-900/50 border border-primary-800/30 rounded-lg">
          <div class="mb-3">
            <h3 class="font-semibold text-white mb-1">Schedule Reminder Emails</h3>
            <p class="text-sm text-gray-400">
              Automatically send reminder emails at the selected interval.
            </p>
          </div>
  
          <!-- Active Reminder Warning -->
          <div v-if="hasActiveReminder" class="mb-4 p-3 bg-yellow-900/20 border border-yellow-700/30 rounded-lg">
            <p class="text-sm text-yellow-400">
              ⚠️ An active reminder exists. Setting a new one will replace it.
            </p>
          </div>
  
          <!-- Interval Selector -->
          <div class="space-y-3">
            <select
              v-model="interval"
              class="w-full px-4 py-2 bg-dark-800 border border-primary-800/30 rounded-lg text-gray-300 focus:outline-none focus:border-magenta-500"
            >
              <option value="">Select reminder interval...</option>
              <option value="1_day">Every 1 day</option>
              <option value="3_days">Every 3 days</option>
              <option value="1_week">Every week</option>
              <option value="2_weeks">Every 2 weeks</option>
            </select>
  
            <button
              @click="setReminder"
              :disabled="!interval"
              class="w-full px-4 py-2 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg transition-colors font-medium"
            >
              Set Reminder
            </button>
          </div>
        </div>
  
        <!-- Cancel Button -->
        <div class="mt-6 flex justify-end">
          <button
            @click="$emit('close')"
            class="px-4 py-2 text-gray-400 hover:text-gray-300 transition-colors"
          >
            Cancel
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
      hasActiveReminder: {
        type: Boolean,
        default: false,
      },
    },
    data() {
      return {
        interval: '',
      }
    },
    methods: {
      sendNow() {
        if (confirm('Send contract reminder now?')) {
          this.$inertia.post(`/applications/${this.applicationId}/send-contract-reminder`, {}, {
            onSuccess: () => {
              this.$emit('close')
            }
          })
        }
      },
      setReminder() {
        if (!this.interval) {
          alert('Please select a reminder interval.')
          return
        }
  
        const intervalText = this.formatInterval(this.interval)
        if (confirm(`Schedule reminder emails to be sent ${intervalText.toLowerCase()}?`)) {
          this.$inertia.post(`/applications/${this.applicationId}/set-contract-reminder`, {
            interval: this.interval,
          }, {
            onSuccess: () => {
              this.$emit('close')
            }
          })
        }
      },
      formatInterval(interval) {
        const intervals = {
          '1_day': 'Every 1 day',
          '3_days': 'Every 3 days',
          '1_week': 'Every week',
          '2_weeks': 'Every 2 weeks',
        }
        return intervals[interval] || interval
      },
    },
  }
  </script>