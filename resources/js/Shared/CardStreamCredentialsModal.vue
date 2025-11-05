<template>
  <teleport to="body">
    <transition
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4"
        @click.self="close"
      >
        <transition
          enter-active-class="transition-all duration-200"
          leave-active-class="transition-all duration-200"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-95"
        >
          <div
            v-if="show"
            class="bg-dark-800 rounded-xl shadow-2xl border border-primary-800/30 w-full max-w-2xl max-h-[90vh] overflow-y-auto"
          >
            <!-- Header -->
            <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30 flex items-center justify-between">
              <h2 class="text-xl font-bold text-magenta-400">
                {{ existingCredentials ? 'Update' : 'Enter' }} CardStream Credentials
              </h2>
              <button
                @click="close"
                class="text-gray-400 hover:text-gray-300 transition-colors"
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Content -->
            <div class="p-6">
              <div class="mb-6">
                <p class="text-gray-300 mb-4">
                  Enter the CardStream credentials to complete the gateway integration.
                </p>
                <div class="bg-blue-900/20 border border-blue-700/30 rounded-lg p-4 mb-6">
                  <p class="text-sm text-blue-300">
                    <strong>Note:</strong> These credentials are needed to complete the gateway integration process.
                  </p>
                </div>
              </div>

              <!-- CardStream Username -->
              <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">
                  CardStream Username <span class="text-red-400">*</span>
                </label>
                <input
                  v-model="form.cardstream_username"
                  type="text"
                  placeholder="username"
                  class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 placeholder-gray-500 focus:outline-none focus:border-magenta-500 transition-colors"
                />
                <p v-if="form.errors.cardstream_username" class="mt-1 text-sm text-red-400">{{ form.errors.cardstream_username }}</p>
              </div>

              <!-- CardStream Password -->
              <div class="mb-4">
                <label class="block text-gray-300 font-medium mb-2">
                  CardStream Password <span class="text-red-400">*</span>
                </label>
                <input
                  v-model="form.cardstream_password"
                  type="password"
                  placeholder="••••••••"
                  class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 placeholder-gray-500 focus:outline-none focus:border-magenta-500 transition-colors"
                />
                <p v-if="form.errors.cardstream_password" class="mt-1 text-sm text-red-400">{{ form.errors.cardstream_password }}</p>
              </div>

              <!-- Merchant ID -->
              <div class="mb-6">
                <label class="block text-gray-300 font-medium mb-2">
                  Merchant ID <span class="text-red-400">*</span>
                </label>
                <input
                  v-model="form.cardstream_merchant_id"
                  type="text"
                  placeholder="Merchant ID"
                  class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 placeholder-gray-500 focus:outline-none focus:border-magenta-500 transition-colors"
                />
                <p v-if="form.errors.cardstream_merchant_id" class="mt-1 text-sm text-red-400">{{ form.errors.cardstream_merchant_id }}</p>
              </div>

              <!-- Send Account Reminder Section (Only for Users/Admins) -->
              <div class="mb-6 p-4 bg-yellow-900/20 border border-yellow-700/30 rounded-lg">
                <div class="flex items-start gap-3">
                  <input
                    id="send-cardstream-reminder"
                    v-model="sendReminder"
                    type="checkbox"
                    class="mt-1 w-4 h-4 text-magenta-600 bg-dark-900 border-primary-800/30 rounded focus:ring-magenta-500"
                  />
                  <div class="flex-1">
                    <label for="send-cardstream-reminder" class="text-yellow-300 font-medium cursor-pointer">
                      Send reminder email to account
                    </label>
                    <p class="text-sm text-gray-400 mt-1">
                      Notify the account about their CardStream credentials and schedule recurring reminders
                    </p>
                  </div>
                </div>

                <div v-if="sendReminder" class="mt-4">
                  <label class="block text-gray-300 font-medium mb-2">Reminder Frequency</label>
                  <select
                    v-model="form.reminder_interval"
                    class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 focus:outline-none focus:border-magenta-500 transition-colors"
                  >
                    <option value="1_day">Every 1 day</option>
                    <option value="3_days">Every 3 days</option>
                    <option value="1_week">Every week</option>
                    <option value="2_weeks">Every 2 weeks</option>
                    <option value="1_month">Every month</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Footer -->
            <div class="px-6 py-4 bg-dark-900/60 border-t border-primary-800/40 flex items-center justify-between">
              <button
                @click="close"
                type="button"
                class="px-4 py-2 text-gray-400 hover:text-gray-300 transition-colors"
              >
                Cancel
              </button>
              <button
                @click="submit"
                :disabled="form.processing"
                class="px-6 py-2 bg-gradient-to-r from-primary-600 to-magenta-600 hover:from-primary-500 hover:to-magenta-500 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg transition-colors flex items-center gap-2"
              >
                <svg
                  v-if="form.processing"
                  class="animate-spin w-4 h-4"
                  fill="none"
                  viewBox="0 0 24 24"
                >
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span v-if="form.processing">Saving...</span>
                <span v-else>
                  {{ sendReminder ? 'Save & Send Reminder' : 'Save Credentials' }}
                </span>
              </button>
            </div>
          </div>
        </transition>
      </div>
    </transition>
  </teleport>
</template>

<script>
export default {
  name: 'CardStreamCredentialsModal',
  props: {
    show: {
      type: Boolean,
      default: false,
    },
    applicationId: {
      type: Number,
      required: true,
    },
    existingCredentials: {
      type: Object,
      default: null,
    },
  },
  data() {
    return {
      sendReminder: false,
      form: this.$inertia.form({
        cardstream_username: this.existingCredentials?.cardstream_username || '',
        cardstream_password: this.existingCredentials?.cardstream_password || '',
        cardstream_merchant_id: this.existingCredentials?.cardstream_merchant_id || '',
        send_reminder: false,
        reminder_interval: '3_days',
      }),
    }
  },
  watch: {
    show(newVal) {
      if (newVal && this.existingCredentials) {
        this.form.cardstream_username = this.existingCredentials.cardstream_username || ''
        this.form.cardstream_password = this.existingCredentials.cardstream_password || ''
        this.form.cardstream_merchant_id = this.existingCredentials.cardstream_merchant_id || ''
      }
    },
    sendReminder(newVal) {
      this.form.send_reminder = newVal
    },
  },
  methods: {
    close() {
      this.$emit('close')
      this.sendReminder = false
      if (!this.existingCredentials) {
        this.form.reset()
      }
    },
    submit() {
      this.form.post(`/applications/${this.applicationId}/send-cardstream-credentials`, {
        onSuccess: () => {
          this.close()
          // Reload the page to show updated credentials
          this.$inertia.reload({ only: ['application'] })
        },
      })
    },
  },
}
</script>