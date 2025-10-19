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
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl shadow-2xl overflow-hidden border border-primary-800/30">
      <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-magenta-400 font-bold text-lg">Applications</h2>
      </div>

      <table v-if="applications.length > 0" class="w-full whitespace-nowrap">
        <thead>
          <tr class="text-left font-bold text-sm border-b border-primary-800/20">
            <th class="px-8 py-3 text-magenta-400">Name</th>
            <th class="px-8 py-3 text-magenta-400">Email</th>
            <th class="px-8 py-3 text-magenta-400">City</th>
            <th class="px-8 py-3 text-magenta-400">Created At</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="application in applications"
            :key="application.id"
            class="hover:bg-primary-900/30 transition-colors duration-150 border-b border-primary-800/20"
          >
            <td class="px-8 py-4 text-white">
              {{ application.name }}
            </td>
            <td class="px-8 py-4 text-gray-300">
              {{ application.email || '—' }}
            </td>
            <td class="px-8 py-4 text-gray-300">
              {{ application.city || '—' }}
            </td>
            <td class="px-8 py-4 text-gray-300">
              {{ formatDate(application.created_at) }}
            </td>
            <td class="w-px border-t border-primary-800/20">
              <Link 
                class="flex items-center px-4 hover:bg-primary-800/50 py-4 rounded transition-colors" 
                :href="`/applications/${application.id}/edit`" 
                tabindex="-1"
              >
                <icon name="cheveron-right" class="block w-6 h-6 fill-gray-400 group-hover:fill-magenta-400" />
              </Link>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-else class="px-8 py-8 text-center text-gray-400">
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
import Icon from '@/Shared/Icon.vue'

export default {
  components: { Head, Link, LoadingButton, TextInput, Icon },
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
        hour: '2-digit',
        minute: '2-digit',
      })
    },
  },
}
</script>