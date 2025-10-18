<script>
import { Head } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'

export default {
  components: {
    Head,
  },
  layout: Layout,
  props: {
    applications: Object,
    stats: Object,
  },
}
</script>

<template>
  <div class="min-h-screen">
    <Head title="Dashboard" />
    <h1 class="mb-4 text-3xl font-bold text-white">Dashboard</h1>
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-8 border border-primary-800/30 shadow-2xl">
      <p class="leading-relaxed text-gray-300 text-lg">
        Welcome to the dashboard, here you can see an overview of the progress and statistics of all applications, and contact activity.
      </p>
    </div>
    
    <!-- Stats Section -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-12">
      <div class="bg-gradient-to-br from-primary-600/20 to-magenta-600/20 backdrop-blur-sm rounded-xl p-6 border border-primary-700/30 hover:border-magenta-500/50 transition-all duration-300 hover:scale-105">
        <div class="text-5xl font-bold text-white mb-2">{{ stats.total }}</div>
        <div class="text-magenta-400 font-semibold">Total Applications</div>
      </div>

      <div class="bg-gradient-to-br from-primary-600/20 to-magenta-600/20 backdrop-blur-sm rounded-xl p-6 border border-primary-700/30 hover:border-magenta-500/50 transition-all duration-300 hover:scale-105">
        <div class="text-5xl font-bold text-white mb-2">{{ stats.approved }}</div>
        <div class="text-magenta-400 font-semibold">Applications Approved</div>
      </div>
      <div class="bg-gradient-to-br from-primary-600/20 to-magenta-600/20 backdrop-blur-sm rounded-xl p-6 border border-primary-700/30 hover:border-magenta-500/50 transition-all duration-300 hover:scale-105">
        <div class="text-5xl font-bold text-white mb-2">5</div>
        <div class="text-magenta-400 font-semibold">Active Contacts</div>
      </div>
      <div class="bg-gradient-to-br from-primary-600/20 to-magenta-600/20 backdrop-blur-sm rounded-xl p-6 border border-primary-700/30 hover:border-magenta-500/50 transition-all duration-300 hover:scale-105">
        <div class="text-5xl font-bold text-white mb-2">3</div>
        <div class="text-magenta-400 font-semibold">Years Experience</div>
      </div>
    </div>
  </div>
</template>