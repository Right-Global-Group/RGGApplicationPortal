<template>
    <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @click.self="$emit('close')">
      <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black opacity-75"></div>
        
        <div class="relative bg-dark-800 rounded-xl shadow-2xl max-w-2xl w-full border border-primary-700">
          <div class="px-6 py-4 bg-gradient-to-r from-magenta-900/50 to-primary-900/50 border-b border-primary-700 flex justify-between items-center">
            <h3 class="text-xl font-bold text-white">Fees Confirmation Reminder</h3>
            <button @click="$emit('close')" class="text-gray-400 hover:text-white transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
  
          <div class="p-6 space-y-6">
            <!-- Send Now Section -->
            <div>
              <h4 class="text-lg font-semibold text-white mb-3">Send Reminder Now</h4>
              <p class="text-gray-400 text-sm mb-4">
                Send an immediate reminder email to the account to confirm the application fees.
              </p>
              <button
                @click="sendNow"
                :disabled="sending"
                class="btn-primary w-full disabled:opacity-50"
              >
                {{ sending ? 'Sending...' : 'Send Fees Reminder Now' }}
              </button>
            </div>
  
            <!-- Divider -->
            <div class="border-t border-primary-700"></div>
  
            <!-- Schedule Reminders Section -->
            <div>
              <h4 class="text-lg font-semibold text-white mb-3">Schedule Recurring Reminders</h4>
              
              <!-- Active Reminder Display -->
              <div v-if="feesReminder" class="mb-4">
                <div class="flex items-center gap-3 p-4 bg-blue-900/20 border border-blue-700/30 rounded-lg">
                  <div class="flex-1">
                    <div class="text-blue-300 font-semibold">Active Reminder</div>
                    <div class="text-sm text-gray-400 mt-1">
                      Sending {{ formatInterval(feesReminder.interval) }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                      Next: {{ feesReminder.next_send_at }}
                    </div>
                  </div>
                  <button
                    @click="cancelReminder"
                    class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded transition-colors"
                  >
                    Cancel
                  </button>
                </div>
              </div>
  
              <!-- Set New Reminder -->
              <div v-else class="space-y-4">
                <p class="text-gray-400 text-sm">
                  Schedule automatic reminder emails to be sent at regular intervals until fees are confirmed.
                </p>
                
                <select
                  v-model="reminderInterval"
                  class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 focus:outline-none focus:border-magenta-500"
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
                  :disabled="!reminderInterval"
                  class="w-full px-4 py-2 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg transition-colors"
                >
                  Set Reminder Schedule
                </button>
                
                <p class="text-xs text-gray-500">
                  <strong>Note:</strong> Reminders will automatically stop once fees are confirmed.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  export default {
    props: {
      show: Boolean,
      application: Object,
      feesReminder: Object,
    },
    data() {
      return {
        reminderInterval: '',
        sending: false,
      }
    },
    methods: {
      sendNow() {
        if (confirm('Send fees confirmation reminder to the account now?')) {
          this.sending = true
          this.$inertia.post(`/applications/${this.application.id}/send-fees-reminder`, {}, {
            onFinish: () => {
              this.sending = false
              this.$emit('close')
            }
          })
        }
      },
      setReminder() {
        if (!this.reminderInterval) return
        
        if (confirm(`Schedule fees confirmation reminders ${this.formatInterval(this.reminderInterval).toLowerCase()}?`)) {
          this.$inertia.post(`/applications/${this.application.id}/set-fees-reminder`, {
            interval: this.reminderInterval,
          }, {
            onSuccess: () => {
              this.reminderInterval = ''
              this.$emit('close')
            }
          })
        }
      },
      cancelReminder() {
        if (confirm('Cancel scheduled fees confirmation reminders?')) {
          this.$inertia.post(`/applications/${this.application.id}/cancel-fees-reminder`, {}, {
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