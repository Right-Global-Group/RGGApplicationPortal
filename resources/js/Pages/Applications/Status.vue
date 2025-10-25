<template>
  <div>
    <Head :title="`Status - ${application.name}`" />
    
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold text-white">Application Status</h1>
      <Link 
        href="/progress-tracker" 
        class="text-magenta-400 hover:text-magenta-300 flex items-center gap-2"
      >
        <icon name="arrow-left" class="w-4 h-4 fill-current" />
        Back to Tracker
      </Link>
    </div>

    <!-- Application Info -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <div class="flex justify-between">
        <div>
          <div class="text-sm text-gray-400 mb-1">Application Name</div>
          <div class="text-lg font-semibold text-white">{{ application.name }}</div>
        </div>
        <div v-if="application.trading_name">
          <div class="text-sm text-gray-400 mb-1">Trading Name</div>
          <div class="text-lg font-semibold text-white">{{ application.trading_name }}</div>
        </div>
        <Link
          :href="`/applications/${application.id}/edit`"
          class="text-magenta-400 hover:text-magenta-300 flex items-center gap-2"
        >
          <icon name="edit" class="w-4 h-4 fill-current" />
          Edit Application
        </Link>
      </div>
    </div>

    <!-- Fee Structure Display -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-primary-800/30 shadow-2xl overflow-hidden mb-6">
      <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-xl font-bold text-magenta-400">Fee Structure</h2>
      </div>
      <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
            <div class="text-gray-400 text-sm mb-1">Setup Fee (+ VAT)</div>
            <div class="text-2xl font-bold text-magenta-400">£{{ parseFloat(application.setup_fee).toFixed(2) }}</div>
            <div class="text-gray-500 text-xs mt-1">Taken on approval</div>
          </div>
          <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
            <div class="text-gray-400 text-sm mb-1">Transaction Fee</div>
            <div class="text-2xl font-bold text-magenta-400">{{ application.transaction_percentage }}% + £{{ parseFloat(application.transaction_fixed_fee).toFixed(2) }}</div>
            <div class="text-gray-500 text-xs mt-1">Per transaction</div>
          </div>
          <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
            <div class="text-gray-400 text-sm mb-1">Monthly Fee</div>
            <div class="text-2xl font-bold text-magenta-400">£{{ parseFloat(application.monthly_fee).toFixed(2) }}</div>
            <div class="text-gray-500 text-xs mt-1">Fixed monthly charge</div>
          </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
            <div class="text-gray-400 text-sm mb-1">Monthly Minimum</div>
            <div class="text-xl font-bold text-gray-300">£{{ parseFloat(application.monthly_minimum).toFixed(2) }}</div>
            <div class="text-gray-500 text-xs mt-1">Made up of transactional fees</div>
          </div>
          <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
            <div class="text-gray-400 text-sm mb-1">Service Fee</div>
            <div class="text-xl font-bold text-gray-300">£{{ parseFloat(application.service_fee).toFixed(2) }}</div>
            <div class="text-gray-500 text-xs mt-1">Additional service charge</div>
          </div>
        </div>

        <!-- Fees Confirmation Status and Button -->
        <div v-if="!application.fees_confirmed && is_account" class="bg-yellow-900/20 border border-yellow-700/30 rounded-lg p-4">
          <div class="flex items-center justify-between">
            <div>
              <div class="text-yellow-400 font-semibold mb-1">Fees Require Confirmation</div>
              <div class="text-gray-400 text-sm">Please review and confirm the fee structure to proceed with your application.</div>
            </div>
            <button @click="confirmFees" :disabled="confirmingFees" class="btn-primary whitespace-nowrap ml-4">
              {{ confirmingFees ? 'Confirming...' : 'Confirm Fees' }}
            </button>
          </div>
        </div>
        
        <div v-else-if="application.fees_confirmed" class="bg-green-900/20 border border-green-700/30 rounded-lg p-4">
          <div class="flex items-center">
            <svg class="w-5 h-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <div>
              <div class="text-green-400 font-semibold">Fees Confirmed</div>
              <div class="text-gray-400 text-sm">Confirmed {{ application.fees_confirmed_at }}</div>
            </div>
          </div>
        </div>

        <div v-else-if="!is_account" class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
          <div class="text-gray-400 text-sm">
            <span v-if="!application.fees_confirmed" class="text-yellow-400">⏳ Awaiting fee confirmation from account</span>
            <span v-else class="text-green-400">✓ Fees confirmed</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Progress Bar -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-xl font-bold text-white">Overall Progress</h2>
        <span class="text-2xl font-bold text-magenta-400">
          {{ application.status?.progress_percentage || 0 }}%
        </span>
      </div>
      <div class="bg-gray-700 rounded-full h-4 overflow-hidden">
        <div 
          class="h-full bg-gradient-to-r from-magenta-500 to-primary-500 transition-all duration-500"
          :style="{ width: (application.status?.progress_percentage || 0) + '%' }"
        ></div>
      </div>
      <div class="mt-2 text-sm text-gray-400">
        Current Step: <span class="text-magenta-400 font-semibold">
          {{ formatStatus(application.status?.current_step) }}
        </span>
      </div>
    </div>

    <!-- Alert for Additional Info -->
    <div 
      v-if="application.status?.requires_additional_info" 
      class="bg-red-900/30 border border-red-700/50 rounded-xl p-4 mb-6"
    >
      <div class="flex items-start gap-3">
        <icon name="alert-circle" class="w-6 h-6 fill-red-400 flex-shrink-0 mt-0.5" />
        <div class="flex-1">
          <h3 class="text-lg font-semibold text-red-300 mb-2">Additional Information Required</h3>
          <p class="text-gray-300">{{ application.status.additional_info_notes }}</p>
        </div>
      </div>
    </div>

    <!-- Action Buttons (only for users, not accounts) -->
    <div v-if="!is_account" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <h2 class="text-xl font-bold text-white mb-4">Quick Actions</h2>
      <div class="flex flex-wrap gap-3">
        <button
          v-if="canSendContract"
          @click="sendContractLink"
          :disabled="isLoading"
          class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
        >
          <svg 
            v-if="isLoading" 
            class="animate-spin h-5 w-5" 
            xmlns="http://www.w3.org/2000/svg" 
            fill="none" 
            viewBox="0 0 24 24"
          >
            <circle 
              class="opacity-25" 
              cx="12" 
              cy="12" 
              r="10" 
              stroke="currentColor" 
              stroke-width="4"
            ></circle>
            <path 
              class="opacity-75" 
              fill="currentColor" 
              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            ></path>
          </svg>
          <svg 
            v-else
            class="h-5 w-5" 
            xmlns="http://www.w3.org/2000/svg" 
            fill="none" 
            viewBox="0 0 24 24" 
            stroke="currentColor"
          >
            <path 
              stroke-linecap="round" 
              stroke-linejoin="round" 
              stroke-width="2" 
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" 
            />
          </svg>
          <span v-if="isLoading">Opening DocuSign...</span>
          <span v-else>Send Contract Link (DocuSign)</span>
        </button>

        <button
          v-if="canApprove"
          @click="markAsApproved"
          class="btn-primary"
        >
          Approve Application
        </button>

        <button
          v-if="canCreateInvoice"
          @click="showInvoiceModal = true"
          class="btn-primary"
        >
          Create Invoice
        </button>

        <button
          @click="requestAdditionalInfo"
          class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition-colors"
        >
          Request Additional Info
        </button>
      </div>
    </div>

    <!-- Progress Timeline -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <h2 class="text-xl font-bold text-white mb-6">Progress Timeline</h2>
      <div class="space-y-4">
        <timeline-step
          v-for="(step, index) in processSteps"
          :key="step.id"
          :label="step.label"
          :description="step.description"
          :is-completed="isStepCompleted(step.id)"
          :is-current="application.status?.current_step === step.id"
          :timestamp="getStepTimestamp(step.id)"
          :show-connector="index < processSteps.length - 1"
        />
      </div>
    </div>

    <!-- Documents Section -->
    <div v-if="application.documents?.length > 0" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <h2 class="text-xl font-bold text-white mb-4">Documents</h2>
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
            </div>
          </div>
        </div>
        <div v-else class="bg-yellow-900/20 border border-yellow-700/30 rounded-lg p-3">
          <p class="text-yellow-300 text-sm">No documents uploaded yet</p>
        </div>
      </div>
    </div>

    <!-- Invoices Section -->
    <div v-if="application.invoices?.length > 0" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <h2 class="text-xl font-bold text-white mb-4">Invoices</h2>
      <div class="space-y-2">
        <div
          v-for="invoice in application.invoices"
          :key="invoice.id"
          class="flex items-center justify-between p-3 bg-dark-900/50 border border-primary-800/30 rounded-lg"
        >
          <div>
            <div class="text-white font-semibold">{{ invoice.invoice_number }}</div>
            <div class="text-sm text-gray-400">
              Amount: £{{ parseFloat(invoice.amount).toFixed(2) }}
              <span v-if="invoice.due_date" class="ml-2">Due: {{ invoice.due_date }}</span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span
              class="inline-flex px-3 py-1 rounded-full text-sm font-semibold"
              :class="getInvoiceStatusClass(invoice.status)"
            >
              {{ invoice.status }}
            </span>
            <button
              v-if="invoice.status === 'sent' && !is_account"
              @click="markInvoiceAsPaid(invoice.id)"
              class="px-3 py-1 bg-green-600 hover:bg-green-700 text-white rounded text-sm"
            >
              Mark as Paid
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Gateway Integration Section -->
    <div v-if="application.gateway" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <h2 class="text-xl font-bold text-white mb-4">Gateway Integration</h2>
      <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
        <div class="flex items-center justify-between mb-2">
          <div class="text-gray-400">Provider</div>
          <div class="text-white font-semibold">{{ application.gateway.provider }}</div>
        </div>
        <div class="flex items-center justify-between mb-2">
          <div class="text-gray-400">Merchant ID</div>
          <div class="text-white">{{ application.gateway.merchant_id }}</div>
        </div>
        <div class="flex items-center justify-between">
          <div class="text-gray-400">Status</div>
          <span
            class="inline-flex px-3 py-1 rounded-full text-sm font-semibold"
            :class="getGatewayStatusClass(application.gateway.status)"
          >
            {{ application.gateway.status }}
          </span>
        </div>
      </div>
    </div>

    <!-- Activity Log -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
      <h2 class="text-xl font-bold text-white mb-4">Activity Log</h2>
      <div v-if="application.activity_logs?.length > 0" class="space-y-3">
        <div
          v-for="log in application.activity_logs"
          :key="log.created_at"
          class="flex items-start gap-3 p-3 hover:bg-primary-900/20 rounded-lg transition-colors"
        >
          <div class="w-2 h-2 bg-magenta-500 rounded-full mt-2 flex-shrink-0"></div>
          <div class="flex-1">
            <div class="text-white">{{ log.description }}</div>
            <div class="text-sm text-gray-400 mt-1">
              {{ log.created_at }}
              <span v-if="log.user_name" class="ml-2">by {{ log.user_name }}</span>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="text-gray-400 text-center py-4">No activity yet</div>
    </div>

    <!-- Modals -->
    <invoice-modal
      v-if="showInvoiceModal"
      :application-id="application.id"
      @close="showInvoiceModal = false"
    />

    <additional-info-modal
      v-if="showAdditionalInfoModal"
      :application-id="application.id"
      @close="showAdditionalInfoModal = false"
    />
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import TimelineStep from '@/Shared/TimelineStep.vue'
import InvoiceModal from '@/Shared/InvoiceModal.vue'
import AdditionalInfoModal from '@/Shared/AdditionalInfoModal.vue'
import Icon from '@/Shared/Icon.vue'

export default {
  components: {
    Head,
    Link,
    TimelineStep,
    InvoiceModal,
    AdditionalInfoModal,
    Icon,
  },
  layout: Layout,
  props: {
    application: Object,
    is_account: Boolean,
    documentCategories: Object,
    categoryDescriptions: Object,
  },
  data() {
    return {
      confirmingFees: false,
      isLoading: false,
      showInvoiceModal: false,
      showAdditionalInfoModal: false,
      processSteps: [
        { id: 'created', label: 'Application Created', description: 'Initial application setup' },
        { id: 'fees_confirmed', label: 'Fees Confirmed', description: 'Account confirmed fee structure' },
        { id: 'documents_uploaded', label: 'Documents Uploaded', description: 'All required documents uploaded' },
        { id: 'application_sent', label: 'Contract Sent', description: 'Contract sent to client' },
        { id: 'contract_completed', label: 'Contract Signed', description: 'Client signed the contract' },
        { id: 'contract_submitted', label: 'Contract Submitted', description: 'Contract submitted for review' },
        { id: 'application_approved', label: 'Application Approved', description: 'Application approved by admin' },
        { id: 'invoice_sent', label: 'Invoice Sent', description: 'Setup fee invoice sent' },
        { id: 'invoice_paid', label: 'Payment Received', description: 'Setup fee paid' },
        { id: 'gateway_integrated', label: 'Gateway Integration', description: 'Payment gateway integrated' },
        { id: 'account_live', label: 'Account Live', description: 'Merchant account is live' },
      ],
    }
  },
  computed: {
    canSendContract() {
      return this.application.status?.current_step === 'documents_uploaded' && !this.is_account
    },
    canApprove() {
      return ['contract_submitted'].includes(this.application.status?.current_step) && !this.is_account
    },
    canCreateInvoice() {
      return this.application.status?.current_step === 'application_approved' && !this.is_account
    },
  },
  methods: {
    confirmFees() {
      if (confirm('Are you sure you want to confirm these fees? This action cannot be undone.')) {
        this.confirmingFees = true
        this.$inertia.post(`/applications/${this.application.id}/confirm-fees`, {}, {
          onFinish: () => {
            this.confirmingFees = false
          }
        })
      }
    },
    async sendContractLink() {
      this.isLoading = true
      try {
        const response = await fetch(`/applications/${this.application.id}/send-contract`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
        })
        const data = await response.json()
        
        if (data.success && data.signing_url) {
          window.open(data.signing_url, '_blank', 'width=800,height=600')
        } else {
          alert(data.message || 'Failed to send contract')
        }
      } catch (error) {
        console.error('Error sending contract:', error)
        alert('Failed to send contract')
      } finally {
        this.isLoading = false
      }
    },
    markAsApproved() {
      if (confirm('Mark this application as approved?')) {
        this.$inertia.post(`/applications/${this.application.id}/mark-approved`)
      }
    },
    requestAdditionalInfo() {
      this.showAdditionalInfoModal = true
    },
    markInvoiceAsPaid(invoiceId) {
      if (confirm('Mark this invoice as paid?')) {
        this.$inertia.post(`/invoices/${invoiceId}/mark-paid`)
      }
    },
    isStepCompleted(stepId) {
      const currentStep = this.application.status?.current_step
      const stepOrder = ['created', 'fees_confirmed', 'documents_uploaded', 'application_sent', 'contract_completed', 'contract_submitted', 'application_approved', 'invoice_sent', 'invoice_paid', 'gateway_integrated', 'account_live']
      const currentIndex = stepOrder.indexOf(currentStep)
      const checkIndex = stepOrder.indexOf(stepId)
      return checkIndex <= currentIndex
    },
    getStepTimestamp(stepId) {
      return this.application.status?.timestamps?.[stepId] || null
    },
    formatStatus(status) {
      if (!status) return 'Created'
      return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
    },
    getDocumentStatusClass(status) {
      const classes = {
        sent: 'bg-blue-900/30 text-blue-400',
        completed: 'bg-green-900/30 text-green-400',
        pending: 'bg-yellow-900/30 text-yellow-400',
      }
      return classes[status] || 'bg-gray-900/30 text-gray-400'
    },
    getInvoiceStatusClass(status) {
      const classes = {
        sent: 'bg-blue-900/30 text-blue-400',
        paid: 'bg-green-900/30 text-green-400',
        overdue: 'bg-red-900/30 text-red-400',
      }
      return classes[status] || 'bg-gray-900/30 text-gray-400'
    },
    getGatewayStatusClass(status) {
      const classes = {
        active: 'bg-green-900/30 text-green-400',
        pending: 'bg-yellow-900/30 text-yellow-400',
        inactive: 'bg-gray-900/30 text-gray-400',
      }
      return classes[status] || 'bg-gray-900/30 text-gray-400'
    },
    getDocumentsByCategory(category) {
      return this.application.documents.filter(doc => doc.document_category === category)
    },
    getCategoryDescription(category) {
      return this.categoryDescriptions?.[category] || ''
    },
  },
}
</script>