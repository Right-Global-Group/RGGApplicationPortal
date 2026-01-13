<template>
  <teleport to="body">
    <transition name="modal">
      <div
        v-if="show"
        class="fixed inset-0 z-50 overflow-y-auto"
        @click.self="close"
      >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
          <!-- Background overlay -->
          <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75" @click="close"></div>

          <!-- Modal panel -->
          <div class="inline-block w-full max-w-6xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-dark-800 shadow-2xl rounded-2xl border border-primary-700/50 relative">
            
            <!-- Close button -->
            <button
              @click="close"
              class="absolute top-4 right-4 text-gray-400 hover:text-white transition-colors z-10"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>

            <!-- Header -->
            <div class="mb-4">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-xl font-bold text-white">{{ document?.filename || 'Document' }}</h3>
                  <p class="text-sm text-gray-400 mt-1">{{ formatMimeType(document?.mime_type) }}</p>
                </div>
                
                <!-- Edit button for editable PDFs -->
                <button
                  v-if="canEdit && !editMode"
                  @click="startEditing"
                  class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg transition-colors flex items-center gap-2"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                  Edit Fields
                </button>
                <button
                  v-if="canEdit && editMode"
                  @click="cancelEditing"
                  class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg transition-colors flex items-center gap-2"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                  </svg>
                  Cancel Edit
                </button>
              </div>
            </div>

            <!-- Document viewer -->
            <div v-if="document?.content" class="bg-dark-900/50 rounded-lg p-4 overflow-auto relative" style="max-height: 70vh;">
              
              <!-- PDF Viewer with Inline Editing -->
              <div v-if="isPDF" class="relative">
                <!-- Canvas Container for PDF rendering -->
                <div class="relative bg-white rounded overflow-auto" style="max-height: 60vh;">
                  <canvas 
                    ref="pdfCanvas" 
                    class="w-full cursor-pointer"
                    @click="handleCanvasClick"
                  ></canvas>
                  
                  <!-- Overlay input fields in edit mode -->
                  <div v-if="editMode && editableFields.length > 0" class="absolute inset-0 pointer-events-none">
                    <div
                      v-for="field in editableFields"
                      :key="field.name"
                      class="absolute pointer-events-auto"
                      :style="{
                        left: field.rect.x + 'px',
                        top: field.rect.y + 'px',
                        width: field.rect.width + 'px',
                        height: field.rect.height + 'px'
                      }"
                    >
                      <input
                        v-model="field.value"
                        type="text"
                        class="w-full h-full px-2 text-sm border-2 border-yellow-500 bg-white/95 focus:outline-none focus:ring-2 focus:ring-yellow-600 focus:bg-white"
                        :placeholder="formatFieldName(field.name)"
                        @input="field.modified = true"
                      />
                    </div>
                  </div>
                </div>

                <!-- Loading indicator -->
                <div v-if="loadingPdf" class="absolute inset-0 flex items-center justify-center bg-dark-900/80 rounded">
                  <div class="text-center">
                    <svg class="animate-spin h-12 w-12 text-magenta-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-gray-400">{{ pdfError || 'Loading PDF...' }}</p>
                  </div>
                </div>
              </div>

              <!-- Image Viewer -->
              <img
                v-else-if="isImage"
                :src="`data:${document.mime_type};base64,${document.content}`"
                :alt="document.filename"
                class="max-w-full max-h-[60vh] mx-auto rounded-lg"
              />

              <!-- CSV Viewer -->
              <div v-else-if="isCSV" class="overflow-auto max-h-[60vh]">
                <table class="min-w-full text-left border-collapse text-gray-300 text-sm">
                  <thead>
                    <tr>
                      <th
                        v-for="(h, index) in csvHeaders"
                        :key="index"
                        class="border-b border-gray-700 px-3 py-2 font-semibold bg-dark-700"
                      >
                        {{ h }}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(row, rIndex) in csvRows" :key="rIndex">
                      <td
                        v-for="(col, cIndex) in row"
                        :key="cIndex"
                        class="border-b border-gray-800 px-3 py-2"
                      >
                        {{ col }}
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Unsupported -->
              <div v-else class="p-8 text-center">
                <p class="text-gray-400 mb-4">This file cannot be previewed in the browser.</p>
                <p class="text-sm text-gray-500">{{ document.mime_type }}</p>
              </div>

            </div>

            <!-- Footer -->
            <div class="mt-6 flex justify-between items-center">
              <div v-if="editMode" class="text-sm text-gray-400">
                💡 Tip: Click on highlighted fields in the document to edit them directly.
              </div>
              <div v-else></div>
              
              <div class="flex gap-3">
                <button
                  v-if="editMode"
                  @click="saveEdits"
                  :disabled="saving || !hasModifications"
                  class="px-6 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                  <svg v-if="saving" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span>{{ saving ? 'Saving...' : 'Save Changes' }}</span>
                </button>
                <button
                  v-if="!editMode"
                  @click="close"
                  class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg"
                >
                  Close
                </button>
              </div>
            </div>

          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script>
import { router } from '@inertiajs/vue3'

export default {
  emits: ["close"],
  props: {
    show: Boolean,
    document: {
      type: Object,
      default: null,
    },
    applicationId: Number,
    documentCategory: String,
    isAccount: Boolean,
  },
  data() {
    return {
      csvRows: [],
      csvHeaders: [],
      editMode: false,
      loadingPdf: false,
      saving: false,
      editableFields: [],
      pdfError: null,
      pdfDoc: null,
      currentPage: 1,
      scale: 1.5,
    };
  },
  computed: {
    isPDF() {
      return this.document?.mime_type === "application/pdf";
    },
    isImage() {
      return this.document?.mime_type?.startsWith("image/");
    },
    isCSV() {
      return (
        this.document?.mime_type === "text/csv" ||
        this.document?.filename?.toLowerCase().endsWith(".csv")
      );
    },
    canEdit() {
      return (
        !this.isAccount &&
        this.isPDF &&
        (this.documentCategory === 'contract' || this.documentCategory === 'application_form')
      );
    },
    hasModifications() {
      return this.editableFields.some(field => field.modified);
    },
  },
  watch: {
    show(value) {
      if (value && this.document) {
        this.parseIfNeeded();
        if (this.isPDF) {
          this.$nextTick(() => {
            this.loadPDF();
          });
        }
      } else if (!value) {
        this.reset();
      }
    },
  },
  methods: {
    async loadPDF() {
      if (!this.document?.content) return;
      
      this.loadingPdf = true;
      
      try {
        // Load PDF.js
        if (!window.pdfjsLib) {
          const script = document.createElement('script');
          script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js';
          document.head.appendChild(script);
          await new Promise((resolve) => {
            script.onload = resolve;
          });
          window.pdfjsLib.GlobalWorkerOptions.workerSrc = 
            'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        }
        
        // Convert base64 to ArrayBuffer
        const binaryString = atob(this.document.content);
        const bytes = new Uint8Array(binaryString.length);
        for (let i = 0; i < binaryString.length; i++) {
          bytes[i] = binaryString.charCodeAt(i);
        }
        
        // Load PDF document
        const loadingTask = window.pdfjsLib.getDocument({ data: bytes });
        this.pdfDoc = await loadingTask.promise;
        
        // Render first page
        await this.renderPage(this.currentPage);
        
      } catch (error) {
        console.error('Failed to load PDF:', error);
        this.pdfError = 'Failed to load PDF';
      } finally {
        this.loadingPdf = false;
      }
    },
    
    async renderPage(pageNum) {
      if (!this.pdfDoc) return;
      
      try {
        const page = await this.pdfDoc.getPage(pageNum);
        const canvas = this.$refs.pdfCanvas;
        if (!canvas) return;
        
        const viewport = page.getViewport({ scale: this.scale });
        const context = canvas.getContext('2d');
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        
        const renderContext = {
          canvasContext: context,
          viewport: viewport
        };
        
        await page.render(renderContext).promise;
      } catch (error) {
        console.error('Failed to render page:', error);
      }
    },
    
    async startEditing() {
      this.editMode = true;
      this.loadingPdf = true;
      
      try {
        const response = await fetch(
          `/applications/${this.applicationId}/documents/${this.document.id}/pdf-fields`
        );
        
        const data = await response.json();
        
        if (data.success && data.fields) {
          // Convert fields to editable format with positions
          this.editableFields = [];
          Object.values(data.fields).forEach(pageFields => {
            pageFields.forEach(field => {
              this.editableFields.push({
                ...field,
                modified: false,
                rect: this.calculateFieldRect(field)
              });
            });
          });
        } else {
          alert('Failed to load PDF fields: ' + (data.message || 'Unknown error'));
          this.editMode = false;
        }
      } catch (error) {
        console.error('Failed to load PDF fields:', error);
        alert('Failed to load PDF fields');
        this.editMode = false;
      } finally {
        this.loadingPdf = false;
      }
    },
    
    calculateFieldRect(field) {
      // This is a simplified calculation - you'll need to adjust based on actual PDF coordinates
      const canvas = this.$refs.pdfCanvas;
      if (!canvas) return { x: 0, y: 0, width: 200, height: 30 };
      
      // Map field names to approximate positions (you'll need to adjust these based on your actual PDF layout)
      const fieldPositions = {
        'merchant_name': { x: 100, y: 150, width: 300, height: 30 },
        'trading_name': { x: 100, y: 200, width: 300, height: 30 },
        'company_number': { x: 100, y: 250, width: 300, height: 30 },
        'registered_address': { x: 100, y: 300, width: 300, height: 30 },
        'contact_email': { x: 100, y: 350, width: 300, height: 30 },
        'contact_phone': { x: 100, y: 400, width: 300, height: 30 },
        'transaction_percentage': { x: 100, y: 450, width: 150, height: 30 },
        'transaction_fixed_fee': { x: 300, y: 450, width: 150, height: 30 },
        'monthly_fee': { x: 100, y: 500, width: 150, height: 30 },
        'monthly_minimum': { x: 300, y: 500, width: 150, height: 30 },
        'setup_fee': { x: 100, y: 550, width: 150, height: 30 },
      };
      
      return fieldPositions[field.name] || { x: 100, y: 100, width: 200, height: 30 };
    },
    
    handleCanvasClick(event) {
      if (!this.editMode) return;
      
      // Focus the input field at the clicked position
      const rect = event.target.getBoundingClientRect();
      const x = event.clientX - rect.left;
      const y = event.clientY - rect.top;
      
      // Find the closest field to the click
      const field = this.editableFields.find(f => {
        return x >= f.rect.x && x <= f.rect.x + f.rect.width &&
               y >= f.rect.y && y <= f.rect.y + f.rect.height;
      });
      
      if (field) {
        // Focus the corresponding input
        this.$nextTick(() => {
          const inputs = this.$el.querySelectorAll('input');
          const index = this.editableFields.indexOf(field);
          if (inputs[index]) {
            inputs[index].focus();
          }
        });
      }
    },
    
    cancelEditing() {
      this.editMode = false;
      this.editableFields = [];
    },
    
    async saveEdits() {
      this.saving = true;
      
      // Convert fields to simple object
      const fieldValues = {};
      this.editableFields.forEach(field => {
        if (field.modified) {
          fieldValues[field.name] = field.value;
        }
      });
      
      try {
        const response = await fetch(
          `/applications/${this.applicationId}/documents/${this.document.id}/save-pdf-edits`,
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ field_values: fieldValues })
          }
        );

        const data = await response.json();
        
        if (data.success) {
          alert('✅ Document edited successfully! A new version has been created.');
          this.editMode = false;
          this.close();
          router.reload({ preserveScroll: true });
        } else {
          alert('❌ Failed to save: ' + data.message);
        }
      } catch (error) {
        console.error('Failed to save edits:', error);
        alert('❌ Failed to save edits. Please try again.');
      } finally {
        this.saving = false;
      }
    },

    parseIfNeeded() {
      if (this.isCSV && this.document?.content) {
        this.parseCSV(atob(this.document.content));
      }
    },

    parseCSV(raw) {
      const lines = raw.split(/\r?\n/).filter((l) => l.trim().length > 0);
      if (lines.length === 0) return;
      this.csvHeaders = lines[0].split(",");
      this.csvRows = lines.slice(1).map((l) => l.split(","));
    },

    reset() {
      this.csvRows = [];
      this.csvHeaders = [];
      this.editMode = false;
      this.editableFields = [];
      this.pdfError = null;
      this.pdfDoc = null;
    },

    close() {
      this.$emit("close");
    },

    formatMimeType(type) {
      const map = {
        "application/pdf": "PDF Document",
        "image/jpeg": "JPEG Image",
        "image/jpg": "JPG Image",
        "image/png": "PNG Image",
        "text/csv": "CSV File",
      };
      return map[type] || type;
    },
    
    formatFieldName(fieldName) {
      return fieldName
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
    },
  },
};
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>