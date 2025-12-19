<template>
  <div>
    <Head title="Document Library" />
    
    <h1 class="text-3xl font-bold text-white mb-6">Document Library</h1>

    <div v-if="applications.length === 0" class="bg-dark-800/50 rounded-xl p-8 text-center border border-primary-800/30">
      <p class="text-gray-400">No applications found</p>
    </div>

    <div v-else class="space-y-6">
      <div
        v-for="app in applications"
        :key="app.id"
        class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-primary-800/30 overflow-hidden"
      >
        <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
          <div class="flex justify-between items-center">
            <div>
              <h2 class="text-xl font-bold text-white">{{ app.name }} - {{ app.account_name }}</h2>
              <p class="text-sm text-gray-400 mt-1">Created {{ app.created_at }}</p>
            </div>
            <Link 
              :href="`/applications/${app.id}/status`"
              class="text-magenta-400 hover:text-magenta-300 text-sm flex items-center gap-2"
            >
              View Application
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </Link>
          </div>
        </div>

        <div class="p-6">
          <!-- DocuSign Contract -->
          <div v-if="app.has_docusign" class="mb-6">
            <h3 class="text-lg font-semibold text-magenta-400 mb-3 flex items-center gap-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              Contract (DocuSign)
            </h3>
            <div class="bg-dark-900/50 border border-primary-700/30 rounded-lg p-4">
              <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                  <div class="p-2 bg-blue-900/30 rounded">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </div>
                  <div>
                    <div class="text-white font-medium">Signed Contract</div>
                    <div class="text-sm text-gray-400">PDF Document</div>
                  </div>
                </div>
                
                <div class="flex gap-2">
                  <button
                    @click="viewDocuSignContract(app.id)"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors flex items-center gap-2"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    View
                  </button>
                  <a
                    :href="`/applications/${app.id}/docusign/download`"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download
                  </a>
                </div>
              </div>
            </div>
          </div>

          <!-- Uploaded Documents -->
          <div>
            <h3 class="text-lg font-semibold text-magenta-400 mb-3 flex items-center gap-2">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
              </svg>
              Uploaded Documents ({{ app.documents.length }})
            </h3>
            <div v-if="app.documents.length > 0" class="space-y-2">
              <div
                v-for="doc in app.documents"
                :key="doc.id"
                class="flex items-center justify-between bg-dark-900/50 border border-primary-700/30 rounded-lg p-4 hover:border-magenta-500/30 transition-colors"
              >
                <div class="flex items-center gap-3">
                  <div class="p-2 bg-green-900/30 rounded">
                    <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                  </div>
                  <div>
                    <div class="text-white font-medium">{{ doc.filename }}</div>
                    <div class="text-sm text-gray-400">
                      {{ formatCategory(doc.category) }} • Uploaded {{ doc.uploaded_at }}
                    </div>
                  </div>
                </div>
                
                <div class="flex gap-2">
                  <button
                    @click="viewDocument(doc)"
                    class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition-colors flex items-center gap-2"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    View
                  </button>
                  <a
                    :href="doc.download_url"
                    class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors flex items-center gap-2"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download
                  </a>
                </div>
              </div>
            </div>
            <div v-else class="bg-yellow-900/20 border border-yellow-700/30 rounded-lg p-4">
              <p class="text-yellow-300 text-sm">No documents uploaded yet</p>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Document Viewer Modal -->
    <document-viewer-modal
      :show="showDocumentViewer"
      :view-url="currentDocumentViewUrl"
      :download-url="currentDocumentDownloadUrl"
      @close="closeDocumentViewer"
    />
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import DocumentViewerModal from '@/Shared/DocumentViewerModal.vue'

export default {
  components: {
    Head,
    Link,
    DocumentViewerModal,
  },
  layout: Layout,
  props: {
    applications: Array,
    is_account: Boolean,
  },
  data() {
    return {
      showDocumentViewer: false,
      currentDocumentViewUrl: null,
      currentDocumentDownloadUrl: null,
    }
  },
  methods: {
    formatCategory(category) {
      if (!category) return 'Uncategorized'
      return category.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
    },
    viewDocument(doc) {
      this.currentDocumentViewUrl = doc.view_url
      this.currentDocumentDownloadUrl = doc.download_url
      this.showDocumentViewer = true
    },
    viewDocuSignContract(applicationId) {
      this.currentDocumentViewUrl = `/applications/${applicationId}/docusign/view`
      this.currentDocumentDownloadUrl = `/applications/${applicationId}/docusign/download`
      this.showDocumentViewer = true
    },
    closeDocumentViewer() {
      this.showDocumentViewer = false
      this.currentDocumentViewUrl = null
      this.currentDocumentDownloadUrl = null
    },
  },
}
</script>