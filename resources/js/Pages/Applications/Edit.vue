<template>
  <div>
    <Head :title="form.name" />
    <h1 class="mb-6 text-3xl font-bold text-white">
      <Link class="text-magenta-400 hover:text-magenta-500 transition-colors" href="/applications">Applications</Link>
      <span class="text-gray-500 mx-2">/</span>
      {{ form.name }}
    </h1>

    <trashed-message v-if="application.deleted_at" class="mb-6" @restore="restore">
      This application has been deleted.
    </trashed-message>

    <div class="max-w-3xl bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
      <form @submit.prevent="update">
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <text-input v-model="form.name" :error="form.errors.name" class="pb-8 pr-6 w-full lg:w-1/2" label="Name" />
          <text-input v-model="application.account_name" :error="form.errors.name" disabled class="pb-8 pr-6 w-full lg:w-1/2" label="Merchant Account Name" />
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

        <div class="flex items-center px-8 py-4 bg-dark-900/60 border-t border-primary-800/40">
          <button
            v-if="!application.deleted_at"
            class="text-red-500 hover:text-red-400 transition-colors"
            tabindex="-1"
            type="button"
            @click="destroy"
          >
            Delete Application
          </button>
          <loading-button :loading="form.processing" class="btn-primary ml-auto" type="submit">
            Update Application
          </loading-button>
        </div>
      </form>
    </div>

    <h2 class="mt-12 text-2xl font-bold text-magenta-400">Contacts</h2>
    <div class="mt-6 bg-dark-800/50 backdrop-blur-sm rounded-xl shadow-2xl overflow-hidden border border-primary-800/30">
      <table class="w-full whitespace-nowrap">
        <thead>
          <tr class="text-left font-bold bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-700/50">
            <th class="pb-4 pt-6 px-6 text-magenta-400">Name</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400">City</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400" colspan="2">Phone</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="contact in application.contacts"
            :key="contact.id"
            class="hover:bg-primary-900/30 focus-within:bg-primary-900/30 transition-colors border-b border-primary-800/20"
          >
            <td class="border-t border-primary-800/20">
              <Link class="flex items-center px-6 py-4 text-gray-200 hover:text-magenta-400 transition-colors"
                :href="`/contacts/${contact.id}/edit`">
                {{ contact.name }}
                <icon v-if="contact.deleted_at" name="trash" class="shrink-0 ml-2 w-3 h-3 fill-gray-500" />
              </Link>
            </td>
            <td class="border-t border-primary-800/20">
              <Link class="flex items-center px-6 py-4 text-gray-300 hover:text-gray-100 transition-colors"
                :href="`/contacts/${contact.id}/edit`" tabindex="-1">
                {{ contact.city }}
              </Link>
            </td>
            <td class="border-t border-primary-800/20">
              <Link class="flex items-center px-6 py-4 text-gray-300 hover:text-gray-100 transition-colors"
                :href="`/contacts/${contact.id}/edit`" tabindex="-1">
                {{ contact.phone }}
              </Link>
            </td>
            <td class="w-px border-t border-primary-800/20">
              <Link class="flex items-center px-4 py-4 hover:bg-primary-800/50 rounded transition-colors"
                :href="`/contacts/${contact.id}/edit`" tabindex="-1">
                <icon name="cheveron-right" class="block w-6 h-6 fill-gray-400 hover:fill-magenta-400 transition-colors" />
              </Link>
            </td>
          </tr>
          <tr v-if="application.contacts.length === 0">
            <td class="px-6 py-8 text-center text-gray-400 border-t border-primary-800/20" colspan="4">
              No contacts found.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Icon from '@/Shared/Icon.vue'
import Layout from '@/Shared/Layout.vue'
import TextInput from '@/Shared/TextInput.vue'
import SelectInput from '@/Shared/SelectInput.vue'
import LoadingButton from '@/Shared/LoadingButton.vue'
import TrashedMessage from '@/Shared/TrashedMessage.vue'

export default {
  components: { Head, Icon, Link, LoadingButton, SelectInput, TextInput, TrashedMessage },
  layout: Layout,
  props: { application: Object },
  remember: 'form',
  data() {
    return {
      form: this.$inertia.form({
        name: this.application.name,
        account_name: this.application.account_name,
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
    destroy() {
      if (confirm('Are you sure you want to delete this application?')) {
        this.$inertia.delete(`/applications/${this.application.id}`)
      }
    },
    restore() {
      if (confirm('Are you sure you want to restore this application?')) {
        this.$inertia.put(`/applications/${this.application.id}/restore`)
      }
    },
  },
}
</script>
