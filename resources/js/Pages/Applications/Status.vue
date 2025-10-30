<template>
  <div>
    <Head :title="`Status - ${application.name}`" />

    <div class="flex items-center justify-between mb-6">
      <div class="flex-1">
        <h1 class="text-3xl font-bold text-white mb-3">Application Status</h1>

        <!-- Quick Navigation -->
        <div class="flex flex-wrap gap-2">
          <button
            v-for="section in sections"
            :key="section.id"
            @click="scrollToSection(section.id)"
            class="text-sm px-3 py-1.5 bg-primary-900/50 hover:bg-primary-800/50 text-magenta-300 hover:text-magenta-200 rounded-lg border border-primary-700/30 transition-colors"
          >
            {{ section.label }}
          </button>
        </div>
      </div>

      <Link 
        href="/progress-tracker" 
        class="text-magenta-400 hover:text-magenta-300 flex items-center gap-2 ml-6"
      >
        <icon name="arrow-left" class="w-4 h-4 fill-current" />
        Back to Tracker
      </Link>
    </div>

    <!-- Application Info -->
    <div id="section-application-info" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6 scroll-mt-6">
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
    <div id="section-fee-structure" class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-primary-800/30 shadow-2xl overflow-hidden mb-6 scroll-mt-6">
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
      </div>
    </div>

    <!-- Progress Bar -->
    <div id="section-progress" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6 scroll-mt-6">
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

    <!-- Alert for Additional Info - Shows ALL requests with their notes -->
    <div 
      v-if="allAdditionalInfoRequests.length > 0" 
      class="bg-orange-900/10 border border-yellow-700/50 rounded-xl p-4 mb-6"
    >
      <div class="flex items-start gap-3">
        <icon name="alert-circle" class="w-6 h-6 fill-orange-400 flex-shrink-0 mt-0.5" />
        <div class="flex-1">
          <h3 class="text-lg font-semibold text-orange-300/80 mb-3">Additional Information Requests</h3>

          <!-- All additional info requests -->
          <div class="space-y-3">
            <!-- Document Requests (before documents_approved) -->
            <div v-if="documentRequests.length > 0">
              <p class="text-sm font-semibold text-blue-300 mb-3">📋 Document Requests:</p>
              
              <!-- Pending Document Requests -->
              <div v-if="pendingDocumentRequests.length > 0" class="space-y-2 mb-4">
                <p class="text-xs text-yellow-400 font-semibold uppercase tracking-wide mb-2">⏳ Pending Upload:</p>
                <div 
                  v-for="doc in pendingDocumentRequests" 
                  :key="doc.id"
                  class="bg-yellow-900/20 border border-yellow-700/30 rounded-lg p-3"
                >
                  <div class="flex items-start justify-between mb-2">
                    <div class="flex-1">
                      <p class="font-semibold text-yellow-200">{{ doc.document_name }}</p>
                    </div>
                    <span class="ml-3 px-2 py-1 bg-yellow-600 text-yellow-100 text-xs font-semibold rounded">
                      Pending
                    </span>
                  </div>
                  
                  <!-- Request Message -->
                  <div v-if="doc.notes" class="mb-2 p-2 bg-red-900/20 border border-red-700/30 rounded">
                    <p class="text-xs font-semibold text-red-300 mb-1">Request Message:</p>
                    <p class="text-sm text-gray-300 whitespace-pre-wrap">{{ doc.notes }}</p>
                  </div>
                  
                  <!-- Document Instructions -->
                  <div v-if="doc.instructions && doc.document_name !== 'General Additional Information'" class="mb-2">
                    <p class="text-xs font-semibold text-yellow-300 mb-1">Document Instructions:</p>
                    <p class="text-sm text-gray-400 whitespace-pre-wrap">{{ doc.instructions }}</p>
                  </div>
                  
                  <p class="text-xs text-gray-500 mt-2">
                    Requested by {{ doc.requested_by }} on {{ doc.requested_at }}
                  </p>
                </div>
              </div>

              <!-- Completed Document Requests -->
              <div v-if="completedDocumentRequests.length > 0" class="space-y-2">
                <p class="text-xs text-green-400 font-semibold uppercase tracking-wide mb-2">✓ Completed:</p>
                <div 
                  v-for="doc in completedDocumentRequests" 
                  :key="doc.id"
                  class="bg-green-900/20 border border-green-700/30 rounded-lg p-3"
                >
                  <div class="flex items-start justify-between mb-2">
                    <div class="flex-1">
                      <p class="font-semibold text-green-200">{{ doc.document_name }}</p>
                    </div>
                    <span class="ml-3 px-2 py-1 bg-green-600 text-green-100 text-xs font-semibold rounded">
                      Complete
                    </span>
                  </div>
                  
                  <!-- Request Message -->
                  <div v-if="doc.notes" class="mb-2 p-2 bg-green-900/20 border border-green-700/30 rounded">
                    <p class="text-xs font-semibold text-green-300 mb-1">Request Message:</p>
                    <p class="text-sm text-gray-300 whitespace-pre-wrap">{{ doc.notes }}</p>
                  </div>
                  
                  <!-- Document Instructions -->
                  <div v-if="doc.instructions && doc.document_name !== 'General Additional Information'" class="mb-2">
                    <p class="text-xs font-semibold text-green-300 mb-1">Document Instructions:</p>
                    <p class="text-sm text-gray-400 whitespace-pre-wrap">{{ doc.instructions }}</p>
                  </div>
                  
                  <p class="text-xs text-gray-500 mt-2">
                    Requested by {{ doc.requested_by }} on {{ doc.requested_at }}
                  </p>
                  <p class="text-xs text-green-400 mt-1">
                    ✓ Completed on {{ doc.uploaded_at }}
                  </p>
                </div>
              </div>
            </div>

            <!-- General Info Requests (after documents_approved or no document required) -->
            <div v-if="generalInfoRequests.length > 0" :class="{ 'mt-6 pt-6 border-t border-primary-800/30': documentRequests.length > 0 }">
              <p class="text-sm font-semibold text-purple-300 mb-3">💬 General Information Requests:</p>
              <div class="space-y-2">
                <div 
                  v-for="doc in generalInfoRequests" 
                  :key="doc.id"
                  class="bg-purple-900/20 border border-purple-700/30 rounded-lg p-3"
                >
                  <div class="flex items-start justify-between mb-2">
                    <div class="flex-1">
                      <p class="font-semibold text-purple-200">Information Request</p>
                    </div>
                    <span class="ml-3 px-2 py-1 bg-purple-600 text-purple-100 text-xs font-semibold rounded">
                      Info Only
                    </span>
                  </div>
                  
                  <!-- Request Message -->
                  <div v-if="doc.notes" class="mb-2 p-2 bg-purple-900/20 border border-purple-700/30 rounded">
                    <p class="text-xs font-semibold text-purple-300 mb-1">Request Message:</p>
                    <p class="text-sm text-gray-300 whitespace-pre-wrap">{{ doc.notes }}</p>
                  </div>
                  
                  <p class="text-xs text-gray-500 mt-2">
                    Requested by {{ doc.requested_by }} on {{ doc.requested_at }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Action Buttons (only for users, not accounts) -->
    <div v-if="!is_account" id="section-actions" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6 scroll-mt-6">
      <h2 class="text-xl font-bold text-white mb-4">Quick Actions</h2>
      <div class="flex flex-wrap gap-3">
        <!-- Send Credentials Button (only if account hasn't logged in yet) -->
        <button
          v-if="!accountHasLoggedIn"
          @click="showCredentialsModal = true"
          class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <icon name="mail" class="w-4 h-4 fill-current" />
          Send Account Credentials
        </button>

        <button
          v-if="canApproveDocuments"
          @click="markDocumentsAsApproved"
          class="btn-primary"
        >
          Mark Documents as Completed
        </button>

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
          v-if="!is_account"
          @click="requestAdditionalInfo"
          class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <icon name="mail" class="w-4 h-4 fill-current" />
          Request Additional Info
        </button>

        <!-- Cancel Additional Info Reminder (if active) -->
        <button
          v-if="additionalInfoReminder"
          @click="cancelAdditionalInfoReminder"
          class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <icon name="x" class="w-4 h-4 fill-current" />
          Cancel Info Request Reminder
        </button>
      </div>
    </div>

    <!-- Progress Timeline -->
    <div id="section-timeline" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6 scroll-mt-6">
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
    <div v-if="application.documents?.length > 0" id="section-documents" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6 scroll-mt-6">
      <h2 class="text-xl font-bold text-white mb-4">Documents</h2>
      <div 
          v-for="(label, category) in documentCategoriesWithAdditional" 
          :key="category"
          class="mb-6 last:mb-0"
        >
          <h3 class="text-lg font-semibold text-gray-300 mb-2">{{ label }}</h3>
          <p class="text-sm text-gray-400 mb-3">{{ categoryDescriptionsWithAdditional[category] }}</p>

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
    <div v-if="application.invoices?.length > 0" id="section-invoices" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6 scroll-mt-6">
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
    <div v-if="application.gateway" id="section-gateway" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6 scroll-mt-6">
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

    <!-- Email History Section -->
    <div 
      id="section-email-history" v-if="application.email_logs?.length > 0" 
      class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6"
    >
      <h2 class="text-xl font-bold text-white mb-4">Application Email History</h2>
      <div class="space-y-3">
        <div
          v-for="log in application.email_logs"
          :key="log.id"
          class="flex items-start justify-between p-4 bg-dark-900/50 border border-primary-800/30 rounded-lg hover:bg-primary-900/20 transition-colors"
        >
          <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
              <span class="font-semibold text-white">{{ log.subject }}</span>
            </div>
            <div class="text-sm text-gray-400 space-y-1">
              <div>
                <span class="text-gray-500">To:</span> {{ log.recipient_email }}
              </div>
              <div>
                <span class="text-gray-500">Type:</span> {{ formatEmailType(log.email_type) }}
              </div>
              <div>
                <span class="text-gray-500">Sent:</span> {{ log.sent_at }}
              </div>
              <div v-if="log.opened && log.opened_at">
                <span class="text-gray-500">Opened:</span> {{ log.opened_at }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Scheduled Emails Section -->
    <div 
      v-if="application.scheduled_emails?.length > 0" 
      class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6"
    >
      <h2 class="text-xl font-bold text-white mb-4">Scheduled Email Reminders</h2>
      <div class="space-y-3">
        <div
          v-for="reminder in application.scheduled_emails"
          :key="reminder.id"
          class="flex items-center justify-between p-4 bg-blue-900/20 border border-blue-700/30 rounded-lg"
        >
          <div class="flex-1">
            <div class="font-semibold text-blue-300 mb-1">
              {{ formatEmailType(reminder.email_type) }}
            </div>
            <div class="text-sm text-gray-400">
              Sending {{ formatInterval(reminder.interval) }}
            </div>
            <div class="text-sm text-gray-500 mt-1">
              Next: {{ reminder.next_send_at }}
            </div>
          </div>
          <div class="flex items-center gap-2">
            <span class="px-2 py-1 bg-green-900/50 text-green-300 rounded text-xs font-semibold">
              Active
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Activity Log -->
    <div id="section-activity-log" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl scroll-mt-6">
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
      :has-active-reminder="!!additionalInfoReminder"
      :current-step="application.status?.current_step"
      :documents-approved="isStepCompleted('documents_approved')"
      @close="showAdditionalInfoModal = false"
    />

    <credentials-modal
      v-if="showCredentialsModal"
      :account-id="accountId"
      :application-id="application.id"
      :has-active-reminder="!!credentialsReminder"
      @close="showCredentialsModal = false"
    />
  </div>

  <!-- Scroll to Top Button -->
  <transition
    enter-active-class="transition-all duration-300 ease-out"
    leave-active-class="transition-all duration-200 ease-in"
    enter-from-class="opacity-0 translate-y-4"
    enter-to-class="opacity-100 translate-y-0"
    leave-from-class="opacity-100 translate-y-0"
    leave-to-class="opacity-0 translate-y-4"
  >
    <button
      v-if="showScrollTop"
      @click="scrollToTop"
      class="fixed bottom-6 right-6 z-50 w-12 h-12 bg-gradient-to-br from-primary-600 to-magenta-600 hover:from-primary-500 hover:to-magenta-500 text-white rounded-full shadow-2xl flex items-center justify-center transition-all duration-300 hover:scale-110 border border-primary-500/30"
      aria-label="Scroll to top"
    >
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
      </svg>
    </button>
  </transition>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import TimelineStep from '@/Shared/TimelineStep.vue'
import InvoiceModal from '@/Shared/InvoiceModal.vue'
import AdditionalInfoModal from '@/Shared/AdditionalInfoModal.vue'
import CredentialsModal from '@/Shared/CredentialsModal.vue'
import Icon from '@/Shared/Icon.vue'

export default {
  components: {
    Head,
    Link,
    TimelineStep,
    InvoiceModal,
    AdditionalInfoModal,
    CredentialsModal,
    Icon,
  },
  layout: Layout,
  props: {
    application: Object,
    is_account: Boolean,
    additionalInfoReminder: Object,
    credentialsReminder: Object,
    accountId: Number,
    accountHasLoggedIn: Boolean,
    documentCategories: Object,
    categoryDescriptions: Object,
  },
  data() {
    return {
      confirmingFees: false,
      showScrollTop: false,
      isLoading: false,
      showInvoiceModal: false,
      showAdditionalInfoModal: false,
      showCredentialsModal: false,
      processSteps: [
        { id: 'created', label: 'Application Created', description: 'Initial application setup' },
        { id: 'documents_uploaded', label: 'Documents Uploaded', description: 'All required documents uploaded' },
        { id: 'documents_approved', label: 'Documents Approved', description: 'Documents reviewed and approved' },
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
    sections() {
      const baseSections = [
        { id: 'section-application-info', label: 'Application Info' },
        { id: 'section-fee-structure', label: 'Fee Structure' },
        { id: 'section-progress', label: 'Progress' },
        { id: 'section-timeline', label: 'Timeline' },
      ]

      if (!this.is_account) {
        baseSections.push({ id: 'section-actions', label: 'Actions' })
      }

      if (this.application.documents?.length > 0) {
        baseSections.push({ id: 'section-documents', label: 'Documents' })
      }

      if (this.application.invoices?.length > 0) {
        baseSections.push({ id: 'section-invoices', label: 'Invoices' })
      }

      if (this.application.gateway) {
        baseSections.push({ id: 'section-gateway', label: 'Gateway' })
      }

      baseSections.push({ id: 'section-email-history', label: 'Emails' })
      baseSections.push({ id: 'section-activity-log', label: 'Activity Log' })

      return baseSections
    },

    allAdditionalInfoRequests() {
      return this.application.additional_documents || []
    },

    // Document requests are those made before documents_approved (with actual documents to upload)
    documentRequests() {
      return this.allAdditionalInfoRequests.filter(doc => {
        // If it's marked as "General Additional Information", it's not a real document request
        return doc.document_name !== 'General Additional Information'
      })
    },

    // Pending document requests
    pendingDocumentRequests() {
      return this.documentRequests.filter(doc => !doc.is_uploaded)
    },

    // Completed document requests
    completedDocumentRequests() {
      return this.documentRequests.filter(doc => doc.is_uploaded)
    },

    // General info requests (either sent after documents_approved OR marked as "General Additional Information")
    generalInfoRequests() {
      return this.allAdditionalInfoRequests.filter(doc => {
        return doc.document_name === 'General Additional Information'
      })
    },

    // Aliases for compatibility with other sections
    pendingAdditionalDocuments() {
      return this.pendingDocumentRequests
    },

    uploadedAdditionalDocuments() {
      return this.completedDocumentRequests
    },

    hasPendingAdditionalDocuments() {
      return this.pendingDocumentRequests.length > 0
    },

    canSendContract() {
      // Can only send contract after documents are approved
      return this.application.status?.current_step === 'documents_approved' && !this.is_account
    },

    canApprove() {
      return ['contract_submitted'].includes(this.application.status?.current_step) && !this.is_account
    },

    canCreateInvoice() {
      return this.application.status?.current_step === 'application_approved' && !this.is_account
    },

    canApproveDocuments() {
      // Can approve documents only when on documents_uploaded step (not account users)
      return this.application.status?.current_step === 'documents_uploaded' && !this.is_account
    },
    
    // Build document categories including all pending additional documents
    documentCategoriesWithAdditional() {
      const categories = { ...this.documentCategories }
      
      // Add all pending additional documents as separate categories
      this.pendingAdditionalDocuments.forEach(doc => {
        categories[`additional_requested_${doc.id}`] = doc.document_name
      })
      
      return categories
    },
    
    // Build category descriptions including all pending additional documents
    categoryDescriptionsWithAdditional() {
      const descriptions = { ...this.categoryDescriptions }
      
      // Add descriptions for all pending additional documents
      this.pendingAdditionalDocuments.forEach(doc => {
        descriptions[`additional_requested_${doc.id}`] = doc.instructions || 'Additional document requested by administrator'
      })
      
      return descriptions
    },

    // Helper to get the description for a specific category
    getCategoryDescription() {
      return (category) => {
        return this.categoryDescriptionsWithAdditional[category] || ''
      }
    },
  },
  methods: {
    scrollToSection(sectionId) {
      const element = document.getElementById(sectionId)
      if (element) {
        element.scrollIntoView({ behavior: 'smooth', block: 'start' })
      }
    },
    
    scrollToTop() {
      if (this.scrollContainer) {
        this.scrollContainer.scrollTo({ top: 0, behavior: 'smooth' })
      } else {
        window.scrollTo({ top: 0, behavior: 'smooth' })
      }
    },
    
    handleScroll() {
      const scrollTop = this.scrollContainer ? this.scrollContainer.scrollTop : (window.pageYOffset || document.documentElement.scrollTop || 0)
      this.showScrollTop = scrollTop > 300
    },
    
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
    
    formatEmailType(type) {
      const types = {
        'account_credentials': 'Account Credentials',
        'application_created': 'Application Created',
        'fees_changed': 'Fees Changed',
        'document_uploaded': 'Document Uploaded',
        'all_documents_uploaded': 'All Documents Uploaded',
        'additional_info_requested': 'Additional Info Requested',
        'application_approved': 'Application Approved',
      }
      return types[type] || type
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
    
    cancelAdditionalInfoReminder() {
      if (confirm('Cancel scheduled additional info reminders?')) {
        this.$inertia.post(`/applications/${this.application.id}/cancel-additional-info-reminder`)
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
      const timestamps = this.application.status?.timestamps
      
      // Steps that require explicit completion (manual actions)
      const manualSteps = ['documents_approved']
      
      // For manual steps, only show as complete if they have a timestamp
      if (manualSteps.includes(stepId)) {
        return !!timestamps?.[stepId]
      }
      
      // For automatic steps, use the order-based logic
      const stepOrder = [
        'created', 
        'documents_uploaded', 
        'documents_approved', 
        'application_sent', 
        'contract_completed', 
        'contract_submitted', 
        'application_approved',
        'approval_email_sent',
        'gateway_contract_sent',
        'gateway_contract_signed',
        'gateway_details_received',
        'wordpress_credentials_collected',
        'invoice_sent', 
        'invoice_paid', 
        'gateway_integrated', 
        'account_live'
      ]
      
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
    
    markDocumentsAsApproved() {
      if (confirm('Mark all documents as approved?')) {
        this.$inertia.post(`/applications/${this.application.id}/mark-documents-approved`)
      }
    },
    
    // Check if a category is an additional document
    isAdditionalDocumentCategory(category) {
      return category.startsWith('additional_requested_')
    },

    // Get additional document details by category
    getAdditionalDocumentByCategory(category) {
      if (!this.isAdditionalDocumentCategory(category)) return null
      
      const docId = parseInt(category.replace('additional_requested_', ''))
      return this.application.additional_documents?.find(doc => doc.id === docId)
    },
  },
  mounted() {
    this.$nextTick(() => {
      const mainContent = document.querySelector('[scroll-region]')

      if (mainContent) {
        mainContent.addEventListener('scroll', this.handleScroll, { passive: true })
        this.scrollContainer = mainContent
      } else {
        window.addEventListener('scroll', this.handleScroll, { passive: true })
      }

      setTimeout(() => this.handleScroll(), 100)
    })
  },
  beforeUnmount() {
    if (this.scrollContainer) {
      this.scrollContainer.removeEventListener('scroll', this.handleScroll)
    } else {
      window.removeEventListener('scroll', this.handleScroll)
    }
  }
}
</script>