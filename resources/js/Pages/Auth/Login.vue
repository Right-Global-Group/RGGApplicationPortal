<template>
  <Head title="Login" />
  <div class="flex items-center justify-center p-6 min-h-screen bg-gradient-to-b from-dark-900 to-dark-800">
    <div class="w-full max-w-md">
      <logo class="block mx-auto w-full max-w-xs fill-magenta-400" height="50" />
      <form class="mt-10 bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden" @submit.prevent="login">
        <div class="px-10 py-12">
          <h1 class="text-center text-3xl font-bold text-white">Welcome Back</h1>
          <div class="mt-4 mx-auto w-24 border-b-2 border-magenta-500" />
          <text-input v-model="form.email" :error="form.errors.email" class="mt-10" label="Email" type="email" autofocus autocapitalize="off" />
          <text-input v-model="form.password" :error="form.errors.password" class="mt-6" label="Password" type="password" />
          <label class="flex items-center mt-6 select-none text-gray-300" for="remember">
            <input id="remember" v-model="form.remember" class="mr-2 rounded border-primary-800 bg-dark-900/50 focus:ring-magenta-500" type="checkbox" />
            <span class="text-sm">Remember Me</span>
          </label>
        </div>
        <div class="flex px-10 py-4 bg-dark-900/60 border-t border-primary-800/40">
          <loading-button :loading="form.processing" class="btn-primary ml-auto" type="submit">
            Login
          </loading-button>
        </div>
      </form>
      <div class="mt-6 text-center">
        <Link href="/account/login" class="text-magenta-400 hover:text-magenta-300 text-sm">
          Merchant Login Instead
        </Link>
      </div>
    </div>
  </div>
</template>

<script>
  import { Head, Link } from '@inertiajs/vue3'
  import Logo from '@/Shared/Logo.vue'
  import TextInput from '@/Shared/TextInput.vue'
  import LoadingButton from '@/Shared/LoadingButton.vue'
  
  export default {
    components: { Head, Link, LoadingButton, Logo, TextInput },
    data() {
      return {
        form: this.$inertia.form({
          email: '',
          password: '',
          remember: false,
        }),
      }
    },
    methods: {
      login() {
        this.form.post('/login')
      },
    },
  }
</script>
