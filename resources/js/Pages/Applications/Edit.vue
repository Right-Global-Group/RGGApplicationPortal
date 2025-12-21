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

        <div v-if="showAccountActions" class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
          <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <h2 class="text-magenta-400 font-bold text-lg">Account Actions</h2>
          </div>
          <div class="p-6 flex flex-wrap gap-3">
            <!-- Upload Documents (shows until documents_approved) -->
            <button
              v-if="canUploadDocs"
              @click="scrollToDocuments"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg"
            >
              Upload Documents
            </button>

            <!-- Sign Contract (shows when contract_sent and not yet signed) -->
            <Link
              v-if="canSignContract"
              :href="`/applications/${application.id}/status#section-actions`"
              class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg"
            >
              Sign Contract
            </Link>
          </div>
        </div>

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
                <Link 
                  v-if="application.user_id && !$page.props.auth.account" 
                  :href="`/users/${application.user_id}/edit`" 
                  class="inline-block px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-magenta-400 hover:text-magenta-300 hover:border-magenta-500/50 transition-colors"
                >
                  {{ application.user_name || '—' }}
                </Link>
                <div 
                  v-else 
                  class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300"
                >
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
                <label class="block text-gray-300 font-medium mb-2">Monthly Minimum</label>
                <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  £{{ parseFloat(application.monthly_minimum).toFixed(2) }}
                </div>
              </div>
              <div>
                <label class="block text-gray-300 font-medium mb-2">Scaling Fee</label>
                <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  £{{ parseFloat(application.scaling_fee).toFixed(2)}}
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
                <label class="block text-gray-300 font-medium mb-2">Setup Fee</label>
                <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  £{{ parseFloat(application.setup_fee).toFixed(2) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Documents Section -->
        <div v-if="shouldShowDocuments" class="documents-section bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
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

            <!-- Explanation for Merchant Directors -->
            <div class="mb-6 p-4 bg-blue-900/20 border border-blue-700/30 rounded-lg">
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

            <!-- Base Required Documents -->
            <div 
              v-for="(label, category) in documentCategories" 
              :key="category"
              class="mb-6 last:mb-0"
            >
              <div class="flex items-center justify-between mb-2">
                <h3 class="text-lg font-semibold text-gray-300">{{ label }}</h3>
              </div>
              
              <p class="text-sm text-gray-400 mb-3">{{ categoryDescriptions[category] }}</p>
              
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
                      Delete File
                    </button>
                  </div>
                </div>
              </div>
              <div v-else class="bg-yellow-900/20 border border-yellow-700/30 rounded-lg p-3">
                <p class="text-yellow-300 text-sm">No documents uploaded yet</p>
              </div>
            </div>

            <!-- Additional Documents (Both Pending and Uploaded) -->
            <div v-if="allAdditionalInfoRequests.length > 0" class="mt-6 pt-6 border-t border-primary-800/30">
              <h3 class="text-lg font-semibold text-gray-300 mb-4">Additional Requested Documents</h3>
              
              <!-- Pending Additional Documents -->
              <div v-if="pendingAdditionalDocuments.length > 0" class="mb-6">
                <p class="text-sm font-semibold text-yellow-300 mb-3">⏳ Pending Upload:</p>
                <div 
                  v-for="additionalDoc in pendingAdditionalDocuments" 
                  :key="additionalDoc.id"
                  class="mb-4 last:mb-0"
                >
                  <div class="bg-yellow-900/20 border border-yellow-700/30 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                      <h4 class="text-md font-semibold text-yellow-200">{{ additionalDoc.document_name }}</h4>
                      <div class="flex items-center gap-2">
                        <span class="text-xs text-yellow-400 bg-yellow-900/20 px-2 py-1 rounded border border-yellow-700/30">
                          Pending
                        </span>
                        <!-- Delete requirement button (only for users) -->
                        <button
                          v-if="canChangeFees"
                          @click="removeDocumentRequirement(additionalDoc.id)"
                          class="px-3 py-1 bg-red-600 hover:bg-red-700 text-white text-xs rounded-lg transition-colors"
                        >
                          Remove Requirement
                        </button>
                      </div>
                    </div>
                    
                    <!-- Request Message -->
                    <div v-if="additionalDoc.notes" class="mb-3 p-2 bg-red-900/20 border border-red-700/30 rounded">
                      <p class="text-xs font-semibold text-red-300 mb-1">Request Message:</p>
                      <p class="text-sm text-gray-300 whitespace-pre-wrap">{{ additionalDoc.notes }}</p>
                    </div>
                    
                    <!-- Document Instructions -->
                    <div v-if="additionalDoc.instructions && additionalDoc.document_name !== 'General Additional Information'" class="mb-3">
                      <p class="text-xs font-semibold text-yellow-300 mb-1">Document Instructions:</p>
                      <p class="text-sm text-gray-400 whitespace-pre-wrap">{{ additionalDoc.instructions }}</p>
                    </div>
                    
                    <p class="text-xs text-gray-500 mb-3">
                      Requested by {{ additionalDoc.requested_by }} on {{ additionalDoc.requested_at }}
                    </p>

                    <!-- Uploaded Files -->
                    <div v-if="getDocumentsByCategory(`additional_requested_${additionalDoc.id}`).length > 0" class="space-y-2">
                      <p class="text-xs font-semibold text-yellow-300">Uploaded Files:</p>
                      <div 
                        v-for="doc in getDocumentsByCategory(`additional_requested_${additionalDoc.id}`)"
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
                            Delete File
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Uploaded Additional Documents -->
              <div v-if="uploadedAdditionalDocuments.length > 0">
                <p class="text-sm font-semibold text-green-300 mb-3">✓ Completed:</p>
                <div 
                  v-for="additionalDoc in uploadedAdditionalDocuments" 
                  :key="additionalDoc.id"
                  class="mb-4 last:mb-0"
                >
                  <div class="bg-green-900/20 border border-green-700/30 rounded-lg p-4">
                    <div class="flex items-center justify-between mb-3">
                      <h4 class="text-md font-semibold text-green-200">{{ additionalDoc.document_name }}</h4>
                      <span class="text-xs text-green-400 bg-green-900/20 px-2 py-1 rounded border border-green-700/30">
                        ✓ Completed {{ additionalDoc.uploaded_at }}
                      </span>
                    </div>
                    
                    <!-- Request Message -->
                    <div v-if="additionalDoc.notes" class="mb-3 p-2 bg-green-900/20 border border-green-700/30 rounded">
                      <p class="text-xs font-semibold text-green-300 mb-1">Request Message:</p>
                      <p class="text-sm text-gray-300 whitespace-pre-wrap">{{ additionalDoc.notes }}</p>
                    </div>
                    
                    <!-- Document Instructions -->
                    <div v-if="additionalDoc.instructions && additionalDoc.document_name !== 'General Additional Information'" class="mb-3">
                      <p class="text-xs font-semibold text-green-300 mb-1">Document Instructions:</p>
                      <p class="text-sm text-gray-400 whitespace-pre-wrap">{{ additionalDoc.instructions }}</p>
                    </div>
                    
                    <p class="text-xs text-gray-500 mb-3">
                      Requested by {{ additionalDoc.requested_by }} on {{ additionalDoc.requested_at }}
                    </p>

                    <!-- Uploaded Files -->
                    <div v-if="getDocumentsByCategory(`additional_requested_${additionalDoc.id}`).length > 0" class="space-y-2">
                      <p class="text-xs font-semibold text-green-300">Uploaded Files:</p>
                      <div 
                        v-for="doc in getDocumentsByCategory(`additional_requested_${additionalDoc.id}`)"
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
                            Delete File
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
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
        <!-- Sticky container with dynamic height -->
        <div class="sticky top-6 overflow-y-auto custom-scrollbar" style="max-height: calc(100vh - 3rem);">
          <div class="space-y-6">
            <!-- Application Status -->
            <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
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

            <!-- WordPress Credentials Section -->
            <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
              <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30 flex items-center justify-between">
                <h2 class="text-magenta-400 font-bold text-lg">WordPress Credentials</h2>
                <button 
                  v-if="canEnterWordPress"
                  @click="showWordPressModal = true" 
                  class="px-4 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors text-sm font-medium"
                >
                  {{ application.wordpress_credentials_entered_at ? 'Update' : 'Enter' }} Credentials
                </button>
              </div>
              <div class="p-8">
                <div v-if="application.wordpress_credentials_entered_at" class="space-y-4">
                  <div class="bg-green-900/20 border border-green-700/30 rounded-lg p-4 mb-4">
                    <div class="flex items-center gap-2 text-green-300">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                      </svg>
                      <span class="font-semibold">Credentials Entered</span>
                      <span class="text-sm text-green-400">{{ formatDate(application.wordpress_credentials_entered_at) }}</span>
                    </div>
                  </div>

                  <div>
                    <label class="block text-gray-300 font-medium mb-2">WordPress Site URL</label>
                    <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 flex items-center justify-between">
                      <span class="break-all">{{ application.wordpress_url }}</span>
                      <a 
                        :href="application.wordpress_url" 
                        target="_blank"
                        class="text-blue-400 hover:text-blue-300 text-sm whitespace-nowrap ml-2"
                      >
                        Visit Site →
                      </a>
                    </div>
                  </div>

                  <div>
                    <label class="block text-gray-300 font-medium mb-2">WordPress Admin Username</label>
                    <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                      {{ application.wordpress_username }}
                    </div>
                  </div>

                  <div>
                    <label class="block text-gray-300 font-medium mb-2">WordPress Admin Password</label>
                    <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 font-mono">
                      {{ showWordPressPassword ? application.wordpress_password : '••••••••••••' }}
                      <button
                        @click="showWordPressPassword = !showWordPressPassword"
                        class="ml-3 text-blue-400 hover:text-blue-300 text-sm"
                      >
                        {{ showWordPressPassword ? 'Hide' : 'Show' }}
                      </button>
                    </div>
                  </div>
                </div>
                <div v-else class="bg-yellow-900/20 border border-yellow-700/30 rounded-lg p-4">
                  <p class="text-yellow-300 text-sm">No WordPress credentials entered yet.</p>
                </div>
              </div>
            </div>

            <!-- CardStream Credentials Section -->
            <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
              <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30 flex items-center justify-between">
                <h2 class="text-magenta-400 font-bold text-lg">CardStream Credentials</h2>
                <button 
                  v-if="canEditCardstream"
                  @click="showCardStreamModal = true" 
                  class="px-4 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors text-sm font-medium"
                >
                  {{ application.cardstream_credentials_entered_at ? 'Update' : 'Send' }} Credentials
                </button>
              </div>
              <div class="p-8">
                <div v-if="application.cardstream_credentials_entered_at" class="space-y-4">
                  <div class="bg-green-900/20 border border-green-700/30 rounded-lg p-4 mb-4">
                    <div class="flex items-center gap-2 text-green-300">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                      </svg>
                      <span class="font-semibold">Credentials Sent</span>
                      <span class="text-sm text-green-400">{{ formatDate(application.cardstream_credentials_entered_at) }}</span>
                    </div>
                  </div>

                  <div>
                    <label class="block text-gray-300 font-medium mb-2">CardStream Username</label>
                    <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                      {{ application.cardstream_username }}
                    </div>
                  </div>

                  <div>
                    <label class="block text-gray-300 font-medium mb-2">CardStream Password</label>
                    <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 font-mono">
                      {{ showCardStreamPassword ? application.cardstream_password : '••••••••••••' }}
                      <button
                        @click="showCardStreamPassword = !showCardStreamPassword"
                        class="ml-3 text-blue-400 hover:text-blue-300 text-sm"
                      >
                        {{ showCardStreamPassword ? 'Hide' : 'Show' }}
                      </button>
                    </div>
                  </div>

                  <div>
                    <label class="block text-gray-300 font-medium mb-2">CardStream Merchant ID</label>
                    <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                      {{ application.cardstream_merchant_id }}
                    </div>
                  </div>
                </div>
                <div v-else class="bg-yellow-900/20 border border-yellow-700/30 rounded-lg p-4">
                  <p class="text-yellow-300 text-sm">No CardStream credentials entered yet.</p>
                </div>
              </div>
            </div>
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
    <word-press-credentials-modal
      v-if="canEditWordPress"
      :show="showWordPressModal"
      :application-id="application.id"
      :existing-credentials="{
        wordpress_url: application.wordpress_url,
        wordpress_username: application.wordpress_username,
        wordpress_password: application.wordpress_password,
      }"
      @close="showWordPressModal = false"
    />

    <card-stream-credentials-modal
      v-if="canEditCardstream"
      :show="showCardStreamModal"
      :application-id="application.id"
      :existing-credentials="{
        cardstream_username: application.cardstream_username,
        cardstream_password: application.cardstream_password,
        cardstream_merchant_id: application.cardstream_merchant_id,
      }"
      @close="showCardStreamModal = false"
    />
  </div>
</template>

<style scoped>
/* Custom scrollbar for the sticky sidebar */
.custom-scrollbar {
  scrollbar-width: thin;
  scrollbar-color: rgba(139, 92, 246, 0.3) transparent;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: rgba(139, 92, 246, 0.3);
  border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background-color: rgba(139, 92, 246, 0.5);
}
</style>

<script>
  import { Head, Link } from '@inertiajs/vue3'
  import Layout from '@/Shared/Layout.vue'
  import TextInput from '@/Shared/TextInput.vue'
  import SelectInput from '@/Shared/SelectInput.vue'
  import LoadingButton from '@/Shared/LoadingButton.vue'
  import ChangeFeesModal from '@/Shared/ChangeFeesModal.vue'
  import DocumentUploadModal from '@/Shared/DocumentUploadModal.vue'
  import WordPressCredentialsModal from '@/Shared/WordPressCredentialsModal.vue'
  import CardStreamCredentialsModal from '@/Shared/CardStreamCredentialsModal.vue'
  
  export default {
    components: { 
      Head, 
      Link, 
      LoadingButton, 
      SelectInput, 
      TextInput, 
      ChangeFeesModal, 
      DocumentUploadModal, 
      WordPressCredentialsModal, 
      CardStreamCredentialsModal 
    },
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
      canEditWordPress: { type: Boolean, default: true },
      canEditCardstream: { type: Boolean, default: true },
    },
    data() {
      return {
        showChangeFeesModal: false,
        showDocumentUploadModal: false,
        showWordPressModal: false,
        showWordPressPassword: false,
        showCardStreamPassword: false,
        showCardStreamModal: false,
        form: this.$inertia.form({
          account_id: this.application.account_id,
          name: this.application.name,
        }),
      }
    },
    computed: {
      shouldShowDocuments() {
        return true
      },
  
      showAccountActions() {
        return this.canUploadDocs || this.canSignContract
      },
      
      canUploadDocs() {
        return true
      },
      
      canSignContract() {
        // ✅ Use the backend-provided flag (already checks routing order AND contract_signed)
        return this.application.can_merchant_sign === true
      },
      
      canEnterWordPress() {
        return true
      },
      
      nextStep() {
        const timestamps = this.application.status?.timestamps
        
        // Build array of pending actions
        const pendingActions = []
        
        // Check if documents need approval
        if (!timestamps?.documents_approved) {
          if (!timestamps?.documents_uploaded) {
            pendingActions.push('Upload documents')
          } else {
            pendingActions.push('Waiting for document approval')
          }
        }
        
        // Check if contract needs to be sent/signed
        if (!timestamps?.contract_signed) {
          if (!timestamps?.contract_sent) {
            if (timestamps?.documents_approved) {
              pendingActions.push('Waiting for contract to be sent')
            }
          } else {
            pendingActions.push('Waiting for all parties to sign contract')
          }
        }
        
        // If we have pending actions, show them
        if (pendingActions.length > 0) {
          return pendingActions.join(' & ')
        }
        
        // All initial steps complete, check submission & beyond
        if (timestamps?.contract_signed && !timestamps?.contract_submitted) {
          return 'Contract signed by a recipient'
        }
        
        if (timestamps?.contract_submitted && !timestamps?.application_approved) {
          return 'Under review by CardStream'
        }
        
        if (timestamps?.application_approved && !timestamps?.invoice_sent) {
          return 'Approved - Ready to create invoice'
        }
        
        if (timestamps?.invoice_sent && !timestamps?.invoice_paid) {
          return 'Waiting for invoice payment'
        }
        
        if (timestamps?.invoice_paid && !timestamps?.gateway_integrated) {
          return 'Payment received - Ready for gateway integration'
        }
        
        if (timestamps?.gateway_integrated && !timestamps?.account_live) {
          return 'Gateway integrated - Final setup'
        }
        
        if (timestamps?.account_live) {
          return 'Account is Live ✓'
        }
        
        return 'Processing...'
      },
  
      pendingAdditionalDocuments() {
        return this.application.additional_documents?.filter(doc => !doc.is_uploaded) || []
      },
  
      allAdditionalInfoRequests() {
        return this.application.additional_documents || []
      },
      
      uploadedAdditionalDocuments() {
        return this.application.additional_documents?.filter(doc => doc.is_uploaded) || []
      },
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
      scrollToDocuments() {
        this.$nextTick(() => {
          const documentsSection = document.querySelector('.documents-section')
          if (documentsSection) {
            documentsSection.scrollIntoView({ behavior: 'smooth', block: 'start' })
          }
        })
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
      isAdditionalDocumentCategory(category) {
        return category.startsWith('additional_requested_')
      },
      
      getCategoryDescriptionWithAdditional(category) {
        if (this.isAdditionalDocumentCategory(category)) {
          const docId = category.replace('additional_requested_', '')
          const doc = this.pendingAdditionalDocuments.find(d => d.id === parseInt(docId))
          return doc?.instructions || 'Additional document requested by administrator'
        }
        return this.categoryDescriptions[category] || ''
      },
      
      removeDocumentRequirement(docId) {
        const doc = this.pendingAdditionalDocuments.find(d => d.id === parseInt(docId))
        
        if (confirm(`Remove the requirement for "${doc?.document_name}"? Any uploaded files will be deleted.`)) {
          this.$inertia.delete(`/applications/${this.application.id}/additional-documents/${docId}/requirement`)
        }
      },
    },
    mounted() {
      if (window.location.hash === '#documents') {
        this.$nextTick(() => {
          setTimeout(() => {
            this.scrollToDocuments()
          }, 100)
        })
      }
    },
  }
  </script>