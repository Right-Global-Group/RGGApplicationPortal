<template>
    <div class="min-h-screen bg-dark-900 flex items-center justify-center px-4">
      <Head title="Account Login" />
      
      <div class="w-full max-w-md">
        <logo class="block mx-auto w-full max-w-xs fill-magenta-400" height="50" />
        <div class="mt-10 bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
          <div class="px-8 py-6 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <h1 class="text-2xl font-bold text-white text-center">Merchant Login</h1>
          </div>
          
          <form @submit.prevent="submit" class="p-8 space-y-6">
            <text-input
              v-model="form.email"
              :error="form.errors.email"
              label="Email"
              type="email"
              autofocus
              autocomplete="username"
            />
            
            <text-input
              v-model="form.password"
              :error="form.errors.password"
              label="Password"
              type="password"
              autocomplete="current-password"
            />
            
            <div class="flex items-center">
              <input
                id="remember"
                v-model="form.remember"
                type="checkbox"
                class="w-4 h-4 bg-dark-900/50 border-primary-800/30 rounded text-magenta-500 focus:ring-magenta-500"
              >
              <label for="remember" class="ml-2 text-sm text-gray-300">Remember me</label>
            </div>
            
            <loading-button
              :loading="form.processing"
              class="btn-primary w-full justify-center"
              type="submit"
            >
              Login
            </loading-button>
          </form>
        </div>
        
        <div class="mt-6 text-center">
          <Link href="/login" class="text-magenta-400 hover:text-magenta-300 text-sm">
            User Login Instead
          </Link>
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import { Head, Link } from '@inertiajs/vue3'
  import TextInput from '@/Shared/TextInput.vue'
  import Logo from '@/Shared/Logo.vue'
  import LoadingButton from '@/Shared/LoadingButton.vue'
  
  export default {
    components: {
      Head,
      Link,
      TextInput,
      LoadingButton,
      Logo,
    },
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
      submit() {
        this.form.post('/account/login')
      },
    },
  }
  </script>