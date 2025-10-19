<template>
  <div>
    <Head title="Create Application" />
    <h1 class="mb-6 text-3xl font-bold text-white">
      <Link class="text-magenta-400 hover:text-magenta-500 transition-colors" href="/applications">Applications</Link>
      <span class="text-gray-500 mx-2">/</span>
      Create
    </h1>

    <div class="max-w-3xl bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
      <form @submit.prevent="store">
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <select-input v-model="form.account_id" :error="form.errors.account_id" class="pb-8 pr-6 w-full lg:w-1/2" label="Account">
            <option :value="null" />
            <option v-for="account in accounts" :key="account.id" :value="account.id">
              {{ account.name }}
            </option>
          </select-input>
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
        <div class="flex items-center justify-end px-8 py-4 bg-dark-900/60 border-t border-primary-800/40">
          <loading-button :loading="form.processing" class="btn-primary" type="submit">Create Application</loading-button>
        </div>
      </form>
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
    accounts: Array,
    preselected_account_id: [Number, null],
  },
  data() {
    return {
      form: this.$inertia.form({
        account_id: this.preselected_account_id,
        name: null,
        email: null,
        phone: null,
        address: null,
        city: null,
        region: null,
        country: null,
        postal_code: null,
      }),
    }
  },
  methods: {
    store() {
      this.form.post('/applications')
    },
  },
}
</script>