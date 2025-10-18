<template>
  <div>
    <Head title="Progress Tracker" />
    <h1 class="mb-4 text-3xl font-bold text-white">Progress Tracker</h1>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
      <div class="bg-gradient-to-br from-primary-600/20 to-magenta-600/20 backdrop-blur-sm rounded-xl p-6 border border-primary-700/30">
        <div class="text-3xl font-bold text-white mb-1">{{ stats.total_applications }}</div>
        <div class="text-sm text-magenta-400">Total Applications</div>
      </div>
      <div class="bg-gradient-to-br from-yellow-600/20 to-orange-600/20 backdrop-blur-sm rounded-xl p-6 border border-yellow-700/30">
        <div class="text-3xl font-bold text-white mb-1">{{ stats.pending_contracts }}</div>
        <div class="text-sm text-yellow-400">Pending Contracts</div>
      </div>
      <div class="bg-gradient-to-br from-blue-600/20 to-cyan-600/20 backdrop-blur-sm rounded-xl p-6 border border-blue-700/30">
        <div class="text-3xl font-bold text-white mb-1">{{ stats.awaiting_approval }}</div>
        <div class="text-sm text-cyan-400">Awaiting Approval</div>
      </div>
      <div class="bg-gradient-to-br from-purple-600/20 to-pink-600/20 backdrop-blur-sm rounded-xl p-6 border border-purple-700/30">
        <div class="text-3xl font-bold text-white mb-1">{{ stats.awaiting_payment }}</div>
        <div class="text-sm text-pink-400">Awaiting Payment</div>
      </div>
      <div class="bg-gradient-to-br from-orange-600/20 to-red-600/20 backdrop-blur-sm rounded-xl p-6 border border-orange-700/30">
        <div class="text-3xl font-bold text-white mb-1">{{ stats.in_integration }}</div>
        <div class="text-sm text-orange-400">In Integration</div>
      </div>
      <div class="bg-gradient-to-br from-green-600/20 to-emerald-600/20 backdrop-blur-sm rounded-xl p-6 border border-green-700/30">
        <div class="text-3xl font-bold text-white mb-1">{{ stats.live_accounts }}</div>
        <div class="text-sm text-green-400">Live Accounts</div>
      </div>
    </div>

    <!-- Applications Table -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl shadow-2xl overflow-hidden border border-primary-800/30">
      <table class="w-full whitespace-nowrap">
        <thead>
          <tr class="text-left font-bold bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-700/50">
            <th class="pb-4 pt-6 px-6 text-magenta-400">Application</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400">Status</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400">Progress</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400">Gateway</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400">Last Updated</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400"></th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="application in applications.data" 
            :key="application.id"
            class="hover:bg-primary-900/30 focus-within:bg-primary-900/30 transition-colors duration-150 border-b border-primary-800/20"
          >
            <td class="border-t border-primary-800/20">
              <Link 
                class="flex items-center px-6 py-4 text-gray-200 hover:text-magenta-400 transition-colors" 
                :href="`/applications/${application.id}/status`"
              >
                <div>
                  <div class="font-semibold">{{ application.name }}</div>
                  <div v-if="application.trading_name" class="text-sm text-gray-400">{{ application.trading_name }}</div>
                </div>
                <icon 
                  v-if="application.requires_attention" 
                  name="alert-circle" 
                  class="shrink-0 ml-2 w-5 h-5 fill-red-500 animate-pulse" 
                />
              </Link>
            </td>
            <td class="border-t border-primary-800/20 px-6 py-4">
              <span 
                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold"
                :class="getStatusClass(application.current_step)"
              >
                {{ formatStatus(application.current_step) }}
              </span>
            </td>
            <td class="border-t border-primary-800/20 px-6 py-4">
              <div class="flex items-center gap-3">
                <div class="flex-1 bg-gray-700 rounded-full h-2 overflow-hidden">
                  <div 
                    class="h-full bg-gradient-to-r from-magenta-500 to-primary-500 transition-all duration-500"
                    :style="{ width: application.progress_percentage + '%' }"
                  ></div>
                </div>
                <span class="text-sm text-gray-300 font-semibold min-w-[3rem]">
                  {{ application.progress_percentage }}%
                </span>
              </div>
            </td>
            <td class="border-t border-primary-800/20 px-6 py-4">
              <span v-if="application.gateway_provider" class="text-gray-300 capitalize">
                {{ application.gateway_provider }}
              </span>
              <span v-else class="text-gray-500">-</span>
            </td>
            <td class="border-t border-primary-800/20 px-6 py-4 text-gray-400 text-sm">
              {{ application.updated_at }}
            </td>
            <td class="w-px border-t border-primary-800/20">
              <Link 
                class="flex items-center px-4 hover:bg-primary-800/50 py-4 rounded transition-colors" 
                :href="`/applications/${application.id}/status`"
              >
                <icon name="cheveron-right" class="block w-6 h-6 fill-gray-400" />
              </Link>
            </td>
          </tr>
          <tr v-if="applications.length === 0">
            <td class="px-6 py-8 border-t border-primary-800/20 text-gray-400 text-center" colspan="6">
              No applications found.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
  <pagination class="mt-6" :links="applications.links" />
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Icon from '@/Shared/Icon.vue'
import Layout from '@/Shared/Layout.vue'
import Pagination from '@/Shared/Pagination.vue'

export default {
  components: {
    Head,
    Link,
    Pagination,
    Icon,
  },
  layout: Layout,
  props: {
    applications: Object,
    stats: Object,
  },
  methods: {
    formatStatus(status) {
      const statusMap = {
        created: 'Created',
        application_sent: 'Contract Sent',
        contract_completed: 'Contract Signed',
        contract_submitted: 'Submitted',
        application_approved: 'Approved',
        approval_email_sent: 'Approval Sent',
        invoice_sent: 'Invoice Sent',
        invoice_paid: 'Payment Received',
        gateway_integrated: 'Integration Complete',
        account_live: 'Live',
      }
      return statusMap[status] || status
    },
    getStatusClass(status) {
      const classMap = {
        created: 'bg-gray-700 text-gray-300',
        application_sent: 'bg-yellow-900/50 text-yellow-300',
        contract_completed: 'bg-blue-900/50 text-blue-300',
        contract_submitted: 'bg-blue-900/50 text-blue-300',
        application_approved: 'bg-purple-900/50 text-purple-300',
        approval_email_sent: 'bg-purple-900/50 text-purple-300',
        invoice_sent: 'bg-orange-900/50 text-orange-300',
        invoice_paid: 'bg-cyan-900/50 text-cyan-300',
        gateway_integrated: 'bg-emerald-900/50 text-emerald-300',
        account_live: 'bg-green-900/50 text-green-300',
      }
      return classMap[status] || 'bg-gray-700 text-gray-300'
    },
  },
}
</script>