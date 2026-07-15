<template>
  <div class="min-h-screen bg-dark-900 flex items-center justify-center px-4">
    <Head title="Send me a login link" />

    <div class="w-full max-w-md">
      <logo class="block mx-auto w-full max-w-xs fill-magenta-400" height="50" />
      <div class="mt-10 bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
        <div class="px-8 py-6 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
          <h1 class="text-2xl font-bold text-white text-center">Merchant Login</h1>
        </div>

        <div v-if="$page.props.flash.success" class="mx-8 mt-8 rounded-lg border border-primary-800/30 bg-dark-900/50 px-4 py-3 text-sm text-gray-300">
          {{ $page.props.flash.success }}
        </div>

        <form @submit.prevent="submit" class="p-8 space-y-6">
          <p class="text-sm text-gray-400">
            There's no password to remember. Enter your email address and we'll send you a
            link that signs you straight in.
          </p>

          <text-input
            v-model="form.email"
            :error="form.errors.email"
            label="Email"
            type="email"
            autofocus
            autocomplete="username"
          />

          <loading-button
            :loading="form.processing"
            class="btn-primary w-full justify-center"
            type="submit"
          >
            Email me a link
          </loading-button>
        </form>
      </div>
    </div>
  </div>
</template>

<script>
import { Head } from '@inertiajs/vue3'
import TextInput from '@/Shared/TextInput.vue'
import Logo from '@/Shared/Logo.vue'
import LoadingButton from '@/Shared/LoadingButton.vue'

export default {
  components: {
    Head,
    TextInput,
    LoadingButton,
    Logo,
  },
  data() {
    return {
      form: this.$inertia.form({
        email: '',
      }),
    }
  },
  methods: {
    submit() {
      this.form.post('/account/login')
    },
  },
}
</script>
