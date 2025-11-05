<template>
    <modal @close="$emit('close')" max-width="2xl">
      <div class="p-6">
        <h2 class="text-2xl font-bold text-white mb-6">Send Message to Administrator</h2>
        
        <!-- Message Input -->
        <div class="mb-6">
          <label class="block text-gray-300 font-medium mb-2">
            Your Message
          </label>
          <textarea
            v-model="message"
            placeholder="Type your message here..."
            rows="5"
            class="w-full px-4 py-3 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 placeholder-gray-500 focus:outline-none focus:border-magenta-500 resize-none"
          ></textarea>
          <p class="text-sm text-gray-400 mt-2">
            This message will be sent to the administrator managing your application.
          </p>
        </div>
  
        <!-- Send Now Section -->
        <div class="mb-6 p-4 bg-dark-900/50 border border-primary-800/30 rounded-lg">
          <div class="flex items-start justify-between mb-3">
            <div class="flex-1">
              <h3 class="font-semibold text-white mb-1">Send Message Now</h3>
              <p class="text-sm text-gray-400">
                Sends the message immediately to the administrator.
              </p>
            </div>
          </div>
          <button
            @click="sendNow"
            :disabled="!canSend"
            class="w-full px-4 py-2 bg-purple-600 hover:bg-purple-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg transition-colors font-medium"
          >
            Send Message Now
          </button>
        </div>
  
        <!-- Divider -->
        <div class="border-t border-primary-800/30 mb-6"></div>
  
        <!-- Schedule Reminders Section -->
        <div class="p-4 bg-dark-900/50 border border-primary-800/30 rounded-lg">
          <div class="mb-3">
            <h3 class="font-semibold text-white mb-1">Schedule Reminder Emails</h3>
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
              :disabled="!canSetReminder"
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
        message: '',
        interval: '',
      }
    },
    computed: {
      canSend() {
        return this.message.trim().length > 0
      },
      canSetReminder() {
        return this.interval && this.message.trim().length > 0
      }
    },
    methods: {
      sendNow() {
        if (!this.canSend) {
          alert('Please enter a message.')
          return
        }
  
        if (confirm('Send message to administrator now?')) {
          this.$inertia.post(`/applications/${this.applicationId}/send-account-message`, {
            message: this.message,
          }, {
            onSuccess: () => {
              this.$emit('close')
            }
          })
        }
      },
      setReminder() {
        if (!this.canSetReminder) {
          if (!this.message.trim()) {
            alert('Please enter a message.')
          } else {
            alert('Please select a reminder interval.')
          }
          return
        }
  
        const intervalText = this.formatInterval(this.interval)
        if (confirm(`Schedule reminder emails to be sent ${intervalText.toLowerCase()}? No email will be sent immediately.`)) {
          this.$inertia.post(`/applications/${this.applicationId}/set-account-message-reminder`, {
            message: this.message,
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
          '1_month': 'Every month',
        }
        return intervals[interval] || interval
      },
    },
  }
  </script>