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
                    Generates a formal notice letter requesting to move from Cashflows to Cardstream,
                    attaches it to this application's documents, and adds it to the CardStream submission email.
                    This step is optional.
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
                    Used both inline in the letter and in the "Kind regards," sign-off.
                  </p>
                  <p v-if="form.errors.account_name" class="text-xs text-red-400 mt-1">{{ form.errors.account_name }}</p>
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
                  <p v-if="form.errors.logo" class="text-xs text-red-400 mt-1">{{ form.errors.logo }}</p>
                </div>
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
                  :loading="form.processing"
                  class="btn-primary"
                  type="submit"
                >
                  Generate & Attach
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
    accountPhotoUrl: {
      type: String,
      default: null,
    },
  },
  emits: ['close'],
  data() {
    return {
      form: this.$inertia.form({
        account_name: this.accountName || '',
        logo: null,
      }),
      logoPreviewUrl: this.accountPhotoUrl || null,
    }
  },
  watch: {
    show(newVal) {
      if (newVal) {
        this.form.account_name = this.accountName || ''
        this.form.logo = null
        this.logoPreviewUrl = this.accountPhotoUrl || null
        this.form.clearErrors()
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
    submit() {
      this.form.post(`/applications/${this.applicationId}/cashflows-switch-notice`, {
        forceFormData: true,
        onSuccess: () => this.$emit('close'),
      })
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
