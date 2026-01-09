<template>
  <div>
    <Head title="Document Library" />
    
    <div class="mb-6">
      <h1 class="text-3xl font-bold">Document Library</h1>
    </div>

    <!-- Filter Input -->
    <div class="mb-6 flex items-center gap-3">
      <div class="flex-1">
        <label class="block text-sm font-medium text-gray-300 mb-2">
          Filter by Application
        </label>
        <div class="relative">
          <input
            v-model="filterQuery"
            type="text"
            placeholder="Search by application or merchant name..."
            class="w-full px-4 py-2 pr-20 bg-dark-900/50 border border-primary-800/30 rounded-lg text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-magenta-500"
            @input="applyFilter"
          />
          <button
            v-if="filterQuery"
            @click="clearFilter"
            class="absolute right-2 top-1/2 -translate-y-1/2 px-3 py-1 text-sm text-gray-400 hover:text-white transition-colors"
          >
            Clear
          </button>
        </div>
      </div>
    </div>

    <!-- Applications List -->
    <div v-if="applications.length > 0" class="space-y-6">
      <div
        v-for="application in applications"
        :key="application.id"
        class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden"
      >
        <!-- Application Header -->
        <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-xl font-bold text-white">{{ application.name }}</h2>
              <div class="flex items-center gap-3 mt-1">
                <span class="text-sm text-gray-400">{{ application.account_name }}</span>
                <span class="text-sm text-gray-500">• Created {{ application.created_at }}</span>
              </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex items-center gap-3">
              <!-- Upload Document Button (Users Only) -->
              <button
                v-if="!is_account"
                @click="openUploadModal(application)"
                class="px-4 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors text-sm font-medium flex items-center gap-2"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                </svg>
                Upload Document
              </button>
              
              <!-- View Application Button -->
              <Link
                :href="`/applications/${application.id}/edit`"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors text-sm font-medium flex items-center gap-2"
              >
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                View Application
              </Link>
            </div>
          </div>
        </div>

        <!-- Documents List -->
        <div class="p-6">
          <div v-if="application.documents.length > 0" class="space-y-3">
            <div
              v-for="doc in application.documents"
              :key="doc.id"
              class="flex items-center justify-between p-4 bg-dark-900/50 border border-primary-800/30 rounded-lg hover:bg-primary-900/20 transition-colors"
            >
              <div class="flex items-center gap-3 flex-1">
                <svg
                  v-if="doc.dumped_at"
                  class="w-5 h-5 text-yellow-400 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <svg
                  v-else
                  class="w-5 h-5 text-green-400 flex-shrink-0"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-3">
                    <span class="font-semibold text-white">{{ doc.category }}</span>
                    <span class="text-sm text-gray-400">{{ doc.filename }}</span>
                  </div>
                  <div class="text-xs text-gray-500 mt-1">
                    Uploaded {{ doc.uploaded_at }}
                  </div>
                  <div v-if="doc.dumped_at" class="text-xs text-yellow-400 mt-1">
                    ⚠️ File removed {{ doc.dumped_at }} - {{ doc.dumped_reason }}
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-2 ml-4">
                <button
                  v-if="!doc.dumped_at"
                  @click="viewDocument(application.id, doc.id)"
                  class="px-3 py-1.5 bg-primary-600 hover:bg-primary-700 text-white rounded text-sm transition-colors flex items-center gap-1"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                  View
                </button>
                <a
                  v-if="!doc.dumped_at"
                  :href="doc.download_url"
                  class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded text-sm transition-colors flex items-center gap-1"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                  </svg>
                  Download
                </a>
                
                <!-- Edit Button (Users Only, Contract & Application Form) -->
                <button
                  v-if="!is_account && !doc.dumped_at && (doc.category === 'contract' || doc.category === 'application_form')"
                  @click="openEditModal(application.id, doc)"
                  class="px-3 py-1.5 bg-yellow-600 hover:bg-yellow-700 text-white rounded text-sm transition-colors flex items-center gap-1"
                  title="Edit PDF fields"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                  Edit
                </button>
              </div>
            </div>
          </div>
          
          <div v-else class="text-center py-8 text-gray-400">
            No documents uploaded yet
          </div>
        </div>
      </div>
    </div>

    <div v-else class="text-center py-12">
      <svg class="w-16 h-16 text-gray-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      <p class="text-gray-400 text-lg">No applications found</p>
    </div>

    <!-- Document Upload Modal -->
    <document-library-upload-modal
      :show="showUploadModal"
      :applications="[selectedApplication]"
      :preselected-application-id="selectedApplication?.id"
      @close="showUploadModal = false"
    />

    <!-- Document Viewer Modal -->
    <document-viewer-modal
      :show="showViewerModal"
      :document="currentDocument"
      @close="showViewerModal = false"
    />

    <!-- PDF Edit Modal -->
    <edit-pdf-modal
      :show="showEditModal"
      :document="documentToEdit"
      :application-id="editApplicationId"
      @close="showEditModal = false"
      @saved="handleEditSaved"
    />
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import DocumentLibraryUploadModal from '@/Shared/DocumentLibraryUploadModal.vue'
import DocumentViewerModal from '@/Shared/DocumentViewerModal.vue'
import EditPdfModal from '@/Shared/EditPdfModal.vue'

defineOptions({ layout: Layout })

const props = defineProps({
  applications: Array,
  allApplications: Array,
  is_account: Boolean,
  filters: Object,
})

const filterQuery = ref(props.filters?.application || '')
const showUploadModal = ref(false)
const selectedApplication = ref(null)
const showViewerModal = ref(false)
const currentDocument = ref(null)
const showEditModal = ref(false)
const documentToEdit = ref(null)
const editApplicationId = ref(null)

const openUploadModal = (application) => {
  selectedApplication.value = application
  showUploadModal.value = true
}

const applyFilter = () => {
  router.get(
    '/document-library',
    { application: filterQuery.value },
    {
      preserveState: true,
      preserveScroll: true,
      replace: true,
    }
  )
}

const clearFilter = () => {
  filterQuery.value = ''
  applyFilter()
}

const viewDocument = async (applicationId, documentId) => {
  try {
    const response = await fetch(`/applications/${applicationId}/documents/${documentId}/view`)
    const data = await response.json()

    if (data.success) {
      currentDocument.value = {
        filename: data.filename,
        mime_type: data.mime_type,
        content: data.content,
      }
      showViewerModal.value = true
    } else {
      alert('Failed to load document')
    }
  } catch (error) {
    console.error('Failed to load document:', error)
    alert('Failed to load document')
  }
}

const openEditModal = (applicationId, document) => {
  editApplicationId.value = applicationId
  documentToEdit.value = document
  showEditModal.value = true
}

const handleEditSaved = () => {
  // Reload the page to show the new edited version
  router.reload()
}
</script>