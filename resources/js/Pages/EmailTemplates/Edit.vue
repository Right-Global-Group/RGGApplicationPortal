<template>
  <div>
    <Head :title="`Edit ${template.display_name}`" />
    
    <!-- Header with back + action buttons -->
    <div class="flex items-center justify-between mb-6">
      <div class="flex items-center gap-4">
        <Link
          href="/email-templates"
          class="p-2 bg-dark-800 hover:bg-dark-700 rounded-lg transition-colors"
        >
          <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </Link>
        <div>
          <h1 class="text-3xl font-bold text-white">{{ template.display_name }}</h1>
          <p class="text-sm text-gray-400 mt-1">{{ template.name }}.blade.php</p>
        </div>
      </div>

      <div class="flex gap-3">
        <!-- Preview button -->
        <button
          @click="showPreview = true"
          class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors flex items-center gap-2"
        >
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
          </svg>
          Preview
        </button>

        <!-- Save button -->
        <button
          @click="saveTemplate"
          :disabled="saving || !hasChanges"
          class="px-6 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
        >
          <svg v-if="saving" class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
          </svg>
          <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/>
          </svg>
          {{ saving ? 'Saving...' : (hasChanges ? 'Save Changes' : 'No Changes') }}
        </button>
      </div>
    </div>

    <!-- Flash Messages -->
    <div v-if="successMessage" class="bg-green-900/20 border border-green-700/30 rounded-xl p-4 mb-6">
      <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-green-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16..."/>
        </svg>
        <div class="text-green-300 font-semibold flex-1">{{ successMessage }}</div>
        <button @click="successMessage = null" class="text-green-400 hover:text-green-300">
          ✕
        </button>
      </div>
    </div>

    <div v-if="errorMessage" class="bg-red-900/20 border border-red-700/30 rounded-xl p-4 mb-6">
      <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0..."/>
        </svg>
        <div class="text-red-300 font-semibold flex-1">{{ errorMessage }}</div>
        <button @click="errorMessage = null" class="text-red-400 hover:text-red-300">
          ✕
        </button>
      </div>
    </div>

    <!-- Full-width Code Editor -->
    <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl overflow-hidden">
      <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-700">
        <h2 class="text-lg font-bold text-white flex items-center gap-2">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
          </svg>
          Template Code
        </h2>
      </div>
      <div class="p-6">
        <textarea
          :value="content"
          @input="handleInput"
          class="w-full h-[600px] bg-dark-900 text-gray-300 font-mono text-sm p-4 rounded-lg border border-primary-700 focus:border-magenta-500 focus:ring-2 focus:ring-magenta-500/20 resize-none"
          spellcheck="false"
        ></textarea>
      </div>
    </div>

    <!-- Info Box -->
    <div class="bg-blue-900/20 border border-blue-700/30 rounded-xl p-4 mt-6">
      <div class="flex items-start gap-3">
        <svg class="w-5 h-5 text-blue-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M18 10a8 8..."/>
        </svg>
        <div class="flex-1">
          <div class="text-blue-300 font-semibold mb-1">Template Editing Tips</div>
          <ul class="text-sm text-gray-400 space-y-1">
            <li>• Use Blade syntax for dynamic content: <code class="bg-dark-900 px-1 rounded">&#123;&#123; $variable &#125;&#125;</code></li>
            <li>• Preview uses sample data for testing</li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Preview Modal -->
    <div v-if="showPreview" class="fixed inset-0 z-50 overflow-y-auto" @click.self="showPreview = false">
      <div class="flex items-center justify-center min-h-screen px-4 py-8">
        <div class="fixed inset-0 bg-black opacity-75" @click="showPreview = false"></div>

        <div class="relative bg-dark-800 rounded-xl shadow-2xl w-full max-w-4xl border border-primary-700">
          <div class="px-6 py-4 bg-gradient-to-r from-blue-900/50 to-primary-900/50 border-b border-primary-700 flex justify-between items-center">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
              Live Preview
            </h2>
            <button @click="showPreview = false" class="text-gray-400 hover:text-white">
              ✕
            </button>
          </div>

          <div class="p-6 bg-white max-h-[70vh] overflow-y-auto">
            <div v-if="loadingPreview" class="flex items-center justify-center py-20">
              <div class="text-center">
                <svg class="animate-spin h-12 w-12 text-magenta-500 mx-auto mb-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8..."/>
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
            <p class="text-sm text-gray-400">Preview uses sample data</p>
            <button
              @click="loadPreview"
              :disabled="loadingPreview"
              class="text-sm px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors disabled:opacity-50 flex items-center gap-1"
            >
              <svg class="w-4 h-4" :class="{ 'animate-spin': loadingPreview }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
              </svg>
              {{ loadingPreview ? 'Refreshing...' : 'Refresh' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

  
<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import debounce from 'lodash/debounce'

export default {
  components: {
    Head,
    Link,
  },
  layout: Layout,
  props: {
    template: Object,
    allTemplates: Array,
  },
  data() {
    return {
      content: this.template.content,
      originalContent: this.template.content,
      previewHtml: '',
      loadingPreview: false,
      previewError: null,
      previewKey: 0,
      saving: false,
      resetting: false,
      successMessage: null,
      errorMessage: null,
      showPreview: false,
    }
  },
  computed: {
    hasChanges() {
      return this.content !== this.originalContent
    },
  },
  mounted() {
    this.loadPreview()
    document.addEventListener('keydown', this.handleKeyDown)
  },
  beforeUnmount() {
    document.removeEventListener('keydown', this.handleKeyDown)
  },
  created() {
    this.debouncedLoadPreview = debounce(this.loadPreview, 500)
  },
  methods: {
    handleKeyDown(e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 's') {
        e.preventDefault()
        if (this.hasChanges && !this.saving) {
          this.saveTemplate()
        }
      }
    },
    
    handleInput(e) {
      this.content = e.target.value
      if (this.successMessage) {
        this.successMessage = null
      }
      this.debouncedLoadPreview()
    },
    
    async saveTemplate() {
      if (this.saving || !this.hasChanges) return
      
      this.saving = true
      this.successMessage = null
      this.errorMessage = null

      try {
        const response = await fetch(`/email-templates/${this.template.name}`, {
          method: 'PUT',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          },
          body: JSON.stringify({
            content: this.content,
          }),
        })

        const data = await response.json()

        if (!response.ok) {
          throw new Error(data.error || 'Failed to save template')
        }

        this.originalContent = this.content
        this.successMessage = 'Template saved successfully!'
        
        await this.loadPreview()
        
        setTimeout(() => {
          this.successMessage = null
        }, 5000)
        
      } catch (error) {
        console.error('Save error:', error)
        this.errorMessage = error.message || 'Failed to save template'
      } finally {
        this.saving = false
      }
    },
    
    async loadPreview() {
      this.loadingPreview = true
      this.previewError = null

      try {
        const timestamp = new Date().getTime()
        const response = await fetch(`/email-templates/${this.template.name}/preview?t=${timestamp}`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
          },
          body: JSON.stringify({
            content: this.content, // <-- send current unsaved textarea content
          }),
        })

        const data = await response.json()

        if (data.error) {
          this.previewError = data.error
          this.previewHtml = data.html || ''
        } else {
          this.previewHtml = data.html
          this.previewKey++
        }
      } catch (error) {
        console.error('Failed to load preview:', error)
        this.previewError = 'Failed to load preview: ' + error.message
      } finally {
        this.loadingPreview = false
      }
    },
  },
}
</script>