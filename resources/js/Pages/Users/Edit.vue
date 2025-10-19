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

    <div class="max-w-3xl bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-10">
      <form @submit.prevent="update">
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <text-input v-model="form.first_name" :error="form.errors.first_name" class="pb-8 pr-6 w-full lg:w-1/2" label="First name" />
          <text-input v-model="form.last_name" :error="form.errors.last_name" class="pb-8 pr-6 w-full lg:w-1/2" label="Last name" />
          <text-input v-model="form.email" :error="form.errors.email" class="pb-8 pr-6 w-full lg:w-1/2" label="Email" />
          <text-input v-model="form.password" :error="form.errors.password" class="pb-8 pr-6 w-full lg:w-1/2" type="password" autocomplete="new-password" label="Password" />
          <select-input v-model="form.owner" :error="form.errors.owner" class="pb-8 pr-6 w-full lg:w-1/2" label="Owner">
            <option :value="true">Yes</option>
            <option :value="false">No</option>
          </select-input>
          <file-input v-model="form.photo" :error="form.errors.photo" class="pb-8 pr-6 w-full lg:w-1/2" label="Photo" accept="image/*" />
        </div>

        <div class="flex items-center px-8 py-4 bg-dark-900/60 border-t border-primary-800/40">
          <button
            v-if="!user.deleted_at"
            class="text-red-500 hover:text-red-400 transition-colors"
            type="button"
            @click="destroy"
          >
            Delete User
          </button>
          <loading-button :loading="form.processing" class="btn-primary ml-auto" type="submit">Update User</loading-button>
        </div>
      </form>
    </div>

    <!-- Accounts Created by User Section -->
    <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
      <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-magenta-400 font-bold text-lg">Accounts Created ({{ (accounts || []).length }})</h2>
      </div>
      <table v-if="(accounts || []).length > 0" class="w-full text-left">
        <thead>
          <tr class="text-magenta-400 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <th class="px-6 py-3">Name</th>
            <th class="px-6 py-3">Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="account in accounts" :key="account.id" class="hover:bg-primary-900/30 transition-colors duration-150 border-b border-primary-800/20">
            <td class="px-6 py-3 text-white">
              <Link :href="`/accounts/${account.id}/edit`" class="text-magenta-400 hover:text-magenta-300 transition-colors">
                {{ account.name }}
              </Link>
            </td>
            <td class="px-6 py-3 text-gray-400">{{ formatDate(account.created_at) }}</td>
          </tr>
        </tbody>
      </table>
      <div v-else class="px-6 py-8 text-gray-400 text-center">
        No accounts created by this user.
      </div>
    </div>

    <!-- Applications Section -->
    <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
      <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-magenta-400 font-bold text-lg">Applications Created ({{ (applications || []).length }})</h2>
      </div>
      <table v-if="(applications || []).length > 0" class="w-full text-left">
        <thead>
          <tr class="text-magenta-400 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <th class="px-6 py-3">Name</th>
            <th class="px-6 py-3">Account</th>
            <th class="px-6 py-3">Created</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="app in applications" :key="app.id" class="hover:bg-primary-900/30 transition-colors duration-150 border-b border-primary-800/20">
            <td class="px-6 py-3 text-white">{{ app.name }}</td>
            <td class="px-6 py-3 text-gray-300">{{ app.account_name }}</td>
            <td class="px-6 py-3 text-gray-400">{{ formatDate(app.created_at) }}</td>
          </tr>
        </tbody>
      </table>
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
        owner: this.user.owner,
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