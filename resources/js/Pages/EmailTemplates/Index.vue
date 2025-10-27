<template>
  <div>
    <Head title="Email Templates" />
    
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold text-white">Email Templates</h1>
      <div class="text-sm text-gray-400">
        {{ templates.length }} template{{ templates.length !== 1 ? 's' : '' }}
      </div>
    </div>

    <!-- Success Message -->
    <div v-if="$page.props.flash.success" class="bg-green-900/20 border border-green-700/30 rounded-xl p-4 mb-6">
      <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-green-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
        </svg>
        <div class="flex-1">
          <div class="text-green-300 font-semibold">{{ $page.props.flash.success }}</div>
        </div>
      </div>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-900/20 border border-blue-700/30 rounded-xl p-4 mb-6">
      <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
        </svg>
        <div class="flex-1">
          <div class="text-blue-300 font-semibold mb-1">Email Template Management</div>
          <div class="text-sm text-gray-400">
            Edit and preview email templates used throughout the application. A backup is automatically created before each update.
          </div>
        </div>
      </div>
    </div>

    <!-- Templates Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
      <div
        v-for="template in templates"
        :key="template.name"
        class="group bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl overflow-hidden hover:border-magenta-500/50 transition-all duration-200 hover:shadow-xl hover:shadow-magenta-500/10"
      >
        <div class="p-6">
          <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
              <div class="p-3 bg-gradient-to-br from-magenta-900/50 to-primary-900/50 rounded-lg group-hover:from-magenta-800/50 group-hover:to-primary-800/50 transition-colors">
                <svg class="w-6 h-6 text-magenta-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
              </div>
            </div>
          </div>

          <h3 class="text-lg font-semibold text-white mb-2 group-hover:text-magenta-300 transition-colors">
            {{ template.display_name }}
          </h3>

          <div class="space-y-1 text-sm text-gray-400">
            <div class="flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              <span>{{ template.name }}.blade.php</span>
            </div>
            <div class="flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
              <span>Modified {{ formatDate(template.modified) }}</span>
            </div>
            <div class="flex items-center gap-2">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
              </svg>
              <span>{{ formatBytes(template.size) }}</span>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="px-6 pb-6 pt-2 border-t border-primary-700/30 flex gap-2">
          <Link
            :href="`/email-templates/${template.name}/edit`"
            class="flex-1 text-center text-xs px-3 py-2 bg-primary-900/50 text-primary-300 hover:bg-primary-800/50 hover:text-primary-200 rounded transition-colors"
          >
            <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Edit
          </Link>
          <button
            @click="openPreview(template)"
            class="flex-1 text-center text-xs px-3 py-2 bg-blue-900/50 text-blue-300 hover:bg-blue-800/50 hover:text-blue-200 rounded transition-colors"
          >
            <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
            Preview
          </button>
        </div>
      </div>
    </div>

    <!-- Preview Modal -->
    <div v-if="showPreview" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closePreview">
      <div class="flex items-center justify-center min-h-screen px-4 py-8">
        <div class="fixed inset-0 bg-black opacity-75" @click="closePreview"></div>
        
        <div class="relative bg-dark-800 rounded-xl shadow-2xl w-full max-w-4xl border border-primary-700">
          <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-700 flex justify-between items-center">
            <div>
              <h3 class="text-xl font-bold text-white">{{ previewTemplate?.display_name }}</h3>
              <p class="text-sm text-gray-400 mt-1">{{ previewTemplate?.name }}.blade.php</p>
            </div>
            <button @click="closePreview" class="text-gray-400 hover:text-white transition-colors">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="p-6 max-h-[70vh] overflow-y-auto bg-white">
            <div v-if="loadingPreview" class="flex items-center justify-center py-20">
              <div class="text-center">
                <svg class="animate-spin h-12 w-12 text-magenta-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-gray-600">Loading preview...</p>
              </div>
            </div>
            <div v-else-if="previewError" class="text-red-600 p-4 bg-red-50 rounded">
              <strong>Error:</strong> {{ previewError }}
            </div>
            <iframe
              v-else
              :srcdoc="previewHtml"
              class="w-full h-[600px] border-0"
              sandbox="allow-same-origin"
              :key="previewKey"
            ></iframe>
          </div>

          <div class="px-6 py-4 bg-dark-900/60 border-t border-primary-700 flex justify-between items-center">
            <div class="flex items-center gap-3">
              <p class="text-sm text-gray-400">Preview uses sample data</p>
              <button
                @click="refreshPreview"
                :disabled="loadingPreview"
                class="text-sm px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
              >
                <svg class="w-4 h-4" :class="{ 'animate-spin': loadingPreview }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                {{ loadingPreview ? 'Refreshing...' : 'Refresh' }}
              </button>
            </div>
            <div class="flex gap-3">
              <Link
                :href="`/email-templates/${previewTemplate?.name}/edit`"
                class="px-4 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors"
              >
                Edit Template
              </Link>
              <button
                @click="closePreview"
                class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg transition-colors"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'

export default {
  components: {
    Head,
    Link,
  },
  layout: Layout,
  props: {
    templates: Array,
  },
  data() {
    return {
      showPreview: false,
      previewTemplate: null,
      previewHtml: '',
      loadingPreview: false,
      previewError: null,
      previewKey: 0,
    }
  },
  methods: {
    async openPreview(template) {
      this.showPreview = true
      this.previewTemplate = template
      await this.loadPreview()
    },
    
    async loadPreview() {
      if (!this.previewTemplate) return;
      
      this.loadingPreview = true
      this.previewError = null
      this.previewHtml = ''

      try {
        // Add timestamp to bust cache
        const timestamp = new Date().getTime()
        const response = await fetch(`/email-templates/${this.previewTemplate.name}/preview?t=${timestamp}`)
        const data = await response.json()
        
        if (data.error) {
          this.previewError = data.error
          this.previewHtml = data.html || ''
        } else {
          this.previewHtml = data.html
          // Increment key to force iframe reload
          this.previewKey++
        }
      } catch (error) {
        console.error('Failed to load preview:', error)
        this.previewError = 'Failed to load preview: ' + error.message
      } finally {
        this.loadingPreview = false
      }
    },
    
    async refreshPreview() {
      if (!this.previewTemplate) return
      await this.loadPreview()
    },
    
    closePreview() {
      this.showPreview = false
      this.previewTemplate = null
      this.previewHtml = ''
      this.previewError = null
      this.previewKey = 0
    },
    
    formatDate(dateString) {
      const date = new Date(dateString)
      const now = new Date()
      const diff = now - date
      const days = Math.floor(diff / (1000 * 60 * 60 * 24))
      
      if (days === 0) {
        return 'Today'
      } else if (days === 1) {
        return 'Yesterday'
      } else if (days < 7) {
        return `${days} days ago`
      } else {
        return date.toLocaleDateString()
      }
    },
    
    formatBytes(bytes) {
      if (bytes === 0) return '0 Bytes'
      const k = 1024
      const sizes = ['Bytes', 'KB', 'MB']
      const i = Math.floor(Math.log(bytes) / Math.log(k))
      return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
    },
  },
}
</script>