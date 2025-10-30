<template>
    <modal @close="$emit('close')" max-width="2xl">
      <div class="p-6">
        <h2 class="text-2xl font-bold text-white mb-6">Send Account Credentials</h2>
  
        <!-- Send Now Section -->
        <div class="mb-6 p-4 bg-dark-900/50 border border-primary-800/30 rounded-lg">
          <div class="flex items-start justify-between mb-3">
            <div class="flex-1">
              <h3 class="font-semibold text-white mb-1">Send Credentials Now</h3>
              <p class="text-sm text-gray-400">
                Generates a new password and emails login credentials immediately to the account holder.
              </p>
            </div>
          </div>
          <button
            @click="sendNow"
            class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors font-medium"
          >
            Send Credentials Now
          </button>
        </div>
  
        <!-- Divider -->
        <div class="border-t border-primary-800/30 mb-6"></div>
  
        <!-- Schedule Reminders Section -->
        <div class="p-4 bg-dark-900/50 border border-primary-800/30 rounded-lg">
          <div class="mb-3">
            <h3 class="font-semibold text-white mb-1">Schedule Credential Reminder Emails</h3>
            <p class="text-sm text-gray-400">
              Automatically send reminder emails at the selected interval. <strong>Does not send immediately.</strong>
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
              <option value="1_month">Every month</option>
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
      accountId: {
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
        if (confirm('Send login credentials to this account now?')) {
          this.$inertia.post(`/accounts/${this.accountId}/send-credentials`, {}, {
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
        if (confirm(`Schedule credentials to be sent ${intervalText.toLowerCase()}? No email will be sent immediately.`)) {
          this.$inertia.post(`/accounts/${this.accountId}/set-credentials-reminder`, {
            interval: this.interval
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
          '1_month': 'Every month',
        }
        return intervals[interval] || interval
      },
    },
  }
  </script>