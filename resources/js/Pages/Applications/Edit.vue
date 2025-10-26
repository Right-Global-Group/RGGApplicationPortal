<template>
  <div>
    <Head :title="application.name" />
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold text-white">
        <Link class="text-magenta-400 hover:text-magenta-500 transition-colors" href="/applications">Applications</Link>
        <span class="text-gray-500 mx-2">/</span>
        {{ application.name }}
      </h1>
    </div>

    <!-- Top Section: Two columns -->
    <div class="flex flex-col lg:flex-row gap-6 mb-8">
      <!-- Left Column: Details + Form -->
      <div class="flex-1 flex flex-col gap-6">
        <!-- Application Details -->
        <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
          <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <h2 class="text-magenta-400 font-bold text-lg">Application Details</h2>
          </div>
          <div class="p-8 space-y-4">
            <div>
              <label class="block text-gray-300 font-medium mb-2">Account</label>
              <Link v-if="application.account_id" :href="`/accounts/${application.account_id}/edit`" class="inline-block px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-magenta-400 hover:text-magenta-300 hover:border-magenta-500/50 transition-colors">
                {{ application.account_name || '—' }}
              </Link>
              <div v-else class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                {{ application.account_name || '—' }}
              </div>
            </div>
            
            <!-- Parent Application (if exists) -->
            <div v-if="application.parent_application_id" class="bg-blue-900/20 border border-blue-700/30 rounded-lg p-4">
              <label class="block text-blue-300 font-medium mb-2">
                <svg class="inline-block w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Created from Fee Change
              </label>
              <Link 
                :href="`/applications/${application.parent_application_id}/edit`" 
                class="inline-flex items-center px-4 py-2 bg-dark-900/50 border border-blue-700/30 rounded-lg text-blue-300 hover:text-blue-200 hover:border-blue-500/50 transition-colors"
              >
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                View Original Application ({{ application.parent_application_name || `#${application.parent_application_id}` }})
              </Link>
              <p class="text-xs text-gray-400 mt-2">
                This application was created because the fee structure was updated on the original application.
              </p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-gray-300 font-medium mb-2">Created By</label>
                <Link v-if="application.user_id" :href="`/users/${application.user_id}/edit`" class="inline-block px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-magenta-400 hover:text-magenta-300 hover:border-magenta-500/50 transition-colors">
                  {{ application.user_name || '—' }}
                </Link>
                <div v-else class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  {{ application.user_name || '—' }}
                </div>
              </div>
              <div>
                <label class="block text-gray-300 font-medium mb-2">Created At</label>
                <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  {{ formatDate(application.created_at) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Fee Structure Display (Read-only) with Change Fees Button -->
        <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
          <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30 flex items-center justify-between">
            <h2 class="text-magenta-400 font-bold text-lg">Fee Structure</h2>
            <button 
              v-if="canChangeFees"
              @click="showChangeFeesModal = true" 
              class="px-4 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors text-sm font-medium"
            >
              Change Fees
            </button>
          </div>
          <div class="p-8">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-gray-300 font-medium mb-2">Setup Fee</label>
                <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  £{{ parseFloat(application.setup_fee).toFixed(2)}}
                </div>
              </div>
              <div>
                <label class="block text-gray-300 font-medium mb-2">Transaction Fee</label>
                <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  {{ application.transaction_percentage }}% + £{{ parseFloat(application.transaction_fixed_fee).toFixed(2) }}
                </div>
              </div>
              <div>
                <label class="block text-gray-300 font-medium mb-2">Monthly Fee</label>
                <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  £{{ parseFloat(application.monthly_fee).toFixed(2) }}
                </div>
              </div>
              <div>
                <label class="block text-gray-300 font-medium mb-2">Monthly Minimum</label>
                <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  £{{ parseFloat(application.monthly_minimum).toFixed(2) }}
                </div>
              </div>
              <div>
                <label class="block text-gray-300 font-medium mb-2">Service Fee</label>
                <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  £{{ parseFloat(application.service_fee).toFixed(2) }}
                </div>
              </div>
              <div>
                <label class="block text-gray-300 font-medium mb-2">Fees Confirmed</label>
                <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  <span v-if="application.fees_confirmed" class="text-green-400">
                    ✓ Confirmed {{ application.fees_confirmed_at ? `at ${application.fees_confirmed_at}` : '' }}
                  </span>
                  <span v-else class="text-yellow-400">Pending Confirmation</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Documents Section - Only show if status is documents_uploaded or later -->
        <div v-if="shouldShowDocuments" class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
          <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30 flex items-center justify-between">
            <h2 class="text-magenta-400 font-bold text-lg">Documents</h2>
            <button 
              @click="showDocumentUploadModal = true" 
              class="px-4 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors text-sm font-medium"
            >
              Upload Document
            </button>
          </div>
          <div class="p-8">
            <div 
              v-for="(label, category) in documentCategories" 
              :key="category"
              class="mb-6 last:mb-0"
            >
              <h3 class="text-lg font-semibold text-gray-300 mb-2">{{ label }}</h3>
              <p class="text-sm text-gray-400 mb-3">{{ getCategoryDescription(category) }}</p>
              
              <div v-if="getDocumentsByCategory(category).length > 0" class="space-y-2">
                <div 
                  v-for="doc in getDocumentsByCategory(category)"
                  :key="doc.id"
                  class="flex items-center justify-between bg-dark-900/50 border border-primary-800/30 rounded-lg p-3"
                >
                  <div class="flex items-center flex-1">
                    <svg class="w-5 h-5 text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-gray-300">{{ doc.original_filename || 'Document' }}</span>
                  </div>
                  <div class="flex items-center gap-2">
                    <a 
                      :href="`/applications/${application.id}/documents/${doc.id}/download`"
                      class="text-blue-400 hover:text-blue-300 text-sm"
                    >
                      Download
                    </a>
                    <button 
                      v-if="canChangeFees"
                      @click="deleteDocument(doc.id)"
                      class="text-red-400 hover:text-red-300 text-sm"
                    >
                      Delete
                    </button>
                  </div>
                </div>
              </div>
              <div v-else class="bg-yellow-900/20 border border-yellow-700/30 rounded-lg p-3">
                <p class="text-yellow-300 text-sm">No documents uploaded yet</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Document Upload Modal -->
        <document-upload-modal 
          :show="showDocumentUploadModal"
          :application-id="application.id"
          :categories="documentCategories"
          :category-descriptions="categoryDescriptions"
          @close="showDocumentUploadModal = false"
        />

        <!-- Edit Application Form -->
        <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
          <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <h2 class="text-magenta-400 font-bold text-lg">Edit Application</h2>
          </div>
          <form @submit.prevent="update">
            <div class="flex flex-wrap -mb-8 -mr-6 p-8">
              <text-input
                v-if="$page.props.auth.user.isAdmin"
                v-model="form.name"
                :error="form.errors.name"
                class="pb-8 pr-6 w-full lg:w-1/2"
                label="Application Name"
              />

              <!-- Show as read-only for non-admin users -->
              <div
                v-else
                class="pb-8 pr-6 w-full lg:w-1/2"
              >
                <label class="block text-gray-300 font-medium mb-2">Application Name</label>
                <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  {{ form.name }}
                </div>
              </div>
            </div>

            <div class="flex items-center justify-between px-8 py-4 bg-dark-900/60 border-t border-primary-800/40">
              <Link href="/applications" class="text-gray-400 hover:text-gray-300 transition-colors">
                Back to Applications
              </Link>
              <loading-button
                v-if="$page.props.auth.user.isAdmin"
                :loading="form.processing"
                class="btn-primary"
                type="submit"
              >
                Save Changes
              </loading-button>
            </div>
          </form>
        </div>
      </div>

      <!-- Right Column: Application Status -->
      <div class="w-full lg:w-1/3">
        <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden sticky top-6">
          <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <h2 class="text-magenta-400 font-bold text-lg">Application Status</h2>
          </div>
          <div class="p-8 space-y-4">
            <!-- Current Status Badge -->
            <div class="text-center">
              <div class="inline-flex items-center px-4 py-2 bg-primary-900/50 border border-primary-700/50 rounded-full">
                <div class="w-2 h-2 bg-magenta-400 rounded-full mr-2 animate-pulse"></div>
                <span class="text-sm font-medium text-gray-300">
                  {{ formatStatus(application.status?.current_step) }}
                </span>
              </div>
            </div>

            <!-- Next Steps Box -->
            <div v-if="nextStep" class="bg-yellow-900/20 border border-yellow-700/30 rounded-lg p-4">
              <div class="flex items-start gap-2">
                <svg class="w-5 h-5 text-yellow-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="flex-1">
                  <div class="text-xs font-semibold text-yellow-300 uppercase tracking-wide mb-1">Next Step:</div>
                  <div class="text-sm text-gray-300">{{ nextStep }}</div>
                </div>
              </div>
            </div>

            <!-- View Full Status Button -->
            <Link
              :href="`/applications/${application.id}/status`"
              class="btn-primary w-full text-center block"
            >
              View Full Status & Timeline
            </Link>
          </div>
        </div>
      </div>
    </div>

    <!-- Change Fees Modal (Only shown if canChangeFees is true) -->
    <change-fees-modal 
      v-if="canChangeFees"
      :show="showChangeFeesModal" 
      :application="application" 
      @close="showChangeFeesModal = false" 
    />
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import TextInput from '@/Shared/TextInput.vue'
import SelectInput from '@/Shared/SelectInput.vue'
import LoadingButton from '@/Shared/LoadingButton.vue'
import ChangeFeesModal from '@/Shared/ChangeFeesModal.vue'
import DocumentUploadModal from '@/Shared/DocumentUploadModal.vue'

export default {
  components: { Head, Link, LoadingButton, SelectInput, TextInput, ChangeFeesModal, DocumentUploadModal },
  layout: Layout,
  remember: 'form',
  props: {
    application: Object,
    accounts: Array,
    documents: Array,
    documentCategories: Object,
    categoryDescriptions: Object,
    canChangeFees: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      showChangeFeesModal: false,
      showDocumentUploadModal: false,
      form: this.$inertia.form({
        account_id: this.application.account_id,
        name: this.application.name,
      }),
    }
  },
  computed: {
    shouldShowDocuments() {
      const status = this.application.status?.current_step
      const statusOrder = [
        'created', 'fees_confirmed', 'documents_uploaded', 'application_sent',
        'contract_completed', 'contract_submitted', 'application_approved',
        'invoice_sent', 'invoice_paid', 'gateway_integrated', 'account_live'
      ]
      
      const currentIndex = statusOrder.indexOf(status)
      const documentsIndex = statusOrder.indexOf('documents_uploaded')
      
      return currentIndex >= documentsIndex
    },
    nextStep() {
      const status = this.application.status?.current_step
      
      const nextSteps = {
        'created': 'Waiting for account to confirm fee structure',
        'fees_confirmed': 'Waiting for required documents to be uploaded',
        'documents_uploaded': 'Ready to send contract to account',
        'application_sent': 'Waiting for account to sign contract',
        'contract_completed': 'Contract signed, ready for review',
        'contract_submitted': 'Under review for approval',
        'application_approved': 'Ready to create and send invoice',
        'invoice_sent': 'Waiting for invoice payment',
        'invoice_paid': 'Ready for gateway integration',
        'gateway_integrated': 'Final setup in progress',
        'account_live': 'Application complete - Account is live',
      }
      
      return nextSteps[status] || 'Application in progress'
    }
  },
  methods: {
    update() {
      this.form.put(`/applications/${this.application.id}`)
    },
    getDocumentsByCategory(category) {
      return this.documents.filter(doc => doc.document_category === category)
    },
    getCategoryDescription(category) {
      return this.categoryDescriptions[category] || ''
    },
    deleteDocument(documentId) {
      if (confirm('Are you sure you want to delete this document?')) {
        this.$inertia.delete(`/applications/${this.application.id}/documents/${documentId}`)
      }
    },
    formatDate(date) {
      if (!date) return '—'
      const d = new Date(date)
      return d.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    },
    formatStatus(status) {
      if (!status) return 'Created'
      return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
    },
  },
}
</script>