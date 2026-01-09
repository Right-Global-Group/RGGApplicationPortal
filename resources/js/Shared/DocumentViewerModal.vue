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
              <h3 class="text-xl font-bold text-white">{{ document?.filename || 'Document' }}</h3>
              <p class="text-sm text-gray-400 mt-1">{{ formatMimeType(document?.mime_type) }}</p>
            </div>

            <!-- Document viewer -->
            <div v-if="document?.content" class="bg-dark-900/50 rounded-lg p-2 overflow-auto" style="max-height: 70vh;">

              <!-- PDF Viewer -->
              <iframe
                v-if="isPDF"
                :src="`data:application/pdf;base64,${document.content}`"
                class="w-full h-full"
                style="min-height: 70vh;"
              ></iframe>

              <!-- Image Viewer -->
              <img
                v-else-if="isImage"
                :src="`data:${document.mime_type};base64,${document.content}`"
                :alt="document.filename"
                class="max-w-full max-h-[70vh] mx-auto rounded-lg"
              />

              <!-- CSV Viewer -->
              <div v-else-if="isCSV" class="overflow-auto max-h-[70vh]">
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
            <div class="mt-6 flex justify-end gap-3">
              <button
                @click="close"
                class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg"
              >
                Close
              </button>
            </div>

          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script>
export default {
  emits: ["close"],
  props: {
    show: Boolean,
    document: {
      type: Object,
      default: null,
    },
  },
  data() {
    return {
      csvRows: [],
      csvHeaders: [],
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