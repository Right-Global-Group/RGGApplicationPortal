<template>
  <div>
    <Head :title="application.name" />
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold text-white">
        <Link class="text-magenta-400 hover:text-magenta-500 transition-colors" href="/applications">Applications</Link>
        <span class="text-gray-500 mx-2">/</span>
        {{ application.name }}
      </h1>
    </div>

    <!-- Top Section: Two columns -->
    <div class="flex flex-col lg:flex-row gap-6 mb-8">
      <!-- Left Column: Details + Form -->
      <div class="flex-1 flex flex-col gap-6">
        <!-- Application Details -->
        <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
          <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <h2 class="text-magenta-400 font-bold text-lg">Application Details</h2>
          </div>
          <div class="p-8 space-y-4">
            <div>
              <label class="block text-gray-300 font-medium mb-2">Account</label>
              <Link v-if="application.account_id" :href="`/accounts/${application.account_id}/edit`" class="inline-block px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-magenta-400 hover:text-magenta-300 hover:border-magenta-500/50 transition-colors">
                {{ application.account_name || '—' }}
              </Link>
              <div v-else class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                {{ application.account_name || '—' }}
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-gray-300 font-medium mb-2">Created By</label>
                <Link v-if="application.user_id" :href="`/users/${application.user_id}/edit`" class="inline-block px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-magenta-400 hover:text-magenta-300 hover:border-magenta-500/50 transition-colors">
                  {{ application.user_name || '—' }}
                </Link>
                <div v-else class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  {{ application.user_name || '—' }}
                </div>
              </div>
              <div>
                <label class="block text-gray-300 font-medium mb-2">Created At</label>
                <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  {{ formatDate(application.created_at) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Edit Application Form -->
        <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
          <form @submit.prevent="update">
            <div class="flex flex-wrap -mb-8 -mr-6 p-8">
              <text-input v-model="form.name" :error="form.errors.name" class="pb-8 pr-6 w-full lg:w-1/2" label="Name" />
              <text-input v-model="form.email" :error="form.errors.email" class="pb-8 pr-6 w-full lg:w-1/2" label="Email" />
              <text-input v-model="form.phone" :error="form.errors.phone" class="pb-8 pr-6 w-full lg:w-1/2" label="Phone" />
              <text-input v-model="form.address" :error="form.errors.address" class="pb-8 pr-6 w-full lg:w-1/2" label="Address" />
              <text-input v-model="form.city" :error="form.errors.city" class="pb-8 pr-6 w-full lg:w-1/2" label="City" />
              <text-input v-model="form.region" :error="form.errors.region" class="pb-8 pr-6 w-full lg:w-1/2" label="Province/State" />
              <select-input v-model="form.country" :error="form.errors.country" class="pb-8 pr-6 w-full lg:w-1/2" label="Country">
                <option :value="null" />
                <option value="CA">Canada</option>
                <option value="US">United States</option>
              </select-input>
              <text-input v-model="form.postal_code" :error="form.errors.postal_code" class="pb-8 pr-6 w-full lg:w-1/2" label="Postal code" />
            </div>
            <div class="flex items-center justify-between px-8 py-4 bg-dark-900/60 border-t border-primary-800/40">
              <Link href="/applications" class="text-gray-400 hover:text-gray-300 transition-colors">
                Back to Applications
              </Link>
              <loading-button :loading="form.processing" class="btn-primary" type="submit">
                Save Changes
              </loading-button>
            </div>
          </form>
        </div>
      </div>

      <!-- Right Column: Application Status -->
      <div class="w-full lg:w-1/3 bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
        <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
          <h2 class="text-magenta-400 font-bold text-lg">Application Status</h2>
        </div>
        <div class="p-8">
          <Link
            :href="`/applications/${application.id}/status`"
            class="btn-primary w-full text-center"
          >
            View Status
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import TextInput from '@/Shared/TextInput.vue'
import SelectInput from '@/Shared/SelectInput.vue'
import LoadingButton from '@/Shared/LoadingButton.vue'

export default {
  components: { Head, Link, LoadingButton, SelectInput, TextInput },
  layout: Layout,
  remember: 'form',
  props: {
    application: Object,
    accounts: Array,
  },
  data() {
    return {
      form: this.$inertia.form({
        account_id: this.application.account_id,
        name: this.application.name,
        email: this.application.email,
        phone: this.application.phone,
        address: this.application.address,
        city: this.application.city,
        region: this.application.region,
        country: this.application.country,
        postal_code: this.application.postal_code,
      }),
    }
  },
  methods: {
    update() {
      this.form.put(`/applications/${this.application.id}`)
    },
    formatDate(date) {
      if (!date) return '—'
      const d = new Date(date)
      return d.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    },
  },
}
</script>
