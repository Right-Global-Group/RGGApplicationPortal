<template>
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm">
      <div class="bg-dark-800 rounded-xl shadow-2xl border border-primary-700 max-w-lg w-full p-6">
        <div class="flex items-center justify-between mb-6">
          <h2 class="text-2xl font-bold text-white">Create Invoice</h2>
          <button @click="$emit('close')" class="text-gray-400 hover:text-white">
            <icon name="close" class="w-6 h-6 fill-current" />
          </button>
        </div>
  
        <form @submit.prevent="submit">
          <div class="space-y-4">
            <div>
              <label class="block text-gray-300 font-medium mb-2">Amount (£)</label>
              <input
                v-model="form.amount"
                type="number"
                step="0.01"
                required
                class="form-input w-full bg-primary-900/50 border-primary-700 text-white rounded-lg"
              />
            </div>
  
            <div>
              <label class="block text-gray-300 font-medium mb-2">Type</label>
              <select
                v-model="form.type"
                required
                class="form-select w-full bg-primary-900/50 border-primary-700 text-white rounded-lg"
              >
                <option value="scaling_fee">Scaling Fee</option>
                <option value="monthly">Monthly Fee</option>
                <option value="other">Other</option>
              </select>
            </div>
  
            <div>
              <label class="block text-gray-300 font-medium mb-2">Due Date</label>
              <input
                v-model="form.due_date"
                type="date"
                required
                class="form-input w-full bg-primary-900/50 border-primary-700 text-white rounded-lg"
              />
            </div>
  
            <div>
              <label class="block text-gray-300 font-medium mb-2">Notes (Optional)</label>
              <textarea
                v-model="form.notes"
                rows="3"
                class="form-textarea w-full bg-primary-900/50 border-primary-700 text-white rounded-lg"
              ></textarea>
            </div>
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
              class="btn-primary"
            >
              Create Invoice
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
          amount: '',
          type: 'scaling_fee',
          due_date: '',
          notes: '',
        },
      }
    },
    methods: {
      submit() {
        this.$inertia.post(`/applications/${this.applicationId}/invoices`, this.form, {
          onSuccess: () => this.$emit('close'),
        })
      },
    },
  }
  </script>