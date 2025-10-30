<template>
  <modal @close="$emit('close')" max-width="2xl">
    <div class="p-6">
      <h2 class="text-2xl font-bold text-white mb-6">Request Additional Information</h2>
      
      <!-- Notes Input -->
      <div class="mb-6">
        <label class="block text-gray-300 font-medium mb-2">
          What information do you need?
        </label>
        <textarea
          v-model="notes"
          placeholder="Please provide details about what information is required..."
          rows="5"
          class="w-full px-4 py-3 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 placeholder-gray-500 focus:outline-none focus:border-magenta-500 resize-none"
        ></textarea>
        <p class="text-sm text-gray-400 mt-2">
          This message will be sent to the account holder.
        </p>
      </div>

      <!-- Request Additional Document Checkbox - ONLY SHOW IF documents_approved NOT completed -->
      <div v-if="!documentsApproved" class="mb-6">
        <label class="flex items-center cursor-pointer">
          <input
            type="checkbox"
            v-model="requestAdditionalDocument"
            class="w-4 h-4 text-magenta-600 bg-dark-800 border-primary-800/30 rounded focus:ring-magenta-500 focus:ring-2"
          />
          <span class="ml-2 text-sm text-gray-300">Request additional document</span>
        </label>
      </div>

      <!-- Info message if documents already approved -->
      <div v-else class="mb-6 p-3 bg-blue-900/20 border border-blue-700/30 rounded-lg">
        <div class="flex items-start gap-2">
          <svg class="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
          </svg>
          <div class="flex-1">
            <p class="text-sm text-blue-300">
              Documents have already been approved for this application. Additional document requests can only be made before document approval.
            </p>
          </div>
        </div>
      </div>

      <!-- Additional Document Fields (shown when checkbox is ticked) -->
      <transition
        enter-active-class="transition-all duration-200 ease-out"
        leave-active-class="transition-all duration-150 ease-in"
        enter-from-class="opacity-0 -translate-y-2"
        leave-to-class="opacity-0 -translate-y-2"
      >
        <div v-if="requestAdditionalDocument && !documentsApproved" class="mb-6 p-4 bg-yellow-900/20 border border-yellow-700/30 rounded-lg space-y-3">
          <p class="text-sm text-yellow-300">
            Please specify the name of the additional document to be uploaded below.
          </p>
          
          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">
              Document Name <span class="text-red-400">*</span>
            </label>
            <input
              v-model="additionalDocumentName"
              type="text"
              placeholder="e.g., Tax Return 2024, Utility Bill"
              class="w-full px-3 py-2 bg-dark-800 border border-primary-800/30 rounded-lg text-gray-300 placeholder-gray-500 focus:outline-none focus:border-magenta-500"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-300 mb-1">
              Document Instructions (optional)
            </label>
            <textarea
              v-model="additionalDocumentInstructions"
              rows="3"
              placeholder="Any specific instructions for this document..."
              class="w-full px-3 py-2 bg-dark-800 border border-primary-800/30 rounded-lg text-gray-300 placeholder-gray-500 focus:outline-none focus:border-magenta-500"
            ></textarea>
            <p class="text-xs text-gray-400 mt-1">
              These instructions will be shown to the account holder when uploading the document.
            </p>
          </div>
        </div>
      </transition>

      <!-- Send Now Section -->
      <div class="mb-6 p-4 bg-dark-900/50 border border-primary-800/30 rounded-lg">
        <div class="flex items-start justify-between mb-3">
          <div class="flex-1">
            <h3 class="font-semibold text-white mb-1">Send Request Now</h3>
            <p class="text-sm text-gray-400">
              Sends the request immediately to the account holder.
            </p>
          </div>
        </div>
        <button
          @click="sendNow"
          :disabled="!canSend"
          class="w-full px-4 py-2 bg-red-600 hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg transition-colors font-medium"
        >
          Send Request Now
        </button>
      </div>

      <!-- Divider -->
      <div class="border-t border-primary-800/30 mb-6"></div>

      <!-- Schedule Reminders Section -->
      <div class="p-4 bg-dark-900/50 border border-primary-800/30 rounded-lg">
        <div class="mb-3">
          <h3 class="font-semibold text-white mb-1">Schedule Reminder Emails</h3>
          <p class="text-sm text-gray-400">
            Automatically send reminder emails at the selected interval. <strong>Does not send immediately.</strong>
          </p>
        </div>

        <!-- Active Reminder Warning -->
        <div v-if="hasActiveReminder" class="mb-4 p-3 bg-yellow-900/20 border border-yellow-700/30 rounded-lg">
          <p class="text-sm text-yellow-400">
            ⚠️ An active reminder exists. Setting a new one will replace it.
          </p>
        </div>

        <!-- Interval Selector -->
        <div class="space-y-3">
          <select
            v-model="interval"
            class="w-full px-4 py-2 bg-dark-800 border border-primary-800/30 rounded-lg text-gray-300 focus:outline-none focus:border-magenta-500"
          >
            <option value="">Select reminder interval...</option>
            <option value="1_day">Every 1 day</option>
            <option value="3_days">Every 3 days</option>
            <option value="1_week">Every week</option>
            <option value="2_weeks">Every 2 weeks</option>
            <option value="1_month">Every month</option>
          </select>

          <button
            @click="setReminder"
            :disabled="!canSetReminder"
            class="w-full px-4 py-2 bg-primary-600 hover:bg-primary-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-lg transition-colors font-medium"
          >
            Set Reminder
          </button>
        </div>
      </div>

      <!-- Cancel Button -->
      <div class="mt-6 flex justify-end">
        <button
          @click="$emit('close')"
          class="px-4 py-2 text-gray-400 hover:text-gray-300 transition-colors"
        >
          Cancel
        </button>
      </div>
    </div>
  </modal>
</template>

<script>
import Modal from '@/Shared/Modal.vue'

export default {
  components: { Modal },
  props: {
    applicationId: {
      type: Number,
      required: true,
    },
    hasActiveReminder: {
      type: Boolean,
      default: false,
    },
    currentStep: {
      type: String,
      default: 'created',
    },
    documentsApproved: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      notes: '',
      interval: '',
      requestAdditionalDocument: false,
      additionalDocumentName: '',
      additionalDocumentInstructions: '',
    }
  },
  computed: {
    canSend() {
      if (!this.notes.trim()) return false
      // Only require document name if checkbox is ticked AND documents not approved
      if (this.requestAdditionalDocument && !this.documentsApproved && !this.additionalDocumentName.trim()) return false
      return true
    },
    canSetReminder() {
      if (!this.interval) return false
      if (!this.notes.trim()) return false
      // Only require document name if checkbox is ticked AND documents not approved
      if (this.requestAdditionalDocument && !this.documentsApproved && !this.additionalDocumentName.trim()) return false
      return true
    }
  },
  methods: {
    sendNow() {
      if (!this.canSend) {
        if (this.requestAdditionalDocument && !this.documentsApproved && !this.additionalDocumentName.trim()) {
          alert('Please specify the name of the additional document.')
        } else {
          alert('Please enter the information you need.')
        }
        return
      }

      if (confirm('Send additional information request now?')) {
        this.$inertia.post(`/applications/${this.applicationId}/request-additional-info`, {
          notes: this.notes,
          request_additional_document: this.requestAdditionalDocument && !this.documentsApproved,
          additional_document_name: this.additionalDocumentName,
          additional_document_instructions: this.additionalDocumentInstructions,
        }, {
          onSuccess: () => {
            this.$emit('close')
          }
        })
      }
    },
    setReminder() {
      if (!this.canSetReminder) {
        if (this.requestAdditionalDocument && !this.documentsApproved && !this.additionalDocumentName.trim()) {
          alert('Please specify the name of the additional document.')
        } else if (!this.notes.trim()) {
          alert('Please enter the information you need.')
        } else {
          alert('Please select a reminder interval.')
        }
        return
      }

      const intervalText = this.formatInterval(this.interval)
      if (confirm(`Schedule reminder emails to be sent ${intervalText.toLowerCase()}? No email will be sent immediately.`)) {
        this.$inertia.post(`/applications/${this.applicationId}/set-additional-info-reminder`, {
          notes: this.notes,
          interval: this.interval,
          request_additional_document: this.requestAdditionalDocument && !this.documentsApproved,
          additional_document_name: this.additionalDocumentName,
          additional_document_instructions: this.additionalDocumentInstructions,
        }, {
          onSuccess: () => {
            this.$emit('close')
          }
        })
      }
    },
    formatInterval(interval) {
      const intervals = {
        '1_day': 'Every 1 day',
        '3_days': 'Every 3 days',
        '1_week': 'Every week',
        '2_weeks': 'Every 2 weeks',
        '1_month': 'Every month',
      }
      return intervals[interval] || interval
    },
  },
}
</script>