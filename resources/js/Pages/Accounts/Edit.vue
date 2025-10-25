<template>
  <div>
    <Head :title="account.name" />
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold text-white">
        <Link class="text-magenta-400 hover:text-magenta-500 transition-colors" href="/accounts">Accounts</Link>
        <span class="text-gray-500 mx-2">/</span>
        {{ account.name }}
      </h1>
      <span 
        class="px-4 py-2 rounded-full text-sm font-semibold"
        :class="account.is_confirmed ? 'bg-green-900/50 text-green-300' : 'bg-yellow-900/50 text-yellow-300'"
      >
        {{ account.is_confirmed ? 'Confirmed' : 'Pending' }}
      </span>
    </div>

    <!-- Account Details Section -->
    <div class="max-w-3xl bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
      <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-magenta-400 font-bold text-lg">Account Details</h2>
      </div>
      <div class="p-8 space-y-4">
        <div>
          <label class="block text-gray-300 font-medium mb-2">Created By</label>
          <Link v-if="account.user_id" :href="`/users/${account.user_id}/edit`" class="inline-block px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-magenta-400 hover:text-magenta-300 hover:border-magenta-500/50 transition-colors">
            {{ account.user_name || '—' }}
          </Link>
          <div v-else class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
            {{ account.user_name || '—' }}
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-gray-300 font-medium mb-2">Credentials Sent</label>
            <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
              {{ account.credentials_sent_at || 'Not sent' }}
            </div>
          </div>
          <div>
            <label class="block text-gray-300 font-medium mb-2">First Login</label>
            <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
              {{ account.first_login_at || 'Not logged in' }}
            </div>
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-gray-300 font-medium mb-2">Created At</label>
            <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
              {{ formatDate(account.created_at) }}
            </div>
          </div>
          <div>
            <label class="block text-gray-300 font-medium mb-2">Updated At</label>
            <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
              {{ formatDate(account.updated_at) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Email Credentials Section (Admin Only) -->
    <div v-if="$page.props.auth.user.isAdmin" class="max-w-3xl bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
      <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-magenta-400 font-bold text-lg">Account Credentials</h2>
      </div>
      <div class="p-8 space-y-6">
        <!-- Send Now Section -->
        <div>
          <label class="block text-gray-300 font-medium mb-3">Send Credentials Now</label>
          <button
            @click="sendCredentialsEmail"
            class="btn-primary flex items-center gap-2"
          >
            <icon name="mail" class="w-5 h-5 fill-current" />
            Send Login Credentials Now
          </button>
          <p class="text-sm text-gray-400 mt-2">
            Generates a new password and emails it immediately to the account.
          </p>
        </div>

        <!-- Divider -->
        <div class="border-t border-primary-800/30"></div>

        <!-- Schedule Reminders Section -->
        <div>
          <label class="block text-gray-300 font-medium mb-3">Schedule Credential Reminders</label>
          
          <!-- Active Reminder Display -->
          <div v-if="emailReminder" class="mb-4">
            <div class="flex items-center gap-3 p-3 bg-blue-900/20 border border-blue-700/30 rounded-lg">
              <div class="flex-1">
                <div class="text-blue-300 font-semibold">Active Reminder</div>
                <div class="text-sm text-gray-400 mt-1">
                  Sending {{ formatInterval(emailReminder.interval) }}
                </div>
              </div>
              <button
                @click="cancelReminder"
                class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white text-sm rounded transition-colors"
              >
                Cancel
              </button>
            </div>
          </div>

          <!-- Set New Reminder -->
          <div v-else class="space-y-3">
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
              Set Reminder
            </button>
            
            <p class="text-sm text-gray-400">
              Schedules future reminder emails. <strong>Does not send immediately.</strong>
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Email Logs (Admin Only) -->
    <div v-if="$page.props.auth.user.isAdmin" class="max-w-3xl bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
      <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-magenta-400 font-bold text-lg">Email History</h2>
      </div>
      <div v-if="emailLogs && emailLogs.length > 0" class="divide-y divide-primary-800/20">
        <div
          v-for="log in emailLogs"
          :key="log.id"
          class="px-8 py-4 hover:bg-primary-900/20 transition-colors"
        >
          <div class="flex items-start justify-between">
            <div class="flex-1">
              <div class="font-semibold text-white">{{ log.subject }}</div>
              <div class="text-sm text-gray-400 mt-1">
                Sent: {{ log.sent_at }}
              </div>
            </div>
            <span
              class="px-3 py-1 rounded-full text-xs font-semibold"
              :class="log.opened ? 'bg-green-900/50 text-green-300' : 'bg-gray-700 text-gray-300'"
            >
              {{ log.opened ? 'Opened' : 'Sent' }}
            </span>
          </div>
        </div>
      </div>
      <div v-else class="px-8 py-8 text-gray-400 text-center">
        No emails sent yet
      </div>
    </div>

    <!-- Edit Account Form -->
    <div class="max-w-3xl bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
      <form @submit.prevent="update">
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <text-input v-model="form.name" :error="form.errors.name" class="pb-8 pr-6 w-full lg:w-1/2" label="Account Name" />
          <text-input v-model="form.email" :error="form.errors.email" class="pb-8 pr-6 w-full lg:w-1/2" label="Email" type="email" />
        </div>
        <div class="flex items-center justify-between px-8 py-4 bg-dark-900/60 border-t border-primary-800/40">
          <Link 
            href="/accounts" 
            class="text-gray-400 hover:text-gray-300 transition-colors"
          >
            Back to Accounts
          </Link>
          <loading-button :loading="form.processing" class="btn-primary" type="submit">Save Changes</loading-button>
        </div>
      </form>
    </div>

    <!-- Create Application Button (Admin Only) -->
    <div v-if="$page.props.auth.user.isAdmin" class="mb-8">
      <Link 
        v-if="account.is_confirmed"
        :href="`/applications/create?account_id=${account.id}`" 
        class="btn-primary inline-flex items-center gap-2"
      >
        <span>Create Application for Account</span>
      </Link>
      <button
        v-else
        disabled
        class="btn-primary inline-flex items-center gap-2 opacity-50 cursor-not-allowed"
        title="Account must be confirmed before creating applications"
      >
        <icon name="lock" class="w-4 h-4 fill-current" />
        <span>Create Application (Account Not Confirmed)</span>
      </button>
    </div>

    <!-- Applications List -->
    <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
      <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-magenta-400 font-bold text-lg">Applications ({{ (applications || []).length }})</h2>
      </div>
      <div v-if="(applications || []).length > 0" class="divide-y divide-primary-800/20">
        <Link
          v-for="application in applications"
          :key="application.id"
          :href="`/applications/${application.id}/edit`"
          class="flex items-center justify-between px-6 py-4 hover:bg-primary-900/30 transition-colors duration-150 group cursor-pointer"
        >
          <div class="flex-1">
            <div class="font-semibold text-magenta-400 group-hover:text-magenta-300 transition-colors">
              {{ application.name }}
            </div>
            <div class="text-sm text-gray-400 mt-1">
              Created {{ formatDate(application.created_at) }}
            </div>
          </div>
          <svg 
            class="w-5 h-5 text-gray-400 group-hover:text-magenta-400 transition-colors" 
            fill="none" 
            stroke="currentColor" 
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </Link>
      </div>
      <div v-else class="px-6 py-8 text-gray-400 text-center">
        No applications found for this account.
      </div>
    </div>
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import TextInput from '@/Shared/TextInput.vue'
import LoadingButton from '@/Shared/LoadingButton.vue'
import Icon from '@/Shared/Icon.vue'

export default {
  components: { Head, Link, LoadingButton, TextInput, Icon },
  layout: Layout,
  remember: 'form',
  props: {
    account: Object,
    applications: Array,
    emailReminder: Object,
    emailLogs: Array,
  },
  data() {
    return {
      form: this.$inertia.form({
        name: this.account.name,
        email: this.account.email,
      }),
      reminderInterval: '',
    }
  },
  methods: {
    update() {
      this.form.put(`/accounts/${this.account.id}`)
    },
    sendCredentialsEmail() {
      if (confirm('Send login credentials to this account?')) {
        this.$inertia.post(`/accounts/${this.account.id}/send-credentials`)
      }
    },
    setReminder() {
      if (!this.reminderInterval) {
        return
      }
      
      if (confirm(`Schedule credentials to be sent ${this.formatInterval(this.reminderInterval).toLowerCase()}? No email will be sent now.`)) {
        this.$inertia.post(`/accounts/${this.account.id}/set-credentials-reminder`, {
          interval: this.reminderInterval,
        }, {
          onSuccess: () => {
            this.reminderInterval = ''
          }
        })
      }
    },
    cancelReminder() {
      if (confirm('Cancel scheduled credential reminders?')) {
        this.$inertia.post(`/accounts/${this.account.id}/cancel-credentials-reminder`)
      }
    },
    formatDate(date) {
      if (!date) return '—'
      const d = new Date(date)
      return d.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      })
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