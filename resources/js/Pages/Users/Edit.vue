<template>
  <div>
    <Head :title="`${form.first_name} ${form.last_name}`" />
    <h1 class="mb-6 text-3xl font-bold text-white flex items-center">
      <Link class="text-magenta-400 hover:text-magenta-500 transition-colors" href="/users">Users</Link>
      <span class="text-gray-500 mx-2">/</span>
      {{ form.first_name }} {{ form.last_name }}
      <img v-if="user.photo" class="ml-4 w-10 h-10 rounded-full border border-primary-800/30" :src="user.photo" />
    </h1>

    <trashed-message v-if="user.deleted_at" class="mb-6" @restore="restore">
      This user has been deleted.
    </trashed-message>

    <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-10">
      <form @submit.prevent="update">
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <text-input v-model="form.first_name" :error="form.errors.first_name" class="pb-8 pr-6 w-full lg:w-1/2" label="First name" />
          <text-input v-model="form.last_name" :error="form.errors.last_name" class="pb-8 pr-6 w-full lg:w-1/2" label="Last name" />
          <text-input v-model="form.email" :error="form.errors.email" class="pb-8 pr-6 w-full lg:w-1/2" label="Email" />
          <text-input v-model="form.password" :error="form.errors.password" class="pb-8 pr-6 w-full lg:w-1/2" type="password" autocomplete="new-password" label="Password" />
          <file-input v-model="form.photo" :error="form.errors.photo" class="pb-8 pr-6 w-full lg:w-1/2" label="Photo" accept="image/*" />
        </div>
      </form>
    </div>

    <!-- Accounts Created by User Section -->
    <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
      <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-magenta-400 font-bold text-lg">Accounts Created ({{ (accounts || []).length }})</h2>
      </div>

      <div v-if="(accounts || []).length > 0" class="divide-y divide-primary-800/20">
        <Link
          v-for="account in accounts"
          :key="account.id"
          :href="`/accounts/${account.id}/edit`"
          class="flex items-center justify-between px-6 py-4 hover:bg-primary-900/30 transition-colors duration-150 group cursor-pointer"
        >
          <div class="flex-1">
            <div class="font-semibold text-magenta-400 group-hover:text-magenta-300 transition-colors">
              {{ account.name }}
            </div>
            <div class="text-sm text-gray-400 mt-1">
              Created {{ formatDate(account.created_at) }}
            </div>
          </div>
          <svg
            class="w-5 h-5 text-gray-400 group-hover:text-magenta-400 transition-colors"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </Link>
      </div>

      <div v-else class="px-6 py-8 text-gray-400 text-center">
        No accounts created by this user.
      </div>
    </div>

    <!-- Applications Created by User Section -->
    <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
      <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-magenta-400 font-bold text-lg">Applications Created ({{ (applications || []).length }})</h2>
      </div>

      <div v-if="(applications || []).length > 0" class="divide-y divide-primary-800/20">
        <Link
          v-for="app in applications"
          :key="app.id"
          :href="`/applications/${app.id}/edit`"
          class="flex items-center justify-between px-6 py-4 hover:bg-primary-900/30 transition-colors duration-150 group cursor-pointer"
        >
          <div class="flex-1">
            <div class="font-semibold text-magenta-400 group-hover:text-magenta-300 transition-colors">
              {{ app.name }}
            </div>
            <div class="text-sm text-gray-400 mt-1">
              {{ app.account_name }} — Created {{ formatDate(app.created_at) }}
            </div>
          </div>
          <svg
            class="w-5 h-5 text-gray-400 group-hover:text-magenta-400 transition-colors"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </Link>
      </div>

      <div v-else class="px-6 py-8 text-gray-400 text-center">
        No applications created by this user.
      </div>
    </div>

  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import FileInput from '@/Shared/FileInput.vue'
import TextInput from '@/Shared/TextInput.vue'
import SelectInput from '@/Shared/SelectInput.vue'
import LoadingButton from '@/Shared/LoadingButton.vue'
import TrashedMessage from '@/Shared/TrashedMessage.vue'

export default {
  components: { FileInput, Head, Link, LoadingButton, SelectInput, TextInput, TrashedMessage },
  layout: Layout,
  props: { 
    user: Object,
    applications: Array,
    accounts: Array,
  },
  remember: 'form',
  data() {
    return {
      form: this.$inertia.form({
        _method: 'put',
        first_name: this.user.first_name,
        last_name: this.user.last_name,
        email: this.user.email,
        password: '',
        photo: null,
      }),
    }
  },
  methods: {
    update() {
      this.form.post(`/users/${this.user.id}`, {
        onSuccess: () => this.form.reset('password', 'photo'),
      })
    },
    destroy() {
      if (confirm('Are you sure you want to delete this user?')) {
        this.$inertia.delete(`/users/${this.user.id}`)
      }
    },
    restore() {
      if (confirm('Are you sure you want to restore this user?')) {
        this.$inertia.put(`/users/${this.user.id}/restore`)
      }
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