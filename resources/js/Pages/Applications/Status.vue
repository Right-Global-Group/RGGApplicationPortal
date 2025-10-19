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
        <div>
          <div class="text-sm text-gray-400 mb-1">Email</div>
          <div class="text-lg text-white">{{ application.email }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-400 mb-1">Phone</div>
          <div class="text-lg text-white">{{ application.phone }}</div>
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

    <!-- Action Buttons -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <h2 class="text-xl font-bold text-white mb-4">Quick Actions</h2>
      <div class="flex flex-wrap gap-3">
        <button
          v-if="canSendContract"
          @click="sendContractLink"
          :disabled="isLoading"
          class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
        >
          <svg v-if="isLoading" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <span v-if="isLoading">Sending...</span>
          <span v-else>Send Contract Link (DocuSign)</span>
        </button>
        <button
          v-if="canApprove"
          @click="markAsApproved"
          class="btn-primary bg-green-600 hover:bg-green-700"
        >
          Mark as Approved
        </button>
        <button
          v-if="canSendApprovalEmail"
          @click="sendApprovalEmail"
          class="btn-primary"
        >
          Send Approval Email
        </button>
        <button
          @click="requestAdditionalInfo"
          class="btn-primary bg-yellow-600 hover:bg-yellow-700"
        >
          Request Additional Info
        </button>
        <button
          v-if="canCreateInvoice"
          @click="showInvoiceModal = true"
          class="btn-primary bg-purple-600 hover:bg-purple-700"
        >
          Create Invoice
        </button>
      </div>
    </div>

    <!-- Timeline/Process Flow -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <h2 class="text-xl font-bold text-white mb-6">Process Timeline</h2>
      <div class="space-y-4">
        <timeline-step
          v-for="(step, index) in processSteps"
          :key="step.id"
          :step="step"
          :is-current="step.id === application.status?.current_step"
          :is-completed="isStepCompleted(step.id)"
          :timestamp="getStepTimestamp(step.id)"
        />
      </div>
    </div>

    <!-- Documents -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <h2 class="text-xl font-bold text-white mb-4">Documents</h2>
      <div v-if="application.documents?.length > 0" class="space-y-3">
        <div
          v-for="doc in application.documents"
          :key="doc.id"
          class="flex items-center justify-between p-4 bg-primary-900/30 rounded-lg border border-primary-700/30"
        >
          <div class="flex items-center gap-3">
            <icon name="file" class="w-5 h-5 fill-gray-400" />
            <div>
              <div class="font-semibold text-white capitalize">{{ doc.type }}</div>
              <div class="text-sm text-gray-400">
                Sent: {{ doc.sent_at || 'Not sent' }}
              </div>
            </div>
          </div>
          <span
            class="px-3 py-1 rounded-full text-xs font-semibold"
            :class="getDocumentStatusClass(doc.status)"
          >
            {{ doc.status }}
          </span>
        </div>
      </div>
      <div v-else class="text-gray-400 text-center py-4">No documents yet</div>
    </div>

    <!-- Invoices -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <h2 class="text-xl font-bold text-white mb-4">Invoices</h2>
      <div v-if="application.invoices?.length > 0" class="space-y-3">
        <div
          v-for="invoice in application.invoices"
          :key="invoice.id"
          class="flex items-center justify-between p-4 bg-primary-900/30 rounded-lg border border-primary-700/30"
        >
          <div>
            <div class="font-semibold text-white">{{ invoice.invoice_number }}</div>
            <div class="text-sm text-gray-400">Amount: £{{ invoice.amount }}</div>
            <div class="text-xs text-gray-500">Due: {{ invoice.due_date }}</div>
          </div>
          <div class="flex items-center gap-3">
            <span
              class="px-3 py-1 rounded-full text-xs font-semibold"
              :class="getInvoiceStatusClass(invoice.status)"
            >
              {{ invoice.status }}
            </span>
            <button
              v-if="invoice.status === 'sent'"
              @click="markInvoiceAsPaid(invoice.id)"
              class="text-sm px-3 py-1 bg-green-600 hover:bg-green-700 rounded text-white"
            >
              Mark as Paid
            </button>
          </div>
        </div>
      </div>
      <div v-else class="text-gray-400 text-center py-4">No invoices yet</div>
    </div>

    <!-- Gateway Integration -->
    <div v-if="application.gateway" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <h2 class="text-xl font-bold text-white mb-4">Gateway Integration</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
          <div class="text-sm text-gray-400 mb-1">Provider</div>
          <div class="text-lg font-semibold text-white capitalize">{{ application.gateway.provider }}</div>
        </div>
        <div v-if="application.gateway.merchant_id">
          <div class="text-sm text-gray-400 mb-1">Merchant ID</div>
          <div class="text-lg font-semibold text-white">{{ application.gateway.merchant_id }}</div>
        </div>
        <div>
          <div class="text-sm text-gray-400 mb-1">Status</div>
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
import Icon from '@/Shared/Icon.vue'
import Layout from '@/Shared/Layout.vue'
import TimelineStep from '@/Shared/TimelineStep.vue'
import InvoiceModal from '@/Shared/InvoiceModal.vue'
import AdditionalInfoModal from '@/Shared/AdditionalInfoModal.vue'
import axios from 'axios'

export default {
  components: {
    Head,
    Link,
    Icon,
    TimelineStep,
    InvoiceModal,
    AdditionalInfoModal,
  },
  layout: Layout,
  props: {
    application: Object,
  },
  data() {
    return {
      showInvoiceModal: false,
      showAdditionalInfoModal: false,
      isLoading: false,
      processSteps: [
        { id: 'created', label: 'Application Created' },
        { id: 'application_sent', label: 'Contract Sent' },
        { id: 'contract_completed', label: 'Contract Signed' },
        { id: 'contract_submitted', label: 'Contract Submitted' },
        { id: 'application_approved', label: 'Application Approved' },
        { id: 'approval_email_sent', label: 'Approval Email Sent' },
        { id: 'invoice_sent', label: 'Invoice Sent' },
        { id: 'invoice_paid', label: 'Payment Received' },
        { id: 'gateway_integrated', label: 'Gateway Integrated & Tested' },
        { id: 'account_live', label: 'Account Live' },
      ],
    }
  },
  computed: {
    canSendContract() {
      return this.application.status?.current_step === 'created'
    },
    canApprove() {
      return this.application.status?.current_step === 'contract_submitted'
    },
    canSendApprovalEmail() {
      return this.application.status?.current_step === 'application_approved'
    },
    canCreateInvoice() {
      return ['application_approved', 'approval_email_sent'].includes(this.application.status?.current_step)
    },
  },
  methods: {
    formatStatus(status) {
      const statusMap = {
        created: 'Created',
        application_sent: 'Contract Sent',
        contract_completed: 'Contract Signed',
        contract_submitted: 'Submitted',
        application_approved: 'Approved',
        approval_email_sent: 'Approval Sent',
        invoice_sent: 'Invoice Sent',
        invoice_paid: 'Payment Received',
        gateway_integrated: 'Integration Complete',
        account_live: 'Live',
      }
      return statusMap[status] || status
    },
    isStepCompleted(stepId) {
      const currentIndex = this.processSteps.findIndex(s => s.id === this.application.status?.current_step)
      const stepIndex = this.processSteps.findIndex(s => s.id === stepId)
      return stepIndex <= currentIndex
    },
    getStepTimestamp(stepId) {
      return this.application.status?.timestamps?.[stepId]
    },
    getDocumentStatusClass(status) {
      const classes = {
        pending: 'bg-gray-700 text-gray-300',
        sent: 'bg-blue-900/50 text-blue-300',
        viewed: 'bg-yellow-900/50 text-yellow-300',
        completed: 'bg-green-900/50 text-green-300',
        declined: 'bg-red-900/50 text-red-300',
      }
      return classes[status] || classes.pending
    },
    getInvoiceStatusClass(status) {
      const classes = {
        draft: 'bg-gray-700 text-gray-300',
        sent: 'bg-blue-900/50 text-blue-300',
        paid: 'bg-green-900/50 text-green-300',
        overdue: 'bg-red-900/50 text-red-300',
        cancelled: 'bg-gray-700 text-gray-400',
      }
      return classes[status] || classes.draft
    },
    getGatewayStatusClass(status) {
      const classes = {
        pending: 'bg-gray-700 text-gray-300',
        in_progress: 'bg-blue-900/50 text-blue-300',
        testing: 'bg-yellow-900/50 text-yellow-300',
        live: 'bg-green-900/50 text-green-300',
        failed: 'bg-red-900/50 text-red-300',
      }
      return classes[status] || classes.pending
    },
    async sendContractLink() {
      if (this.isLoading) return

      this.isLoading = true

      try {
        const response = await axios.post(`/applications/${this.application.id}/send-contract`)
        
        if (response.data.success && response.data.signing_url) {
          // Open DocuSign in a new tab
          window.open(response.data.signing_url, '_blank', 'noopener,noreferrer')
          
          // Show success message
          this.$page.props.flash = {
            success: 'Contract sent! DocuSign opened in new tab.'
          }
          
          // Reload the page to show updated status
          setTimeout(() => {
            this.$inertia.reload()
          }, 1000)
        } else {
          this.$page.props.flash = {
            error: response.data.message || 'Failed to send contract'
          }
        }
      } catch (error) {
        console.error('DocuSign error:', error)
        this.$page.props.flash = {
          error: error.response?.data?.message || 'Failed to send contract. Please try again.'
        }
      } finally {
        this.isLoading = false
      }
    },
    markAsApproved() {
      this.$inertia.post(`/applications/${this.application.id}/approve`)
    },
    sendApprovalEmail() {
      this.$inertia.post(`/applications/${this.application.id}/send-approval-email`)
    },
    requestAdditionalInfo() {
      this.showAdditionalInfoModal = true
    },
    markInvoiceAsPaid(invoiceId) {
      if (confirm('Mark this invoice as paid?')) {
        this.$inertia.post(`/invoices/${invoiceId}/mark-paid`, {
          payment_method: 'Bank Transfer',
        })
      }
    },
  },
}
</script>