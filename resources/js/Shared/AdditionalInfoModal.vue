<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
      <div class="bg-dark-800 rounded-xl shadow-2xl border border-primary-700 max-w-lg w-full p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-2xl font-bold text-white">Request Additional Information</h2>
          <button @click="$emit('close')" class="text-gray-400 hover:text-white">
            <icon name="close" class="w-6 h-6 fill-current" />
          </button>
        </div>
  
        <form @submit.prevent="submit">
          <div>
            <label class="block text-gray-300 font-medium mb-2">Information Needed</label>
            <textarea
              v-model="form.notes"
              rows="6"
              required
              placeholder="Please provide details about what additional information is required..."
              class="form-textarea w-full bg-primary-900/50 border-primary-700 text-white rounded-lg"
            ></textarea>
          </div>
  
          <div class="flex justify-end gap-3 mt-6">
            <button
              type="button"
              @click="$emit('close')"
              class="px-4 py-2 text-gray-300 hover:text-white transition-colors"
            >
              Cancel
            </button>
            <button
              type="submit"
              class="btn-primary bg-yellow-600 hover:bg-yellow-700"
            >
              Send Request
            </button>
          </div>
        </form>
      </div>
    </div>
  </template>
  
  <script>
  import Icon from '@/Shared/Icon.vue'
  
  export default {
    components: { Icon },
    props: {
      applicationId: Number,
    },
    data() {
      return {
        form: {
          notes: '',
        },
      }
    },
    methods: {
      submit() {
        this.$inertia.post(`/applications/${this.applicationId}/request-additional-info`, this.form, {
          onSuccess: () => this.$emit('close'),
        })
      },
    },
  }
  </script>