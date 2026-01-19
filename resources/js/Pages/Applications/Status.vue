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
        Progress Tracker
      </Link>
    </div>

    <!-- Application Info -->
    <div id="section-application-info" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6 scroll-mt-6">
      <div class="flex justify-between">
        <div>
          <div class="text-sm text-gray-400 mb-1">Application Name</div>
          <div class="text-lg font-semibold text-white">{{ application.name }}</div>
        </div>
        <div v-if="application.account_name">
          <div class="text-sm text-gray-400 mb-1">Merchant Account Name</div>
          <div class="text-lg font-semibold text-white">{{ application.account_name }}</div>
        </div>
        <div v-if="application.account_recipient_name">
          <div class="text-sm text-gray-400 mb-1">Merchant Recipient Name</div>
          <div class="text-lg font-semibold text-white">{{ application.account_recipient_name }}</div>
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

    <div id="section-fee-structure" class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-primary-800/30 shadow-2xl overflow-hidden mb-6 scroll-mt-6">
      <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-xl font-bold text-magenta-400">Fee Structure</h2>
      </div>
      <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
            <div class="text-gray-400 text-sm mb-1">Monthly Minimum</div>
            <div class="text-2xl font-bold text-magenta-400">£{{ parseFloat(application.monthly_minimum).toFixed(2) }}</div>
            <div class="text-gray-500 text-xs mt-1">Made up of transactional fees</div>
          </div>
          <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
            <div class="text-gray-400 text-sm mb-1">Scaling Fee (+ VAT)</div>
            <div class="text-2xl font-bold text-magenta-400">£{{ parseFloat(application.scaling_fee).toFixed(2) }}</div>
            <div class="text-gray-500 text-xs mt-1">
            </div>
          </div>
          <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
            <div class="text-gray-400 text-sm mb-1">Transaction Fee</div>
            <div class="text-2xl font-bold text-magenta-400">{{ application.transaction_percentage }}% + £{{ parseFloat(application.transaction_fixed_fee).toFixed(2) }}</div>
            <div class="text-gray-500 text-xs mt-1">Per transaction</div>
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
          <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
            <div class="text-gray-400 text-sm mb-1">Monthly Fee</div>
            <div class="text-xl font-bold text-gray-300">£{{ parseFloat(application.monthly_fee).toFixed(2) }}</div>
            <div class="text-gray-500 text-xs mt-1">Fixed monthly charge</div>
          </div>
          <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
            <div class="text-gray-400 text-sm mb-1">Setup Fee</div>
            <div class="text-xl font-bold text-gray-300">£{{ parseFloat(application.setup_fee).toFixed(2) }}</div>
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
      
      <!-- Refresh Notice (Admin Only) -->
      <div v-if="canSendContract && !hasRefreshed" class="mb-4">
        <div class="inline-flex p-3 bg-blue-900/20 border border-blue-700/30 rounded-lg">
          <div class="flex items-center gap-4">
            <p class="text-sm text-blue-300">
              <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Refresh the page to activate the DocuSign button
            </p>
            <button
              @click="refreshPage"
              class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2 text-sm"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              Refresh Page
            </button>
          </div>
        </div>
      </div>

      <div class="flex flex-wrap gap-3">

        <button
          v-if="canSendContract"
          @click="sendContractLink"
          :disabled="isLoading || !hasRefreshed"
          class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          :title="!hasRefreshed ? 'Please refresh the page first' : ''"
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
          <span v-else-if="!hasRefreshed">Open Contract (Refresh Required)</span>
          <span v-else>Open Contract Link (DocuSign)</span>
        </button>

        <!-- Send Contract Link Button -->
        <button
          v-if="canSendContractReminder"
          @click="showContractReminderModal = true"
          class="px-6 py-2 bg-blue-300/50 hover:bg-blue-400/50 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <icon name="mail" class="w-4 h-4 fill-current" />
          Send Contract Link To Merchant
        </button>

        <!-- Cancel Contract Reminder (if active) -->
        <button
          v-if="contractReminder"
          @click="cancelContractReminder"
          class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <icon name="x" class="w-4 h-4 fill-current" />
          Cancel Contract Reminder
        </button>

        <button
          v-if="canApprove"
          @click="markAsApproved"
          class="btn-tertiary"
        >
          Approve Application
        </button>

                <!-- Send Credentials Button (only if account hasn't logged in yet) -->
                <button
          @click="showCredentialsModal = true"
          class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <icon name="mail" class="w-4 h-4 fill-current" />
          Send G2Pay Credentials
        </button>


        <button
          v-if="!is_account"
          @click="requestAdditionalInfo"
          class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors flex items-center gap-2"
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

        <button
          v-if="canApproveDocuments"
          @click="markDocumentsAsApproved"
          class="btn-tertiary flex items-center gap-2"
        >
          <Check class="w-4 h-4 text-white" />
          Mark Documents as Completed
        </button>

        <!-- Submit to CardStream Button (shows when contract_signed) -->
        <button
          v-if="canSubmitToCardStream"
          @click="showSubmitCardStreamModal = true"
          class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Submit to CardStream
        </button>

        <!-- Invoice Reminder Button -->
        <button
          v-if="canSendInvoiceReminder"
          @click="sendInvoiceReminder"
          class="px-6 py-2 bg-purple-800 hover:bg-purple-900 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <icon name="mail" class="w-4 h-4 fill-current" />
          Send Invoice Reminder (Xero)
        </button>

        <!-- Mark Invoice as Paid Button -->
        <button
          v-if="canMarkInvoiceAsPaid"
          @click="markInvoiceAsPaid"
          class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Mark Invoice as Paid
        </button>

        <!-- Send CardStream Credentials Button -->
        <button
          v-if="canSendCardStreamCredentials"
          @click="sendCardStreamCredentials"
          class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <icon name="mail" class="w-4 h-4 fill-current" />
          Send CardStream Credentials
        </button>

        <!-- Cancel CardStream Reminder -->
        <button
          v-if="cardstreamReminder"
          @click="cancelCardStreamReminder"
          class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <icon name="x" class="w-4 h-4 fill-current" />
          Cancel CardStream Reminder
        </button>

        <!-- Mark Gateway Integrated Button -->
        <button
          v-if="canMarkGatewayIntegrated"
          @click="markGatewayIntegrated"
          class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          Mark Gateway Integrated
        </button>

        <!-- Request WordPress Credentials Button -->
        <button
          v-if="canRequestWordPress"
          @click="requestWordPressCredentials"
          class="px-6 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <icon name="mail" class="w-4 h-4 fill-current" />
          Request WordPress Credentials
        </button>

        <!-- Cancel WordPress Reminder -->
        <button
          v-if="wordpressReminder"
          @click="cancelWordPressReminder"
          class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <icon name="x" class="w-4 h-4 fill-current" />
          Cancel WordPress Reminder
        </button>

        <!-- Make Account Live Button -->
        <button
          v-if="canMakeAccountLive"
          @click="makeAccountLive"
          class="px-6 py-2 bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-500 hover:to-emerald-500 text-white rounded-lg transition-all flex items-center gap-2 font-semibold shadow-lg"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          🎉 Make Account Live
        </button>
      </div>
    </div>


    <div id="section-timeline" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6 scroll-mt-6">
      <h2 class="text-xl font-bold text-white mb-6">Progress Timeline</h2>
      
      <!-- Grid layout: timeline on left, manual controls on right -->
      <div class="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-6">
        
        <!-- Timeline Steps Column -->
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

        <!-- Manual Transition Controls Column (Admin Only) -->
        <div v-if="!is_account" class="lg:border-l lg:border-primary-800/30 lg:pl-6">
          <div class="sticky top-6">
            <h3 class="text-lg font-semibold text-magenta-400 mb-4 flex items-center gap-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
              </svg>
              Manual Override
            </h3>
            
            <div class="space-y-3">
              <div
                v-for="step in processSteps"
                :key="'manual-' + step.id"
                class="flex items-center gap-3"
              >
                <button
                  @click="confirmManualTransition(step.id, step.label)"
                  :disabled="isManualTransitioning"
                  class="flex-1 px-3 py-2 text-sm rounded-lg transition-all duration-200 flex items-center justify-between gap-2"
                  :class="[
                    application.status?.current_step === step.id
                      ? 'bg-magenta-600/30 text-magenta-300 border border-magenta-500/50 cursor-not-allowed'
                      : isStepCompleted(step.id)
                      ? 'bg-green-900/20 text-green-400 border border-green-700/30 hover:bg-green-900/30'
                      : 'bg-primary-900/30 text-gray-400 border border-primary-700/30 hover:bg-primary-800/50 hover:text-gray-300',
                    isManualTransitioning ? 'opacity-50 cursor-wait' : ''
                  ]"
                >
                  <span class="truncate">{{ step.label }}</span>
                  
                  <!-- Current step indicator -->
                  <svg 
                    v-if="application.status?.current_step === step.id"
                    class="w-4 h-4 flex-shrink-0" 
                    fill="currentColor" 
                    viewBox="0 0 20 20"
                  >
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                  </svg>
                  
                  <!-- Completed indicator -->
                  <svg 
                    v-else-if="isStepCompleted(step.id)"
                    class="w-4 h-4 flex-shrink-0" 
                    fill="currentColor" 
                    viewBox="0 0 20 20"
                  >
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                  </svg>
                  
                  <!-- Arrow forward indicator -->
                  <svg 
                    v-else
                    class="w-4 h-4 flex-shrink-0" 
                    fill="none" 
                    stroke="currentColor" 
                    viewBox="0 0 24 24"
                  >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                  </svg>
                </button>
              </div>
            </div>

            <div class="mt-4 p-3 bg-yellow-900/20 border border-yellow-700/30 rounded-lg">
              <div class="flex items-start gap-2">
                <svg class="w-4 h-4 text-yellow-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <div class="text-xs text-yellow-300">
                  <p class="font-semibold mb-1">Manual Override Warning</p>
                  <p>Manually transitioning will complete all intermediate steps without triggering automated actions (emails, submissions, etc.).</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- In the merchant actions section, update the Sign Contract button -->
    <div v-if="is_account" id="section-actions" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6 scroll-mt-6">
      <h2 class="text-xl font-bold text-white mb-4">Your Actions</h2>
      
      <!-- Refresh Notice (Merchant Only) -->
      <div v-if="canAccountSignContract && !hasRefreshed" class="mb-4">
        <div class="inline-flex p-3 bg-blue-900/20 border border-blue-700/30 rounded-lg">
          <div class="flex items-center gap-4">
            <p class="text-sm text-blue-300">
              <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              Refresh the page to activate the Sign Contract button
            </p>
            <button
              @click="refreshPage"
              class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2 text-sm"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              Refresh Page
            </button>
          </div>
        </div>
      </div>
      
      <div class="flex flex-wrap gap-3">

        <!-- Sign Contract Button - generates fresh signing URL -->
        <a      
          v-if="canAccountSignContract && application.is_imported && application.docusign_envelope_url && hasRefreshed"
          :href="application.docusign_envelope_url"
          target="_blank"
          class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg flex items-center gap-2"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
          </svg>
          View Contract in DocuSign
        </a>

        <!-- Disabled version for imported contracts -->
        <button
          v-else-if="canAccountSignContract && application.is_imported && application.docusign_envelope_url && !hasRefreshed"
          disabled
          class="px-4 py-2 bg-green-600/50 text-white rounded-lg flex items-center gap-2 opacity-50 cursor-not-allowed"
          title="Please refresh the page first"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
          </svg>
          View Contract (Refresh Required)
        </button>

        <!-- G2Pay-created contracts: generate signing URL -->
        <button
          v-else-if="canAccountSignContract && !application.is_imported"
          @click="openContractForAccount"
          :disabled="isLoadingContract || !hasRefreshed"
          class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg flex items-center gap-2"
          :title="!hasRefreshed ? 'Please refresh the page first' : ''"
        >
          <svg 
            v-if="isLoadingContract" 
            class="animate-spin w-4 h-4" 
            fill="none" 
            viewBox="0 0 24 24"
          >
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
          </svg>
          <span v-if="isLoadingContract">Opening Contract...</span>
          <span v-else-if="!hasRefreshed">Sign Contract (Refresh Required)</span>
          <span v-else>Sign Contract</span>
        </button>

        <!-- Send Message to Administrator Button -->
        <button
          @click="sendMessageToUser"
          class="px-6 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <icon name="mail" class="w-4 h-4 fill-current" />
          Send Message to Administrator
        </button>

        <!-- Cancel Message Reminder (if active) -->
        <button
          v-if="accountMessageReminder"
          @click="cancelAccountMessageReminder"
          class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <icon name="x" class="w-4 h-4 fill-current" />
          Cancel Message Reminder
        </button>
      </div>
    </div>

    <div v-if="hasDocuSignRecipientStatus && !is_account" id="section-contracts" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6 scroll-mt-6">
      <h2 class="text-xl font-bold text-white mb-4">Contract Signing Status</h2>
      <div class="space-y-3">
        <div
          v-for="(recipient, index) in docusignRecipientStatus"
          :key="index"
          class="flex items-center justify-between p-4 bg-dark-900/50 border border-primary-800/30 rounded-lg"
        >
          <div class="flex items-center gap-3">
            <!-- Status Icon -->
            <svg 
              v-if="['completed', 'signed'].includes(recipient.status)" 
              class="w-6 h-6 text-green-400" 
              fill="currentColor" 
              viewBox="0 0 20 20"
            >
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
            </svg>
            <svg 
              v-else-if="recipient.status === 'delivered'" 
              class="w-6 h-6 text-blue-400" 
              fill="currentColor" 
              viewBox="0 0 20 20"
            >
              <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
              <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
            </svg>
            <svg 
              v-else 
              class="w-6 h-6 text-yellow-400" 
              fill="currentColor" 
              viewBox="0 0 20 20"
            >
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
            </svg>
            
            <!-- Recipient Info -->
            <div>
              <div class="text-white font-medium">{{ recipient.name }}</div>
              <div class="text-sm text-gray-400">{{ recipient.email }}</div>
              <div v-if="recipient.signed_at" class="text-xs text-green-400 mt-1">
                Signed: {{ recipient.signed_at }}
              </div>
              <div v-else-if="recipient.delivered_at" class="text-xs text-blue-400 mt-1">
                Viewed: {{ recipient.delivered_at }}
              </div>
            </div>
          </div>

          <!-- Status Badge -->
          <span
            class="px-3 py-1 rounded-full text-sm font-semibold"
            :class="{
              'bg-green-900/50 text-green-300': ['completed', 'signed'].includes(recipient.status),
              'bg-blue-900/50 text-blue-300': recipient.status === 'delivered',
              'bg-yellow-900/50 text-yellow-300': recipient.status === 'sent',
              'bg-gray-700 text-gray-300': !['completed', 'signed', 'delivered', 'sent'].includes(recipient.status),
            }"
          >
            {{ formatStatus(recipient.status) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Documents Section -->
    <div v-if="canUploadDocs" id="section-documents" class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6 scroll-mt-6">
      <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30 flex items-center justify-between -mx-6 -mt-6 mb-6">
        <h2 class="text-magenta-400 font-bold text-lg">Documents</h2>
        <!-- Button Group -->
        <div class="flex items-center gap-3">
          <button 
            v-if="canUploadDocs"
            @click="openUploadModal()" 
            class="px-4 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors text-sm font-medium"
          >
            Upload Document
          </button>
          
          <Link
            :href="getDocumentLibraryUrl()"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm font-medium flex items-center gap-2"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
            </svg>
            Document Library
          </Link>
        </div>
      </div>

      <!-- Explanation for Merchant Directors (only for accounts if they can upload) -->
      <div v-if="is_account && canUploadDocs" class="mb-6 p-4 bg-blue-900/20 border border-blue-700/30 rounded-lg">
        <div class="flex items-start gap-3">
          <svg class="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <div>
            <p class="text-blue-300 font-medium mb-1">Important: Individual Document Requirements</p>
            <p class="text-sm text-gray-300">Each Merchant Director must upload their own documents for every category below. All directors are required to provide their individual documentation.</p>
          </div>
        </div>
      </div>

      <div 
        v-for="(label, category) in documentCategoriesWithAdditional" 
        :key="category"
        class="mb-6 last:mb-0"
      >
        <div class="flex items-center justify-between mb-2">
          <h3 class="text-lg font-semibold text-gray-300">{{ label }}</h3>
          <button 
            v-if="canUploadDocs"
            @click="openUploadModal(category)" 
            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-sm rounded-lg transition-colors flex items-center gap-1"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
            </svg>
            Upload
          </button>
        </div>
        <p class="text-sm text-gray-400 mb-3">{{ categoryDescriptionsWithAdditional[category] }}</p>

        <div v-if="getDocumentsByCategory(category).length > 0" class="space-y-2">
          <div 
            v-for="doc in getDocumentsByCategory(category)"
            :key="doc.id"
            class="flex items-center justify-between bg-dark-900/50 border border-primary-800/30 rounded-lg p-3"
            :class="{ 'opacity-60': doc.dumped_at }"
          >
            <div class="flex items-center flex-1">
              <svg 
                v-if="doc.dumped_at"
                class="w-5 h-5 text-yellow-400 mr-3" 
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
              <svg 
                v-else
                class="w-5 h-5 text-green-400 mr-3" 
                fill="none" 
                stroke="currentColor" 
                viewBox="0 0 24 24"
              >
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div>
                <span class="text-gray-300">{{ doc.original_filename || 'Document' }}</span>
                <div v-if="doc.dumped_at" class="text-xs text-yellow-400 mt-1">
                  ⚠️ File removed {{ formatDate(doc.dumped_at) }} - {{ doc.dumped_reason }}
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <template v-if="!doc.dumped_at">
                <a 
                  :href="`/applications/${application.id}/documents/${doc.id}/download`"
                  class="text-blue-400 hover:text-blue-300 text-sm"
                >
                  Download
                </a>
              </template>
              <span v-else class="text-xs text-gray-500">Removed</span>
            </div>
          </div>
        </div>
        
        <!-- Only show "No documents" for BASE categories, not additional requested ones -->
        <div 
          v-else-if="!category.startsWith('additional_requested_')" 
          class="bg-yellow-900/20 border border-yellow-700/30 rounded-lg p-3"
        >
          <p class="text-yellow-300 text-sm">No documents uploaded yet</p>
        </div>
      </div>
      
      <!-- Extra Documents (Library Uploads) - No Upload Button -->
      <div v-if="hasExtraDocuments" class="mt-8 pt-6 border-t border-primary-800/30">
        <h3 class="text-lg font-semibold text-gray-300 mb-4">
          📁 Extra Documents
          <span class="text-sm text-gray-500 font-normal ml-2">(Uploaded from Document Library)</span>
        </h3>
        
        <div 
          v-for="(label, category) in application.extra_document_categories" 
          :key="category"
          class="mb-6 last:mb-0"
        >
          <div class="flex items-center justify-between mb-2">
            <h4 class="text-md font-semibold text-gray-400">{{ label }}</h4>
            <!-- NO UPLOAD BUTTON -->
          </div>
          
          <div v-if="getDocumentsByCategory(category).length > 0" class="space-y-2">
            <div 
              v-for="doc in getDocumentsByCategory(category)"
              :key="doc.id"
              class="flex items-center justify-between bg-dark-900/50 border border-primary-800/30 rounded-lg p-3"
              :class="{ 'opacity-60': doc.dumped_at }"
            >
              <div class="flex items-center flex-1">
                <svg 
                  v-if="doc.dumped_at"
                  class="w-5 h-5 text-yellow-400 mr-3" 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <svg 
                  v-else
                  class="w-5 h-5 text-green-400 mr-3" 
                  fill="none" 
                  stroke="currentColor" 
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <div>
                  <span class="text-gray-300">{{ doc.original_filename || 'Document' }}</span>
                  <div v-if="doc.dumped_at" class="text-xs text-yellow-400 mt-1">
                    ⚠️ File removed {{ formatDate(doc.dumped_at) }} - {{ doc.dumped_reason }}
                  </div>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <template v-if="!doc.dumped_at">
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
                    Delete File
                  </button>
                </template>
                <div v-else class="px-3 py-1 bg-yellow-900/20 border border-yellow-700/30 rounded text-xs text-yellow-300">
                  File Removed
                </div>
              </div>
            </div>
          </div>
          <div v-else class="bg-yellow-900/20 border border-yellow-700/30 rounded-lg p-3">
            <p class="text-yellow-300 text-sm">No documents in this category yet</p>
          </div>
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

    <!-- Document Upload Modal (for accounts) -->
    <document-upload-modal 
      :show="showDocumentUploadModal"
      :application-id="application.id"
      :categories="documentCategories"
      :category-descriptions="categoryDescriptions"
      :preselected-category="preselectedCategory"
      @close="showDocumentUploadModal = false; preselectedCategory = null"
    />

    <credentials-modal
      v-if="showCredentialsModal"
      :account-id="accountId"
      :application-id="application.id"
      :has-active-reminder="!!credentialsReminder"
      @close="showCredentialsModal = false"
    />

    <!-- Contract Reminder Modal -->
    <contract-reminder-modal
      v-if="showContractReminderModal"
      :application-id="application.id"
      :has-active-reminder="!!contractReminder"
      @close="showContractReminderModal = false"
    />

    <!-- Submit to CardStream Modal -->
    <submit-to-card-stream-modal
      v-if="showSubmitCardStreamModal"
      :show="showSubmitCardStreamModal"
      :application-id="application.id"
      :application-name="application.name"
      :account-name="accountName"
      :account-email="accountEmail"
      :account-mobile="accountMobile"
      :recipient-status="docusignRecipientStatus"
      @close="showSubmitCardStreamModal = false"
    />

    <card-stream-credentials-modal
      v-if="showCardStreamCredentialsModal"
      :show="showCardStreamCredentialsModal"
      :application-id="application.id"
      :has-active-reminder="!!cardstreamReminder"
      @close="showCardStreamCredentialsModal = false"
    />

    <word-press-credentials-modal
      v-if="showWordPressRequestModal"
      :show="showWordPressRequestModal"
      :application-id="application.id"
      :is-request-mode="true"
      :has-active-reminder="!!wordpressReminder"
      @close="showWordPressRequestModal = false"
    />

    <word-press-credentials-modal
      v-if="showWordPressEnterModal"
      :show="showWordPressEnterModal"
      :application-id="application.id"
      :is-request-mode="false"
      @close="showWordPressEnterModal = false"
    />

    <account-message-modal
      v-if="showAccountMessageModal"
      :application-id="application.id"
      :has-active-reminder="!!accountMessageReminder"
      @close="showAccountMessageModal = false"
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
import { Check } from 'lucide-vue-next'

import ContractReminderModal from '@/Shared/ContractReminderModal.vue'
import SubmitToCardStreamModal from '@/Shared/SubmitToCardStreamModal.vue'
import CardStreamCredentialsModal from '@/Shared/CardStreamCredentialsModal.vue'
import WordPressCredentialsModal from '@/Shared/WordPressCredentialsModal.vue'
import AccountMessageModal from '@/Shared/AccountMessageModal.vue'
import DocumentUploadModal from '@/Shared/DocumentUploadModal.vue'


export default {
  components: {
    Head,
    Link,
    Check,
    TimelineStep,
    InvoiceModal,
    AdditionalInfoModal,
    CredentialsModal,
    ContractReminderModal,
    SubmitToCardStreamModal,
    CardStreamCredentialsModal,
    WordPressCredentialsModal,
    AccountMessageModal,
    DocumentUploadModal,
    Icon,
  },
  layout: Layout,
  props: {
    application: Object,
    is_account: Boolean,
    justLoggedIn: Boolean, 
    additionalInfoReminder: Object,
    credentialsReminder: Object,
    accountId: Number,
    accountName: String,
    accountEmail: String,
    accountMobile: Number,
    accountHasLoggedIn: Boolean,
    documentCategories: Object,
    categoryDescriptions: Object,
    docusignRecipientStatus: {
      type: Array,
      default: () => [],
    },
    canUploadDocs: {
      type: Boolean,
      default: true,
    },
    contractReminder: {
      type: Object,
      default: null,
    },
    cardstreamReminder: {
      type: Object,
      default: null,
    },
    wordpressReminder: {
      type: Object,
      default: null,
    },
  },
  data() {
    return {
      confirmingFees: false,
      showScrollTop: false,
      isLoading: false,
      showInvoiceModal: false,
      isLoadingContract: false,
      showAdditionalInfoModal: false,
      showContractReminderModal: false,
      showSubmitCardStreamModal: false,
      showCardStreamCredentialsModal: false,
      showWordPressRequestModal: false,
      showWordPressEnterModal: false,
      showCredentialsModal: false,
      showAccountMessageModal: false,
      showDocumentUploadModal: false,
      preselectedCategory: null,
      hasRefreshed: typeof window !== 'undefined' && sessionStorage.getItem('statusPageRefreshed') === 'true',  // CHANGED THIS LINE
      isManualTransitioning: false,
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

      baseSections.push({ id: 'section-contracts', label: 'Contracts' })

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

    processSteps() {      
      const allSteps = [
        { id: 'created', label: 'Application Created', description: 'Initial application setup' },
        { id: 'contract_sent', label: 'Contract Sent To Merchant', description: 'Contract sent to merchant for signature' },
        { id: 'documents_uploaded', label: 'Documents Uploaded', description: 'All required documents uploaded' },
        { id: 'documents_approved', label: 'Documents Approved', description: 'Documents reviewed and approved' },
        { id: 'contract_signed', label: 'Contract Signed', description: 'All parties have signed the contract' },
        { id: 'contract_submitted', label: 'Contract Submitted', description: 'Contract submitted to gateway' },
        { id: 'application_approved', label: 'Application Approved', description: 'Application approved by admin' },
        { id: 'invoice_sent', label: 'Invoice Sent', description: 'Scaling fee invoice sent' },
        { id: 'invoice_paid', label: 'Payment Received', description: 'Scaling fee paid' },
        { id: 'gateway_integrated', label: 'Gateway Integration', description: 'Payment gateway integrated' },
        { id: 'account_live', label: 'Account Live', description: 'Merchant account is live' },
      ]

      const timestamps = this.application.status?.timestamps || {}
      
      const completed = []
      const pending = []
      
      allSteps.forEach((step, defaultOrder) => {
        let timestamp = null
        
        if (step.id === 'created') {
          timestamp = timestamps.created || this.application.created_at
        } else if (step.id === 'contract_signed') {
          timestamp = timestamps.contract_signed || timestamps.contract_completed
        } else {
          timestamp = timestamps[step.id]
        }
        
        if (timestamp) {
          completed.push({
            ...step,
            timestamp: timestamp,
            sortTime: new Date(timestamp).getTime(),
            defaultOrder: defaultOrder
          })
        } else {
          pending.push(step)
        }
      })
            
      // SORT WITH DETAILED LOGGING
      completed.sort((a, b) => {
        if (a.id === 'created') {
          return -1
        }
        if (b.id === 'created') {
          return 1
        }
        
        const timeDiff = a.sortTime - b.sortTime
        if (timeDiff !== 0) {
          return timeDiff
        }
        
        const orderDiff = a.defaultOrder - b.defaultOrder
        return orderDiff
      })
            
      return [...completed, ...pending]
    },

    canSendContractReminder() {
      // Can only send contract if it hasn't been sent yet
      const timestamps = this.application.status?.timestamps;
      return !this.is_account && !timestamps?.application_approved
    },

    canSendContract() {
      // Can only send contract if it hasn't been sent yet
      const timestamps = this.application.status?.timestamps;
      return !this.is_account && !timestamps?.contract_signed
    },

    accountMessageReminder() {
      return this.application.scheduled_emails?.find(
        email => email.email_type === 'account_message_to_user' && email.is_active
      )
    },

    hasExtraDocuments() {
      return this.application.extra_document_categories && 
            Object.keys(this.application.extra_document_categories).length > 0
    },

    canAccountSignContract() {
      if (!this.is_account) return false;
      
      // Use the backend-provided flag (already checks routing order AND contract_signed)
      return this.application.can_merchant_sign === true
    },
    
    canSubmitToCardStream() {
      if (this.is_account) return false;
      
      const timestamps = this.application.status?.timestamps;
      
      return (
        !!timestamps?.contract_sent &&
        !!timestamps?.contract_signed &&
        !timestamps?.application_approved
      );
    },
    
    hasDocuSignRecipientStatus() {
      return this.docusignRecipientStatus && this.docusignRecipientStatus.length > 0
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

    canApprove() {
      return !this.is_account
    },

    canCreateInvoice() {
      return this.application.status?.current_step === 'application_approved' && !this.is_account
    },

    canApproveDocuments() {
      if (this.is_account) return false;
      
      const timestamps = this.application.status?.timestamps;
      
      return (
        !!timestamps?.documents_uploaded && 
        !timestamps?.documents_approved
      );
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
    canSendInvoiceReminder() {
      if (this.is_account) return false
      const timestamps = this.application.status?.timestamps
      // Show if application approved OR invoice sent, BUT NOT if invoice paid
      return (
        (!!timestamps?.application_approved || !!timestamps?.invoice_sent) && 
        !timestamps?.invoice_paid
      )
    },

    canMarkInvoiceAsPaid() {
      if (this.is_account) return false
      const timestamps = this.application.status?.timestamps
      // Show if invoice sent but not yet paid
      return !!timestamps?.invoice_sent && !timestamps?.invoice_paid
    },

    canSendCardStreamCredentials() {
      if (this.is_account) return false
      const timestamps = this.application.status?.timestamps
      // Show if invoice paid but CardStream credentials not yet entered
      return !!timestamps?.invoice_paid && !this.application.cardstream_credentials_entered_at
    },

    cardstreamReminder() {
      return this.application.scheduled_emails?.find(
        email => email.email_type === 'cardstream_credentials' && email.is_active
      )
    },

    canMarkGatewayIntegrated() {
      if (this.is_account) return false
      const timestamps = this.application.status?.timestamps
      // Show if invoice paid but gateway not yet integrated
      return !!timestamps?.invoice_paid && !timestamps?.gateway_integrated
    },

    canRequestWordPress() {
      // Both users and accounts can access this, but button only shown to users
      if (this.is_account) return false
      const timestamps = this.application.status?.timestamps
      // Show only if invoice is paid AND account is not yet live
      return !!timestamps?.invoice_paid && !timestamps?.account_live
    },

    canEnterWordPress() {
      // Both users and accounts can enter WordPress credentials
      return !this.application.wordpress_credentials_entered_at
    },

    wordpressReminder() {
      return this.application.scheduled_emails?.find(
        email => email.email_type === 'wordpress_credentials_request' && email.is_active
      )
    },

    hasWordPressCredentials() {
      return !!this.application.wordpress_credentials_entered_at
    },

    canMakeAccountLive() {
      if (this.is_account) return false
      const timestamps = this.application.status?.timestamps
      // Show if gateway integrated AND WordPress credentials entered
      return !!timestamps?.gateway_integrated && !timestamps?.account_live
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

    refreshPage() {
      sessionStorage.setItem('statusPageRefreshed', 'true')
      window.location.reload()
    },

    openUploadModal(category = null) {
      this.preselectedCategory = category
      this.showDocumentUploadModal = true
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

    getDocumentLibraryUrl() {
      const accountName = this.application.account_name;
      
      // Only add filter if account_name exists and is not empty
      if (accountName && accountName.trim() !== '' && accountName !== 'Unknown') {
        return `/document-library?application=${encodeURIComponent(accountName)}`;
      }
      
      // Otherwise just go to document library without filter
      return '/document-library';
    },

    async openContractForAccount() {
      this.isLoadingContract = true;
      try {
        const response = await fetch(`/applications/${this.application.id}/send-contract`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
        });
        
        const data = await response.json();

        if (data.success && data.signing_url) {
          window.open(data.signing_url, '_blank', 'width=800,height=600');
        } else {
          alert(data.message || 'Failed to open contract, please refresh and try again.');
        }
      } catch (error) {
        console.error('Error opening contract:', error);
        alert('Failed to open contract, please refresh and try again.');
      } finally {
        this.isLoadingContract = false;
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

    sendInvoiceReminder() {
      if (confirm('Send invoice reminder email to the merchant?')) {
        this.$inertia.post(`/applications/${this.application.id}/send-invoice-reminder`, {}, {
          onSuccess: () => {
            // Success message handled by backend
          },
          onError: () => {
            alert('Failed to send invoice reminder')
          }
        })
      }
    },

    scrollToAccountActions() {
      setTimeout(() => {
        // Find the account actions section
        const actionsSection = document.querySelector('#section-actions')
        if (actionsSection) {
          actionsSection.scrollIntoView({ behavior: 'smooth', block: 'start' })
        }
      }, 500)
    },
    
    scrollToHashOnLoad() {
      const hash = window.location.hash
      if (hash) {
        setTimeout(() => {
          const element = document.querySelector(hash)
          if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'start' })
          }
        }, 500)
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
      
      // Open window IMMEDIATELY
      const popupWindow = window.open('about:blank', '_blank', 'width=800,height=600')
      
      if (popupWindow) {
        popupWindow.document.write(`
          <html>
            <head>
              <title>Loading Contract...</title>
              <style>
                body {
                  font-family: Arial, sans-serif;
                  display: flex;
                  justify-content: center;
                  align-items: center;
                  height: 100vh;
                  margin: 0;
                  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                  color: white;
                }
                .loader {
                  text-align: center;
                }
                .spinner {
                  border: 4px solid rgba(255, 255, 255, 0.3);
                  border-top: 4px solid white;
                  border-radius: 50%;
                  width: 40px;
                  height: 40px;
                  animation: spin 1s linear infinite;
                  margin: 0 auto 20px;
                }
                @keyframes spin {
                  0% { transform: rotate(0deg); }
                  100% { transform: rotate(360deg); }
                }
              </style>
            </head>
            <body>
              <div class="loader">
                <div class="spinner"></div>
                <h2>Loading your contract...</h2>
                <p>Please wait while we prepare your document.</p>
              </div>
            </body>
          </html>
        `)
      }
      
      try {
        // Get CSRF token from meta tag
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        
        if (!csrfToken) {
          throw new Error('CSRF token not found. Please refresh the page.')
        }
        
        const response = await fetch(`/applications/${this.application.id}/send-contract`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json', // Important: tell server we expect JSON
            'X-Requested-With': 'XMLHttpRequest', // Mark as AJAX request
          },
          credentials: 'same-origin', // Include cookies for session
        })
        
        // Check if response is actually JSON
        const contentType = response.headers.get('content-type')
        if (!contentType || !contentType.includes('application/json')) {
          throw new Error('Server returned an invalid response. Please refresh the page and try again.')
        }
        
        const data = await response.json()

        if (data.success && data.signing_url) {
          if (popupWindow && !popupWindow.closed) {
            popupWindow.location.href = data.signing_url
          } else {
            window.open(data.signing_url, '_blank', 'width=800,height=600')
          }
        } else {
          if (popupWindow && !popupWindow.closed) {
            popupWindow.close()
          }
          alert(data.message || 'Failed to send contract')
        }
      } catch (error) {
        console.error('Error sending contract:', error)
        
        if (popupWindow && !popupWindow.closed) {
          popupWindow.close()
        }
        
        // Better error messages
        if (error.message.includes('CSRF')) {
          alert('Your session has expired. Please refresh the page and try again.')
          window.location.reload()
        } else if (error.message.includes('invalid response')) {
          alert('Session expired or server error. Please refresh the page and try again.')
          window.location.reload()
        } else {
          alert('Failed to send contract: ' + error.message)
        }
      } finally {
        this.isLoading = false
      }
    },
    
    confirmManualTransition(targetStep, stepLabel) {
      if (this.application.status?.current_step === targetStep) {
        return // Can't transition to current step
      }

      const currentStepLabel = this.formatStatus(this.application.status?.current_step)
      
      // Get the actual dynamic order from processSteps
      const currentOrder = this.processSteps.map(step => step.id)
      
      const currentIndex = currentOrder.indexOf(this.application.status?.current_step)
      const targetIndex = currentOrder.indexOf(targetStep)
      
      let message
      if (targetIndex < currentIndex) {
        const stepsToUnmark = currentIndex - targetIndex
        message = `Transition backwards from "${currentStepLabel}" to "${stepLabel}"?\n\nThis will move the application back to an earlier step and unmark ${stepsToUnmark} completed step(s).`
      } else {
        message = `Manually jump to "${stepLabel}"?\n\nThis will mark ONLY this step as complete WITHOUT triggering automated actions like emails or submissions.\n\nIntermediate steps will remain incomplete and appear in the timeline after the target step.`
      }
      
      if (confirm(message)) {
        this.manualTransition(targetStep, currentOrder) // Pass the order
      }
    },

    async manualTransition(targetStep, currentOrder) {
      this.isManualTransitioning = true
      
      try {
        await this.$inertia.post(
          `/applications/${this.application.id}/manual-transition`,
          { 
            target_step: targetStep,
            current_order: currentOrder // Send the dynamic order
          },
          {
            preserveScroll: true,
            onSuccess: () => {
              // Success message is shown by backend
            },
            onError: (errors) => {
              console.error('Manual transition error:', errors)
              alert('Failed to transition: ' + (errors.target_step || 'Unknown error'))
            },
          }
        )
      } finally {
        this.isManualTransitioning = false
      }
    },
    
    cancelContractReminder() {
      if (confirm('Cancel scheduled contract reminders?')) {
        this.$inertia.post(`/applications/${this.application.id}/cancel-contract-reminder`)
      }
    },
    
    formatStatus(status) {
      if (!status) return 'Pending'
      return status.charAt(0).toUpperCase() + status.slice(1)
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
        this.$inertia.post(`/applications/${this.application.id}/mark-invoice-paid`)
      }
    },

    sendMessageToUser() {
      this.showAccountMessageModal = true
    },
    
    cancelAccountMessageReminder() {
      if (confirm('Cancel scheduled message reminders?')) {
        this.$inertia.post(`/applications/${this.application.id}/cancel-account-message-reminder`)
      }
    },
    
    isStepCompleted(stepId) {
      const timestamps = this.application.status?.timestamps
      
      // Special case: 'created' is always complete
      if (stepId === 'created') {
        return true
      }
      
      // For contract_signed: Check both old and new field names
      if (stepId === 'contract_signed') {
        return !!(timestamps?.contract_signed || timestamps?.contract_completed)
      }
      
      // For all other steps: Check timestamp
      return !!timestamps?.[stepId]
    },
    
    getStepTimestamp(stepId) {
      if (stepId === 'created') {
        return this.application.created_at
      }
      
      const timestamps = this.application.status?.timestamps
      
      if (stepId === 'contract_signed') {
        return timestamps?.contract_signed || timestamps?.contract_completed || null
      }
      
      return timestamps?.[stepId] || null
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

    /**
     * Request WordPress Credentials
     */
    requestWordPressCredentials() {
      this.showWordPressRequestModal = true
    },

    /**
     * Cancel WordPress Reminder
     */
    cancelWordPressReminder() {
      if (confirm('Cancel scheduled WordPress credentials reminders?')) {
        this.$inertia.post(`/applications/${this.application.id}/cancel-wordpress-reminder`)
      }
    },

    /**
     * Send CardStream Credentials
     */
    sendCardStreamCredentials() {
      this.showCardStreamCredentialsModal = true
    },

    /**
     * Cancel CardStream Reminder
     */
    cancelCardStreamReminder() {
      if (confirm('Cancel scheduled CardStream credentials reminders?')) {
        this.$inertia.post(`/applications/${this.application.id}/cancel-cardstream-reminder`)
      }
    },

    /**
     * Mark Gateway as Integrated
     */
    markGatewayIntegrated() {
      if (confirm('Mark the payment gateway as integrated?')) {
        this.$inertia.post(`/applications/${this.application.id}/mark-gateway-integrated`)
      }
    },

    /**
     * Make Account Live
     */
    makeAccountLive() {
      if (confirm('🎉 Make this merchant account live? This will complete the onboarding process.')) {
        this.$inertia.post(`/applications/${this.application.id}/make-account-live`)
      }
    },

    /**
     * Mark Invoice as Paid
     */
    markInvoiceAsPaid() {
      if (confirm('Mark the invoice as paid?')) {
        this.$inertia.post(`/applications/${this.application.id}/mark-invoice-paid`)
      }
    },
  },
  mounted() {
    // Clear refresh flag on fresh login
    if (this.justLoggedIn) {
      sessionStorage.removeItem('statusPageRefreshed')
      this.hasRefreshed = false
      
      // Clear the session flag so it doesn't affect subsequent visits
      this.$inertia.post('/clear-login-flag', {}, { 
          preserveState: true,
          preserveScroll: true 
      })
    }
    this.$nextTick(() => {

      const mainContent = document.querySelector('[scroll-region]')

      if (mainContent) {
        mainContent.addEventListener('scroll', this.handleScroll, { passive: true })
        this.scrollContainer = mainContent
      } else {
        window.addEventListener('scroll', this.handleScroll, { passive: true })
      }

      setTimeout(() => this.handleScroll(), 100)

      if (this.is_account) {
        this.scrollToAccountActions()
      } else {
        // Otherwise handle hash scroll
        this.scrollToHashOnLoad()
      }
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