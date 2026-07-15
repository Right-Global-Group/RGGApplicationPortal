<template>
  <div class="min-h-screen bg-dark-900 flex items-center justify-center px-4">
    <Head title="Check your email" />

    <div class="w-full max-w-md">
      <logo class="block mx-auto w-full max-w-xs fill-magenta-400" height="50" />
      <div class="mt-10 bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
        <div class="px-8 py-6 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
          <h1 class="text-2xl font-bold text-white text-center">Check your email</h1>
        </div>

        <div class="p-8 space-y-4 text-center">
          <p class="text-gray-300">
            That link had expired, so we've sent a new one to
            <span class="font-semibold text-white">{{ email }}</span>.
          </p>
          <p class="text-sm text-gray-400">
            Open the newest email from us and click the link to sign in. You can close this page.
          </p>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Head } from '@inertiajs/vue3'
import Logo from '@/Shared/Logo.vue'

export default {
  components: {
    Head,
    Logo,
  },
  props: {
    email: String,
  },
}
</script>
