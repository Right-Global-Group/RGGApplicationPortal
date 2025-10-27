<template>
  <div>
    <Head :title="account.name" />
    <div class="flex justify-between flex-wrap">
      <div class="flex items-center gap-6 mb-6 flex-wrap">
        <h1 class="text-3xl font-bold text-white flex items-center">
          <Link class="text-magenta-400 hover:text-magenta-500 transition-colors" href="/accounts">Accounts</Link>
          <span class="text-gray-500 mx-2">/</span>
          {{ account.name }}
          <img v-if="account.photo" class="ml-4 w-12 h-12 rounded-full border border-primary-800/30 object-cover" :src="account.photo" />
          <div v-else class="ml-4 w-12 h-12 rounded-full bg-gradient-to-br from-primary-600 to-magenta-600 flex items-center justify-center text-white font-semibold text-lg">
            {{ account.name.charAt(0).toUpperCase() }}
          </div>
        </h1>
        <span 
          class="px-4 py-2 rounded-full text-sm font-semibold"
          :class="account.is_confirmed ? 'bg-green-900/50 text-green-300' : 'bg-yellow-900/50 text-yellow-300'"
        >
          {{ account.is_confirmed ? 'Confirmed Login' : 'Pending Login' }}
        </span>
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
    </div>

    <div class="flex flex-col sm:flex sm:flex-row gap-6">
      <div class="sm:w-1/2 w-full">

        <!-- Account Details Section -->
        <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
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

        <!-- Email Credentials Section (Admin Only) - Hidden after first login -->
        <div v-if="$page.props.auth.user.isAdmin && !account.first_login_at" class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
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

        <!-- Edit Account Form -->
        <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
          <form @submit.prevent="update">
            <div class="flex flex-wrap -mb-8 -mr-6 p-8">
              <text-input v-model="form.name" :error="form.errors.name" class="pb-8 pr-6 w-full lg:w-1/2" label="Account Name" />
              <text-input v-model="form.email" :error="form.errors.email" class="pb-8 pr-6 w-full lg:w-1/2" label="Email" type="email" />
              <file-input v-model="form.photo" :error="form.errors.photo" class="pb-8 pr-6 w-full lg:w-1/2" label="Photo" accept="image/*" />
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
      </div>
    

      <div class="sm:w-1/2 w-full">
        <!-- Applications List with Next Steps -->
        <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
          <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <h2 class="text-magenta-400 font-bold text-lg">Applications ({{ (applications || []).length }})</h2>
          </div>
          <div v-if="(applications || []).length > 0" class="divide-y divide-primary-800/20">
            <div
              v-for="application in applications"
              :key="application.id"
              class="px-6 py-4 hover:bg-primary-900/20 transition-colors duration-150"
            >
              <Link
                :href="`/applications/${application.id}/edit`"
                class="flex items-center justify-between group cursor-pointer mb-2"
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
              
              <!-- Next Steps Section -->
              <div v-if="getNextStep(application)" class="mt-3 p-3 bg-yellow-900/20 border border-yellow-700/30 rounded-lg">
                <div class="flex items-start gap-2">
                  <svg class="w-4 h-4 text-yellow-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                  </svg>
                  <div class="flex-1">
                    <div class="text-xs font-semibold text-yellow-300 uppercase tracking-wide">Next Step:</div>
                    <div class="text-sm text-gray-300 mt-1">{{ getNextStep(application) }}</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="px-6 py-8 text-gray-400 text-center">
            No applications found for this account.
          </div>
        </div>


        <!-- Email Logs (Admin Only) -->
        <div v-if="$page.props.auth.user.isAdmin" class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
          <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <h2 class="text-magenta-400 font-bold text-lg">Account Email History</h2>
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
      </div>
    </div>
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import TextInput from '@/Shared/TextInput.vue'
import FileInput from '@/Shared/FileInput.vue'
import LoadingButton from '@/Shared/LoadingButton.vue'
import Icon from '@/Shared/Icon.vue'

export default {
  components: { Head, Link, LoadingButton, TextInput, FileInput, Icon },
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
        photo: null,
      }),
      reminderInterval: '',
    }
  },
  methods: {
    update() {
      this.form.post(`/accounts/${this.account.id}`, {
        onSuccess: () => this.form.reset('photo'),
      })
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
    getNextStep(application) {
      const status = application.status?.current_step
      
      const nextSteps = {
        'created': 'Confirm the fee structure for this application',
        'fees_confirmed': 'Upload required documents',
        'documents_uploaded': 'Wait for contract to be sent',
        'application_sent': 'Sign the contract document',
        'contract_completed': 'Wait for application review',
        'contract_submitted': 'Wait for approval',
        'application_approved': 'Wait for invoice',
        'invoice_sent': 'Pay the setup fee invoice',
        'invoice_paid': 'Wait for gateway integration',
        'gateway_integrated': 'Your account is being set up',
        'account_live': null, // No next step when live
      }
      
      return nextSteps[status] || null
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