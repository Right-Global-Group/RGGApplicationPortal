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
              </div>
            </div>

            <!-- Document viewer -->
            <div v-if="document?.content" class="bg-dark-900/50 rounded-lg p-4 overflow-auto" style="max-height: 70vh;">
              
              <!-- PDF Viewer with PDF.js -->
              <div v-if="isPDF" class="relative">
                <!-- PDF Canvas Container -->
                <div ref="pdfContainer" class="relative bg-white rounded overflow-auto" style="max-height: 60vh;">
                  <canvas ref="pdfCanvas" class="mx-auto"></canvas>
                  
                  <!-- Editable overlay (only in edit mode) -->
                  <div v-if="editMode" class="absolute inset-0 pointer-events-none">
                    <div
                      v-for="field in overlayFields"
                      :key="field.name"
                      :style="{
                        position: 'absolute',
                        left: field.left + 'px',
                        top: field.top + 'px',
                        width: field.width + 'px',
                        height: field.height + 'px',
                      }"
                      class="pointer-events-auto"
                    >
                      <input
                        v-model="field.value"
                        type="text"
                        :placeholder="field.name"
                        class="w-full h-full px-2 bg-yellow-100/90 border-2 border-yellow-500 text-gray-900 text-sm rounded focus:outline-none focus:ring-2 focus:ring-yellow-600"
                        :title="formatFieldName(field.name)"
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
                    <p class="text-gray-400">{{ pdfError || 'Rendering PDF...' }}</p>
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
                💡 Tip: Input fields are overlaid on the PDF. Adjust values and save.
              </div>
              <div v-else></div>
              
              <div class="flex gap-3">
                <button
                  v-if="editMode"
                  @click="cancelEditing"
                  class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg"
                >
                  Cancel
                </button>
                <button
                  v-if="editMode"
                  @click="saveEdits"
                  :disabled="saving"
                  class="px-6 py-2 bg-magenta-600 hover:bg-magenta-700 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
                >
                  <svg v-if="saving" class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  <span>{{ saving ? 'Saving...' : 'Save Edited Version' }}</span>
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

// Store PDF.js library reference at module level
let pdfjsLib = null
let pdfjsInitialized = false

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
      overlayFields: [],
      pdfDoc: null,
      pdfPage: null,
      viewport: null,
      pdfLibLoaded: false,
      pdfError: null,
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
  },
  async mounted() {
    // Initialize PDF.js once for the entire app
    if (typeof window !== 'undefined' && !pdfjsInitialized) {
      try {
        console.log('🔧 Loading PDF.js library...');
        
        // Import PDF.js
        const pdfjs = await import('pdfjs-dist');
        pdfjsLib = pdfjs;
        
        console.log('✅ PDF.js version:', pdfjsLib.version);
        
        // CRITICAL: Use LOCAL worker file from public directory
        // This avoids ALL CDN, CORS, and version mismatch issues
        pdfjsLib.GlobalWorkerOptions.workerSrc = '/js/pdf.worker.js';
        
        console.log('✅ Worker configured to use local file: /js/pdf.worker.js');
        
        pdfjsInitialized = true;
        this.pdfLibLoaded = true;
        
        console.log('✅ PDF.js initialized successfully');
      } catch (error) {
        console.error('❌ Failed to load PDF.js:', error);
        this.pdfError = 'Failed to load PDF library: ' + error.message;
      }
    } else if (pdfjsInitialized) {
      this.pdfLibLoaded = true;
    }
  },
  watch: {
    show(value) {
      if (value && this.document) {
        this.parseIfNeeded();
        if (this.isPDF && this.pdfLibLoaded) {
          this.$nextTick(() => {
            this.renderPdf();
          });
        }
      } else if (!value) {
        this.reset();
      }
    },
    pdfLibLoaded(loaded) {
      if (loaded && this.show && this.isPDF) {
        this.$nextTick(() => {
          this.renderPdf();
        });
      }
    },
  },
  methods: {
    async renderPdf() {
      if (!this.$refs.pdfCanvas || !pdfjsLib) {
        console.error('❌ PDF canvas ref or PDF.js library not available');
        this.pdfError = 'PDF viewer not ready';
        return;
      }
      
      this.loadingPdf = true;
      this.pdfError = null;
      
      try {
        console.log('📄 Starting PDF render...');
        
        // Convert base64 to binary
        const pdfData = atob(this.document.content);
        const pdfArray = new Uint8Array(pdfData.length);
        for (let i = 0; i < pdfData.length; i++) {
          pdfArray[i] = pdfData.charCodeAt(i);
        }
        
        console.log('📦 PDF data converted, size:', pdfArray.length, 'bytes');
        
        // Load PDF document
        const loadingTask = pdfjsLib.getDocument({ 
          data: pdfArray,
          useSystemFonts: false,
        });
        
        console.log('⏳ Loading PDF document...');
        this.pdfDoc = await loadingTask.promise;
        console.log('✅ PDF loaded, pages:', this.pdfDoc.numPages);
        
        // Get first page
        this.pdfPage = await this.pdfDoc.getPage(1);
        console.log('✅ Page 1 loaded');
        
        // Calculate scale to fit container width
        const containerWidth = this.$refs.pdfContainer?.clientWidth || 1000;
        const pageViewport = this.pdfPage.getViewport({ scale: 1.0 });
        const scale = Math.min(containerWidth / pageViewport.width, 2.0);
        
        this.viewport = this.pdfPage.getViewport({ scale });
        console.log('📐 Viewport calculated, scale:', scale);
        
        // Prepare canvas
        const canvas = this.$refs.pdfCanvas;
        const context = canvas.getContext('2d');
        
        canvas.height = this.viewport.height;
        canvas.width = this.viewport.width;
        canvas.style.width = '100%';
        canvas.style.height = 'auto';
        
        console.log('🎨 Canvas prepared, rendering...');
        
        // Render PDF page
        const renderContext = {
          canvasContext: context,
          viewport: this.viewport,
        };
        
        await this.pdfPage.render(renderContext).promise;
        console.log('✅ PDF rendered successfully!');
        
      } catch (error) {
        console.error('❌ Error rendering PDF:', error);
        this.pdfError = 'Failed to render: ' + error.message;
        alert('Failed to render PDF: ' + error.message);
      } finally {
        this.loadingPdf = false;
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
          this.overlayFields = this.convertFieldsToOverlay(data.fields);
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
    
    convertFieldsToOverlay(fieldsData) {
      const overlayFields = [];
      let yPosition = 100;
      const xPosition = 50;
      const fieldHeight = 35;
      const fieldWidth = 400;
      const spacing = 10;
      
      Object.values(fieldsData).forEach(pageFields => {
        pageFields.forEach(field => {
          overlayFields.push({
            name: field.name,
            value: field.value || '',
            left: xPosition,
            top: yPosition,
            width: fieldWidth,
            height: fieldHeight,
          });
          yPosition += fieldHeight + spacing;
        });
      });
      
      return overlayFields;
    },
    
    cancelEditing() {
      this.editMode = false;
      this.overlayFields = [];
      this.$nextTick(() => {
        this.renderPdf();
      });
    },
    
    async saveEdits() {
      this.saving = true;
      
      const fieldValues = {};
      this.overlayFields.forEach(field => {
        fieldValues[field.name] = field.value;
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
      this.overlayFields = [];
      this.pdfDoc = null;
      this.pdfPage = null;
      this.viewport = null;
      this.pdfError = null;
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