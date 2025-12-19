<template>
  <div>
    <Head title="Settings" />
    <h1 class="text-3xl font-bold text-white mb-8">Settings</h1>

    <!-- Tabs -->
    <div class="mb-6">
      <div class="border-b border-primary-800/30">
        <nav class="-mb-px flex space-x-8">
          <button
            @click="activeTab = 'general'"
            :class="[
              'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
              activeTab === 'general'
                ? 'border-magenta-500 text-magenta-400'
                : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-300'
            ]"
          >
            General
          </button>
          <button
            @click="activeTab = 'importer'"
            :class="[
              'py-4 px-1 border-b-2 font-medium text-sm transition-colors',
              activeTab === 'importer'
                ? 'border-magenta-500 text-magenta-400'
                : 'border-transparent text-gray-400 hover:text-gray-300 hover:border-gray-300'
            ]"
          >
            Merchant Importer
          </button>
        </nav>
      </div>
    </div>

    <!-- General Tab Content -->
    <div v-show="activeTab === 'general'">
      <!-- User Roles & Permissions -->
      <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden mb-8">
        <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
          <h2 class="text-magenta-400 font-bold text-lg">User Roles & Permissions</h2>
        </div>
        
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="text-magenta-400 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
                <th class="px-6 py-3 text-left">User</th>
                <th class="px-6 py-3 text-left">Email</th>
                <th class="px-6 py-3 text-left">Role</th>
                <th class="px-6 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="user in users"
                :key="user.id"
                class="border-b border-primary-800/20 hover:bg-primary-900/30 transition-colors"
              >
                <td class="px-6 py-4 text-white font-medium">{{ user.name }}</td>
                <td class="px-6 py-4 text-gray-300">{{ user.email }}</td>
                <td class="px-6 py-4">
                  <span
                    class="px-3 py-1 rounded-full text-xs font-semibold"
                    :class="user.is_admin ? 'bg-purple-900/50 text-purple-300' : 'bg-gray-700 text-gray-300'"
                  >
                    {{ user.is_admin ? 'Admin' : 'User' }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <button
                    @click="toggleAdmin(user)"
                    class="text-sm px-3 py-1 rounded transition-colors"
                    :class="user.is_admin ? 'bg-red-900/50 text-red-300 hover:bg-red-900/70' : 'bg-green-900/50 text-green-300 hover:bg-green-900/70'"
                  >
                    {{ user.is_admin ? 'Remove Admin' : 'Make Admin' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <!-- Available Permissions Info -->
        <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
          <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <h2 class="text-magenta-400 font-bold text-lg">Permission Levels</h2>
          </div>
          
          <div class="p-8 space-y-6">
            <div class="space-y-4">
              <div class="bg-purple-900/20 border border-purple-700/30 rounded-lg p-4">
                <h3 class="text-purple-300 font-semibold mb-2">Admin Users</h3>
                <ul class="text-gray-300 text-sm space-y-1 list-disc list-inside">
                  <li>Can view and manage all accounts</li>
                  <li>Can view and manage all applications</li>
                  <li>Can view and manage all users</li>
                  <li>Can access settings and permissions</li>
                  <li>Can send account credentials emails</li>
                  <li>Full system access</li>
                </ul>
              </div>

              <div class="bg-blue-900/20 border border-blue-700/30 rounded-lg p-4">
                <h3 class="text-blue-300 font-semibold mb-2">Account Access (Merchant Accounts)</h3>
                <ul class="text-gray-300 text-sm space-y-1 list-disc list-inside">
                  <li>✅ Can view and edit their own account only</li>
                  <li>✅ Can view and manage their own applications</li>
                  <li>✅ Can view progress tracker for their applications</li>
                  <li>❌ Cannot see other accounts or users</li>
                  <li>❌ Cannot access admin features</li>
                  <li>❌ Cannot see accounts list in menu</li>
                  <li>❌ Cannot see users page</li>
                  <li>✅ Login via separate account login page</li>
                </ul>
              </div>
            </div>
          </div>
        </div>

        <!-- Additional Info -->
        <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
          <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <h2 class="text-magenta-400 font-bold text-lg">Additional Info</h2>
          </div>

          <div class="p-8 space-y-6">
            <div class="space-y-4">
              <div class="bg-white/10 border border-white/10 rounded-lg p-4">
                <h3 class="text-magenta-400 font-semibold mb-2">Important Notes</h3>
                <ul class="text-gray-300 text-sm space-y-1 list-disc list-inside">
                  <li>Accounts (merchants) login at <code class="bg-dark-900 px-2 py-1 rounded">/account/login</code></li>
                  <li>Users (admin/staff) login at <code class="bg-dark-900 px-2 py-1 rounded">/login</code></li>
                  <li>Accounts cannot create applications until their status is "Confirmed"</li>
                  <li>Only admins can send account credentials and manage email reminders</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Merchant Importer Tab Content -->
    <div v-show="activeTab === 'importer'">
      <MerchantImporter :imports="importHistory" />
    </div>
  </div>
</template>

<script>
  import { Head } from '@inertiajs/vue3'
  import Layout from '@/Shared/Layout.vue'
  import MerchantImporter from '@/Pages/Settings/MerchantImporter.vue'
  
  export default {
    components: {
      Head,
      MerchantImporter,
    },
    layout: Layout,
    props: {
      users: Array,
      roles: Array,
      permissions: Array,
      importHistory: {
        type: Object,
        default: () => ({ data: [], links: [] }) // Add default value
      },
    },
    data() {
      return {
        activeTab: 'general',
      }
    },
    methods: {
      toggleAdmin(user) {
        const action = user.is_admin ? 'remove admin from' : 'make admin'
        if (confirm(`Are you sure you want to ${action} ${user.name}?`)) {
          this.$inertia.post(`/settings/users/${user.id}/toggle-admin`)
        }
      },
    },
  }
  </script>