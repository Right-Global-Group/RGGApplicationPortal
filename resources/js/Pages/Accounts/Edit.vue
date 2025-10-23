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
      <div class="p-8 space-y-4">
        <div class="flex items-start gap-4">
          <button
            @click="sendCredentialsEmail"
            class="btn-primary flex items-center gap-2"
          >
            <icon name="mail" class="w-5 h-5 fill-current" />
            Send Login Credentials
          </button>
          
          <div class="flex-1">
            <label class="block text-gray-300 font-medium mb-2">Email Reminder</label>
            <div v-if="emailReminder" class="flex items-center gap-2">
              <span class="px-3 py-1 bg-blue-900/50 text-blue-300 rounded-lg text-sm">
                Active: {{ formatInterval(emailReminder.interval) }}
              </span>
              <button
                @click="cancelReminder"
                class="text-sm text-red-400 hover:text-red-300"
              >
                Cancel
              </button>
            </div>
            <select
              v-else
              v-model="reminderInterval"
              @change="setReminder"
              class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 focus:outline-none focus:border-magenta-500"
            >
              <option value="">Set reminder interval...</option>
              <option value="1_day">Every 1 day</option>
              <option value="3_days">Every 3 days</option>
              <option value="1_week">Every week</option>
              <option value="2_weeks">Every 2 weeks</option>
              <option value="1_month">Every month</option>
            </select>
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
      <table v-if="(applications || []).length > 0" class="w-full text-left">
        <thead>
          <tr class="text-magenta-400 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <th class="px-6 py-3">Name</th>
            <th class="px-6 py-3">Email</th>
            <th class="px-6 py-3">City</th>
            <th class="px-6 py-3">Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="application in applications" :key="application.id" class="hover:bg-primary-900/30 transition-colors duration-150 border-b border-primary-800/20">
            <td class="px-6 py-3 text-white">
              <Link :href="`/applications/${application.id}/edit`" class="text-magenta-400 hover:text-magenta-300 transition-colors">
                {{ application.name }}
              </Link>
            </td>
            <td class="px-6 py-3 text-gray-300">
              {{ application.email || '—' }}
            </td>
            <td class="px-6 py-3 text-gray-300">
              {{ application.city || '—' }}
            </td>
            <td class="px-6 py-3 text-gray-400">
              {{ formatDate(application.created_at) }}
            </td>
          </tr>
        </tbody>
      </table>
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
      if (this.reminderInterval) {
        this.$inertia.post(`/accounts/${this.account.id}/set-email-reminder`, {
          interval: this.reminderInterval,
        }, {
          onSuccess: () => {
            this.reminderInterval = ''
          }
        })
      }
    },
    cancelReminder() {
      if (confirm('Cancel the email reminder?')) {
        this.$inertia.post(`/accounts/${this.account.id}/cancel-email-reminder`)
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