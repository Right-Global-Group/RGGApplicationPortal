<template>
  <teleport to="body">
    <transition name="modal">
      <div v-if="show" class="fixed inset-0 z-50 overflow-y-auto" @click.self="closeModal">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
          <!-- Background overlay -->
          <div class="fixed inset-0 transition-opacity bg-gray-900 bg-opacity-75" @click="closeModal"></div>

          <!-- Modal panel -->
          <div class="inline-block align-bottom bg-dark-800 rounded-xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full border border-primary-800/30">
            <!-- Header -->
            <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
              <div class="flex items-center justify-between">
                <h3 class="text-xl font-bold text-magenta-400">Generate Cashflows Switch Notice</h3>
                <button @click="closeModal" class="text-gray-400 hover:text-gray-300 transition-colors">
                  <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>

            <!-- Body -->
            <form @submit.prevent="submit">
              <div class="px-6 py-6 space-y-4">
                <div class="bg-blue-900/20 border border-blue-700/50 rounded-lg p-4">
                  <p class="text-blue-300 text-sm">
                    Creates a DocuSign agreement for a formal notice confirming the move from Cashflows to
                    Cardstream. Submitting opens DocuSign for you to review and finish; the account then gets
                    an email to sign their part. This step is optional.
                  </p>
                </div>

                <!-- Account Name -->
                <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
                  <label class="block text-sm text-gray-400 mb-2">Account Name</label>
                  <input
                    v-model="form.account_name"
                    type="text"
                    required
                    maxlength="100"
                    class="w-full bg-dark-700 border border-primary-700/50 rounded-lg px-4 py-2 text-white focus:border-magenta-500 focus:ring-2 focus:ring-magenta-500/20"
                  />
                  <p class="text-xs text-gray-500 mt-2">
                    Used inline in the letter's opening paragraph.
                  </p>
                  <p v-if="errors.account_name" class="text-xs text-red-400 mt-1">{{ errors.account_name }}</p>
                </div>

                <!-- Recipient Name (Kind regards signature) -->
                <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
                  <label class="block text-sm text-gray-400 mb-2">Sign-off Name</label>
                  <input
                    v-model="form.recipient_name"
                    type="text"
                    required
                    maxlength="100"
                    class="w-full bg-dark-700 border border-primary-700/50 rounded-lg px-4 py-2 text-white focus:border-magenta-500 focus:ring-2 focus:ring-magenta-500/20"
                  />
                  <p class="text-xs text-gray-500 mt-2">
                    {{ accountRecipientName ? 'Defaults to the account\'s recipient name - edit if you need a different name here.' : 'This account has no recipient name on file - enter who should sign off the letter.' }}
                    Used under "Kind regards," in the letter.
                  </p>
                  <p v-if="errors.recipient_name" class="text-xs text-red-400 mt-1">{{ errors.recipient_name }}</p>
                </div>

                <!-- Logo -->
                <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4">
                  <label class="block text-sm text-gray-400 mb-2">Logo</label>
                  <div class="flex items-center gap-4">
                    <img
                      v-if="logoPreviewUrl"
                      :src="logoPreviewUrl"
                      alt="Logo preview"
                      class="w-16 h-16 rounded-lg object-cover border border-primary-800/30 bg-dark-700"
                    />
                    <div v-else class="w-16 h-16 rounded-lg border border-dashed border-primary-800/30 flex items-center justify-center text-xs text-gray-500">
                      No logo
                    </div>
                    <div class="flex-1">
                      <input
                        ref="logoInput"
                        type="file"
                        accept="image/*"
                        @change="onLogoChange"
                        class="w-full text-sm text-gray-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-primary-700 file:text-white hover:file:bg-primary-600"
                      />
                      <p class="text-xs text-gray-500 mt-2">
                        {{ accountPhotoUrl ? 'Defaults to the account\'s logo on file - upload a file to override it for this letter.' : 'This account has no logo on file - upload one to use here.' }}
                      </p>
                    </div>
                  </div>
                  <p v-if="errors.logo" class="text-xs text-red-400 mt-1">{{ errors.logo }}</p>
                </div>

                <p v-if="submitError" class="text-sm text-red-400">{{ submitError }}</p>
              </div>

              <!-- Footer -->
              <div class="px-6 py-4 bg-dark-900/60 border-t border-primary-800/40 flex justify-end gap-3">
                <button
                  type="button"
                  @click="closeModal"
                  class="px-4 py-2 text-gray-400 hover:text-gray-300 transition-colors"
                >
                  Cancel
                </button>
                <loading-button
                  :loading="submitting"
                  class="btn-primary"
                  type="submit"
                >
                  Generate
                </loading-button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script>
import LoadingButton from '@/Shared/LoadingButton.vue'

export default {
  components: {
    LoadingButton,
  },
  props: {
    show: {
      type: Boolean,
      default: false,
    },
    applicationId: {
      type: Number,
      required: true,
    },
    accountName: {
      type: String,
      default: '',
    },
    accountRecipientName: {
      type: String,
      default: '',
    },
    accountPhotoUrl: {
      type: String,
      default: null,
    },
  },
  emits: ['close', 'generated'],
  data() {
    return {
      form: {
        account_name: this.accountName || '',
        recipient_name: this.accountRecipientName || '',
        logo: null,
      },
      logoPreviewUrl: this.accountPhotoUrl || null,
      errors: {},
      submitError: null,
      submitting: false,
    }
  },
  watch: {
    show(newVal) {
      if (newVal) {
        this.form.account_name = this.accountName || ''
        this.form.recipient_name = this.accountRecipientName || ''
        this.form.logo = null
        this.logoPreviewUrl = this.accountPhotoUrl || null
        this.errors = {}
        this.submitError = null
        if (this.$refs.logoInput) this.$refs.logoInput.value = ''
      }
    },
  },
  methods: {
    closeModal() {
      this.$emit('close')
    },
    onLogoChange(event) {
      const file = event.target.files?.[0] || null
      this.form.logo = file
      this.logoPreviewUrl = file ? URL.createObjectURL(file) : (this.accountPhotoUrl || null)
    },
    async submit() {
      this.submitting = true
      this.errors = {}
      this.submitError = null

      try {
        const formData = new FormData()
        formData.append('account_name', this.form.account_name)
        formData.append('recipient_name', this.form.recipient_name)
        if (this.form.logo) {
          formData.append('logo', this.form.logo)
        }

        const response = await fetch(`/applications/${this.applicationId}/cashflows-switch-notice`, {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
          body: formData,
        })

        const data = await response.json()

        if (data.success && data.signing_url) {
          // Let the parent open + track the popup, so it's wired into the same
          // postMessage/reload handling as every other signing flow on the page -
          // otherwise nothing here would ever tell Status.vue this happened.
          this.$emit('generated', data.signing_url)
          this.$emit('close')
        } else {
          this.submitError = data.message || 'Failed to generate the Cashflows switch notice.'
        }
      } catch (error) {
        console.error('Error generating Cashflows switch notice:', error)
        this.submitError = 'Failed to generate the Cashflows switch notice, please try again.'
      } finally {
        this.submitting = false
      }
    },
  },
}
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
