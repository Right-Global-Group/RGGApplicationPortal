<template>
  <teleport to="body">
    <transition name="modal">
      <div
        v-if="show"
        class="fixed inset-0 z-50 overflow-y-auto"
        @click.self="close"
      >
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
          <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75" @click="close"></div>

          <div class="inline-block w-full max-w-6xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-dark-800 shadow-2xl rounded-2xl border border-primary-700/50 relative">
            
            <button
              @click="close"
              class="absolute top-4 right-4 text-gray-400 hover:text-white transition-colors z-10"
            >
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>

            <div class="mb-4">
              <div class="flex items-center justify-between">
                <div>
                  <h3 class="text-xl font-bold text-white">{{ document?.filename || 'Document' }}</h3>
                  <p class="text-sm text-gray-400 mt-1">{{ formatMimeType(document?.mime_type) }}</p>
                </div>
<!--                 
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
                </button> -->
              </div>
            </div>

            <div v-if="document?.content" class="bg-dark-900/50 rounded-lg p-4 overflow-auto relative" style="max-height: 70vh;">
              
              <div v-if="isPDF" class="relative">
                <div v-if="editMode" class="bg-white rounded p-6 space-y-4 max-h-[60vh] overflow-auto">
                  <div class="mb-4 p-3 bg-blue-900/20 border border-blue-700/30 rounded-lg">
                    <p class="text-sm text-blue-300">
                      💡 <strong>Edit the fields below.</strong> The PDF will be modified in your browser and uploaded to the server.
                    </p>
                  </div>
                  
                  <div v-for="field in editableFields" :key="field.name" class="space-y-2">
                    <label class="block text-sm font-semibold text-gray-700">
                      {{ formatFieldName(field.name) }}
                    </label>
                    <input
                      v-model="field.value"
                      type="text"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-yellow-500"
                      :placeholder="formatFieldName(field.name)"
                      @input="field.modified = true"
                    />
                  </div>
                </div>

                <div v-else class="relative bg-white rounded overflow-auto" style="max-height: 60vh;">
                  <embed
                    :src="`data:${document.mime_type};base64,${document.content}`"
                    type="application/pdf"
                    class="w-full"
                    style="min-height: 600px;"
                  />
                </div>

                <div v-if="loadingFields || processing" class="absolute inset-0 flex items-center justify-center bg-dark-900/80 rounded">
                  <div class="text-center">
                    <svg class="animate-spin h-12 w-12 text-magenta-500 mx-auto mb-4" fill="none" viewBox="0 0 24 24">
                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="text-gray-400">{{ processing ? 'Processing PDF...' : 'Loading edit fields...' }}</p>
                  </div>
                </div>
              </div>

              <img
                v-else-if="isImage"
                :src="`data:${document.mime_type};base64,${document.content}`"
                :alt="document.filename"
                class="max-w-full max-h-[60vh] mx-auto rounded-lg"
              />

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

              <div v-else class="p-8 text-center">
                <p class="text-gray-400 mb-4">This file cannot be previewed in the browser.</p>
                <p class="text-sm text-gray-500">{{ document.mime_type }}</p>
              </div>

            </div>

            <div class="mt-6 flex justify-between items-center">
              <div v-if="editMode" class="text-sm text-gray-400 flex items-center gap-2">
                <svg class="w-4 h-4 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                Edit fields and save. The PDF will be modified with your changes.
              </div>
              <div v-else></div>
              
              <div class="flex gap-3">
                <button
                  v-if="editMode"
                  @click="saveEditsClientSide"
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
import { PDFDocument, rgb, StandardFonts } from 'pdf-lib'

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
      loadingFields: false,
      saving: false,
      processing: false,
      editableFields: [],
      pdfDoc: null,
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
      } else if (!value) {
        this.reset();
      }
    },
  },
  methods: {
    async startEditing() {
      this.editMode = true;
      this.loadingFields = true;
      
      try {
        const response = await fetch(
          `/applications/${this.applicationId}/documents/${this.document.id}/pdf-fields`
        );
        
        const data = await response.json();
        
        if (data.success && data.fields) {
          this.editableFields = [];
          Object.values(data.fields).forEach(pageFields => {
            pageFields.forEach(field => {
              this.editableFields.push({
                ...field,
                modified: false,
              });
            });
          });
          
          // Load PDF into memory for editing
          await this.loadPdfForEditing();
        } else {
          alert('Failed to load PDF fields: ' + (data.message || 'Unknown error'));
          this.editMode = false;
        }
      } catch (error) {
        console.error('Failed to load PDF fields:', error);
        alert('Failed to load PDF fields');
        this.editMode = false;
      } finally {
        this.loadingFields = false;
      }
    },
    
    async loadPdfForEditing() {
      try {
        // Convert base64 to array buffer
        const pdfBytes = Uint8Array.from(atob(this.document.content), c => c.charCodeAt(0));
        this.pdfDoc = await PDFDocument.load(pdfBytes);
        console.log('PDF loaded successfully for editing');
      } catch (error) {
        console.error('Failed to load PDF:', error);
        throw error;
      }
    },
    
    async saveEditsClientSide() {
      this.saving = true;
      this.processing = true;
      
      try {
        // Get field positions for this document type
        const fieldPositions = this.getFieldPositions();
        
        // Get the first page
        const pages = this.pdfDoc.getPages();
        const firstPage = pages[0];
        
        // Embed font
        const font = await this.pdfDoc.embedFont(StandardFonts.Helvetica);
        const boldFont = await this.pdfDoc.embedFont(StandardFonts.HelveticaBold);
        
        // Draw white rectangles and new text for each modified field
        for (const field of this.editableFields) {
          if (!field.modified) continue;
          
          const position = fieldPositions[field.name];
          if (!position) continue;
          
          // Draw white rectangle to cover old text
          firstPage.drawRectangle({
            x: position.x,
            y: position.y,
            width: position.width,
            height: position.height,
            color: rgb(1, 1, 1),
          });
          
          // Draw new text
          firstPage.drawText(field.value, {
            x: position.x + 2,
            y: position.y + 2,
            size: position.fontSize || 10,
            font: position.bold ? boldFont : font,
            color: rgb(0, 0, 0),
          });
        }
        
        // Save the PDF
        const pdfBytes = await this.pdfDoc.save();
        
        // Convert to base64
        const base64 = btoa(String.fromCharCode(...pdfBytes));
        
        // Upload to server
        const response = await fetch(
          `/applications/${this.applicationId}/documents/${this.document.id}/upload-edited-pdf`,
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
              pdf_content: base64,
              original_filename: this.document.filename
            })
          }
        );

        const data = await response.json();
        
        if (data.success) {
          alert('✅ Document edited successfully! The PDF has been updated.');
          this.editMode = false;
          this.close();
          router.reload({ preserveScroll: true });
        } else {
          alert('❌ Failed to save: ' + data.message);
        }
      } catch (error) {
        console.error('Failed to edit PDF:', error);
        alert('❌ Failed to edit PDF. Error: ' + error.message);
      } finally {
        this.saving = false;
        this.processing = false;
      }
    },
    
    getFieldPositions() {
      // PDF coordinates are from BOTTOM-LEFT corner
      // You'll need to calibrate these based on your PDF
      
      if (this.documentCategory === 'contract') {
        return {
          'merchant_name': { x: 50, y: 700, width: 200, height: 15, fontSize: 10, bold: true },
          'transaction_fixed_fee': { x: 400, y: 400, width: 80, height: 15, fontSize: 9 },
          'monthly_minimum': { x: 400, y: 380, width: 100, height: 15, fontSize: 8 },
          'monthly_fee': { x: 400, y: 360, width: 80, height: 15, fontSize: 9 },
          'transaction_percentage': { x: 400, y: 340, width: 80, height: 15, fontSize: 9 },
        };
      } else {
        return {
          'registered_company_name': { x: 100, y: 700, width: 200, height: 15, fontSize: 10 },
          'trading_name': { x: 100, y: 680, width: 200, height: 15, fontSize: 10 },
          'registration_number': { x: 100, y: 660, width: 200, height: 15, fontSize: 10 },
        };
      }
    },
    
    cancelEditing() {
      this.editMode = false;
      this.editableFields = [];
      this.pdfDoc = null;
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