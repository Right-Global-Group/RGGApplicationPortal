<template>
  <div>
    <Head :title="account.name" />
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold text-white">
        <Link class="text-magenta-400 hover:text-magenta-500 transition-colors" href="/accounts">Accounts</Link>
        <span class="text-gray-500 mx-2">/</span>
        {{ account.name }}
      </h1>
    </div>

    <!-- Account Details Section -->
    <div class="max-w-3xl bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
      <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-magenta-400 font-bold text-lg">Account Details</h2>
      </div>
      <div class="p-8 space-y-4">
        <div>
          <label class="block text-gray-300 font-medium mb-2">Created By</label>
          <Link v-if="account.user_id" :href="`/users/${account.user_id}/edit`" class="inline-block px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-magenta-400 hover:text-magenta-300 hover:border-magenta-500/50 transition-colors">
            {{ account.user_name || '—' }}
          </Link>
          <div v-else class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
            {{ account.user_name || '—' }}
          </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-gray-300 font-medium mb-2">Created At</label>
            <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
              {{ formatDate(account.created_at) }}
            </div>
          </div>
          <div>
            <label class="block text-gray-300 font-medium mb-2">Updated At</label>
            <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
              {{ formatDate(account.updated_at) }}
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Edit Account Form -->
    <div class="max-w-3xl bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
      <form @submit.prevent="update">
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <text-input v-model="form.name" :error="form.errors.name" class="pb-8 pr-6 w-full" label="Account Name" />
        </div>
        <div class="flex items-center justify-between px-8 py-4 bg-dark-900/60 border-t border-primary-800/40">
          <Link 
            href="/accounts" 
            class="text-gray-400 hover:text-gray-300 transition-colors"
          >
            Back to Accounts
          </Link>
          <loading-button :loading="form.processing" class="btn-primary" type="submit">Save Changes</loading-button>
        </div>
      </form>
    </div>

    <!-- Create Application Button -->
    <div class="mb-8">
      <Link 
        :href="`/applications/create?account_id=${account.id}`" 
        class="btn-primary inline-flex items-center gap-2"
      >
        <span>Create</span>
        <span>Application for Account</span>
      </Link>
    </div>

    <!-- Applications List -->
    <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
      <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-magenta-400 font-bold text-lg">Applications ({{ (applications || []).length }})</h2>
      </div>
      <table v-if="(applications || []).length > 0" class="w-full text-left">
        <thead>
          <tr class="text-magenta-400 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <th class="px-6 py-3">Name</th>
            <th class="px-6 py-3">Email</th>
            <th class="px-6 py-3">City</th>
            <th class="px-6 py-3">Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="application in applications" :key="application.id" class="hover:bg-primary-900/30 transition-colors duration-150 border-b border-primary-800/20">
            <td class="px-6 py-3 text-white">
              <Link :href="`/applications/${application.id}/edit`" class="text-magenta-400 hover:text-magenta-300 transition-colors">
                {{ application.name }}
              </Link>
            </td>
            <td class="px-6 py-3 text-gray-300">
              {{ application.email || '—' }}
            </td>
            <td class="px-6 py-3 text-gray-300">
              {{ application.city || '—' }}
            </td>
            <td class="px-6 py-3 text-gray-400">
              {{ formatDate(application.created_at) }}
            </td>
          </tr>
        </tbody>
      </table>
      <div v-else class="px-6 py-8 text-gray-400 text-center">
        No applications found for this account.
      </div>
    </div>
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import TextInput from '@/Shared/TextInput.vue'
import LoadingButton from '@/Shared/LoadingButton.vue'

export default {
  components: { Head, Link, LoadingButton, TextInput },
  layout: Layout,
  remember: 'form',
  props: {
    account: Object,
    applications: Array,
  },
  data() {
    return {
      form: this.$inertia.form({
        name: this.account.name,
      }),
    }
  },
  methods: {
    update() {
      this.form.put(`/accounts/${this.account.id}`)
    },
    formatDate(date) {
      if (!date) return '—'
      const d = new Date(date)
      return d.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
      })
    },
  },
}
</script>