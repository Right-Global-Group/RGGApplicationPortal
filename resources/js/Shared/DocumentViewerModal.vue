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
                class="absolute top-4 right-4 text-gray-400 hover:text-white transition-colors"
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
  
              <!-- Header -->
              <div class="mb-4">
                <h3 class="text-xl font-bold text-white">{{ filename }}</h3>
                <p class="text-sm text-gray-400 mt-1">{{ formatMimeType(mimeType) }}</p>
              </div>
  
              <!-- Loading -->
              <div v-if="loading" class="flex items-center justify-center py-20">
                <svg class="animate-spin h-12 w-12 text-magenta-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
              </div>
  
              <!-- Error -->
              <div v-else-if="error" class="bg-red-900/20 border border-red-700/50 rounded-lg p-6 text-center">
                <p class="text-red-300">{{ error }}</p>
              </div>
  
              <!-- Document viewer -->
              <div v-else-if="content" class="bg-dark-900/50 rounded-lg p-2 overflow-auto" style="max-height: 70vh;">
  
                <!-- PDF Viewer -->
                <iframe
                  v-if="isPDF"
                  :src="`data:application/pdf;base64,${content}`"
                  class="w-full h-full"
                  style="min-height: 70vh;"
                ></iframe>
  
                <!-- Image Viewer -->
                <img
                  v-else-if="isImage"
                  :src="`data:${mimeType};base64,${content}`"
                  :alt="filename"
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
  
                <a
                  v-if="downloadUrl"
                  :href="downloadUrl"
                  class="px-4 py-2 bg-primary-600 hover:bg-primary-700 text-white rounded-lg flex items-center gap-2"
                >
                  Download
                </a>
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
      viewUrl: String,
      downloadUrl: String,
    },
    data() {
      return {
        loading: false,
        error: null,
        content: null,
        filename: "",
        mimeType: "",
        csvRows: [],
        csvHeaders: [],
      };
    },
    computed: {
      isPDF() {
        return this.mimeType === "application/pdf";
      },
      isImage() {
        return this.mimeType?.startsWith("image/");
      },
      isCSV() {
        return (
          this.mimeType === "text/csv" ||
          this.filename.toLowerCase().endsWith(".csv")
        );
      },
    },
    watch: {
      show(value) {
        if (value && this.viewUrl) this.loadDocument();
        else if (!value) this.reset();
      },
    },
    methods: {
      async loadDocument() {
        this.loading = true;
        this.error = null;
        this.csvRows = [];
        this.csvHeaders = [];
  
        try {
          const res = await fetch(this.viewUrl);
          const data = await res.json();
  
          if (!data.success) {
            this.error = data.message || "Failed to load document";
            return;
          }
  
          this.content = data.content;
          this.filename = data.filename;
          this.mimeType = data.mime_type;
  
          // Parse CSV if necessary
          if (this.isCSV) {
            this.parseCSV(atob(this.content));
          }
        } catch (e) {
          this.error = "Failed to load document";
        } finally {
          this.loading = false;
        }
      },
  
      parseCSV(raw) {
        const lines = raw.split(/\r?\n/).filter((l) => l.trim().length > 0);
        if (lines.length === 0) return;
  
        this.csvHeaders = lines[0].split(",");
        this.csvRows = lines.slice(1).map((l) => l.split(","));
      },
  
      reset() {
        this.content = null;
        this.filename = "";
        this.mimeType = "";
        this.error = null;
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
  