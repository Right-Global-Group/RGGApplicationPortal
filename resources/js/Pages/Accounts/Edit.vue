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

      <div v-if="showAccountActions" class="mb-6 w-full">
        <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
          <div class="px-6 py-3 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <h3 class="text-magenta-400 font-bold text-base">Account Actions</h3>
          </div>
          <div class="p-4 flex flex-wrap gap-3">
            <!-- Upload Docs Button (shows on created or documents_uploaded) -->
            <Link
              v-if="canUploadDocs"
              :href="`/applications/${activeApplication.id}/edit#documents`"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
              </svg>
              Upload Documents
            </Link>

            <!-- Sign Document Button (shows on application_sent) -->
            <Link
              v-if="canSignDocument"
              :href="`/applications/${activeApplication.id}/status#section-actions`"
              class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors flex items-center gap-2"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
              </svg>
              Sign Document
            </Link>
          </div>
        </div>
      </div>

      <!-- Create Application Button (Admin Only) -->
      <div v-if="$page.props.auth.user.isAdmin" class="mb-8">
        <Link 
          :href="`/applications/create?account_id=${account.id}`" 
          class="btn-primary inline-flex items-center gap-2"
        >
          <span>Create Application for Account</span>
        </Link>
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
    emailLogs: Array,
  },
  data() {
    return {
      form: this.$inertia.form({
        name: this.account.name,
        email: this.account.email,
        photo: null,
      }),
    }
  },
  computed: {
    // Get the most recent active application
    activeApplication() {
      if (!this.applications || this.applications.length === 0) return null
      
      // Find first application that's not account_live
      return this.applications.find(app => app.status?.current_step !== 'account_live') || null
    },
    
    showAccountActions() {
      if (!this.activeApplication) return false
      const status = this.activeApplication.status?.current_step
      return ['created', 'documents_uploaded', 'application_sent'].includes(status)
    },
    
    canUploadDocs() {
      if (!this.activeApplication) return false
      const status = this.activeApplication.status?.current_step
      return ['created', 'documents_uploaded'].includes(status)
    },
    
    canSignDocument() {
      if (!this.activeApplication) return false
      const status = this.activeApplication.status?.current_step
      return status === 'application_sent'
    },
  },
  methods: {
    update() {
      this.form.post(`/accounts/${this.account.id}`, {
        onSuccess: () => this.form.reset('photo'),
      })
    },
    getNextStep(application) {
      const status = application.status?.current_step

      const nextSteps = {
        'created': 'Upload the required documents',
        'documents_uploaded': 'Wait for contract to be sent',
        'application_sent': 'Sign the contract document',
        'contract_completed': 'Wait for application review',
        'contract_submitted': 'Wait for approval',
        'application_approved': 'Wait for invoice',
        'invoice_sent': 'Pay the setup fee invoice',
        'invoice_paid': 'Wait for gateway integration',
        'gateway_integrated': 'Your account is being set up',
        'account_live': null,
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
  },
}
</script>