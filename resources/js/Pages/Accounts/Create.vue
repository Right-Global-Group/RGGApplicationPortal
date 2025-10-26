<template>
  <div>
    <Head title="Create Account" />
    <h1 class="mb-6 text-3xl font-bold text-white">
      <Link class="text-magenta-400 hover:text-magenta-500 transition-colors" href="/accounts">Accounts</Link>
      <span class="text-gray-500 mx-2">/</span>
      Create
    </h1>

    <div class="max-w-3xl bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
      <form @submit.prevent="store">
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <text-input v-model="form.name" :error="form.errors.name" class="pb-8 pr-6 w-full lg:w-1/2" label="Account Name" />
          <text-input v-model="form.email" :error="form.errors.email" class="pb-8 pr-6 w-full lg:w-1/2" label="Email" type="email" />
          <file-input v-model="form.photo" :error="form.errors.photo" class="pb-8 pr-6 w-full lg:w-1/2" label="Photo" accept="image/*" />
          <div class="pb-8 pr-6 w-full lg:w-1/2">
            <label class="block text-gray-300 font-medium mb-2">Created By</label>
            <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
              {{ user.first_name }} {{ user.last_name }}
            </div>
          </div>
        </div>
        <div class="flex items-center justify-end px-8 py-4 bg-dark-900/60 border-t border-primary-800/40">
          <loading-button :loading="form.processing" class="btn-primary" type="submit">Create Account</loading-button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import TextInput from '@/Shared/TextInput.vue'
import FileInput from '@/Shared/FileInput.vue'
import LoadingButton from '@/Shared/LoadingButton.vue'

export default {
  components: { Head, Link, LoadingButton, TextInput, FileInput },
  layout: Layout,
  remember: 'form',
  props: {
    user: Object,
  },
  data() {
    return {
      form: this.$inertia.form({
        name: null,
        email: null,
        photo: null,
      }),
    }
  },
  methods: {
    store() {
      this.form.post('/accounts')
    },
  },
}
</script>