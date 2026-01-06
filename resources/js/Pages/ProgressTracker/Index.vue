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
        <div class="text-3xl font-bold text-white mb-1">{{ stats.awaiting_documents }}</div>
        <div class="text-sm text-yellow-400">Awaiting Documents</div>
      </div>
      <div class="bg-gradient-to-br from-blue-600/20 to-cyan-600/20 backdrop-blur-sm rounded-xl p-6 border border-blue-700/30">
        <div class="text-3xl font-bold text-white mb-1">{{ stats.contract_sent }}</div>
        <div class="text-sm text-cyan-400">Contract Sent</div>
      </div>
      <div class="bg-gradient-to-br from-purple-600/20 to-pink-600/20 backdrop-blur-sm rounded-xl p-6 border border-purple-700/30">
        <div class="text-3xl font-bold text-white mb-1">{{ stats.contract_signed }}</div>
        <div class="text-sm text-pink-400">Contract Signed</div>
      </div>
      <div class="bg-gradient-to-br from-orange-600/20 to-red-600/20 backdrop-blur-sm rounded-xl p-6 border border-orange-700/30">
        <div class="text-3xl font-bold text-white mb-1">{{ stats.awaiting_payment }}</div>
        <div class="text-sm text-orange-400">Awaiting Payment</div>
      </div>
      <div class="bg-gradient-to-br from-green-600/20 to-emerald-600/20 backdrop-blur-sm rounded-xl p-6 border border-green-700/30">
        <div class="text-3xl font-bold text-white mb-1">{{ stats.application_approved }}</div>
        <div class="text-sm text-green-400">Application Approved</div>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 mb-6 border border-primary-800/30">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Search Application Name -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Search Application</label>
          <input
            v-model="form.search"
            type="text"
            placeholder="Search by name..."
            class="w-full bg-dark-700 border border-primary-800/30 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-magenta-500 focus:border-transparent"
            @input="debouncedFilter"
          />
        </div>

        <!-- Search Account Name -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Search Account</label>
          <input
            v-model="form.account_search"
            type="text"
            placeholder="Search by account..."
            class="w-full bg-dark-700 border border-primary-800/30 rounded-lg px-4 py-2 text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-magenta-500 focus:border-transparent"
            @input="debouncedFilter"
          />
        </div>

        <!-- Status Filter Dropdown -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
          <div class="relative">
            <button
              @click="statusDropdownOpen = !statusDropdownOpen"
              class="w-full bg-dark-700 border border-primary-800/30 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-magenta-500 focus:border-transparent text-left flex items-center justify-between"
            >
              <span v-if="form.status.length === 0" class="text-gray-500">Select statuses...</span>
              <span v-else class="text-gray-300">{{ form.status.length }} selected</span>
              <svg class="w-5 h-5 text-gray-400" :class="{ 'rotate-180': statusDropdownOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
              </svg>
            </button>

            <!-- Dropdown Menu -->
            <div
              v-if="statusDropdownOpen"
              class="absolute bottom-full left-0 right-0 mb-2 bg-dark-700 border border-primary-800/30 rounded-lg shadow-lg z-50 max-h-64 overflow-y-auto"
            >
              <div
                v-for="(label, value) in statusOptions"
                :key="value"
                @click="toggleStatus(value)"
                class="px-4 py-3 hover:bg-primary-800/50 cursor-pointer flex items-center gap-2 border-b border-primary-800/20 last:border-b-0 transition-colors"
              >
                <input
                  type="checkbox"
                  :checked="form.status.includes(value)"
                  class="w-4 h-4 rounded accent-magenta-500"
                  @click.stop="toggleStatus(value)"
                />
                <span class="text-gray-300">{{ label }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Date From -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Updated From</label>
          <input
            v-model="form.date_from"
            type="date"
            class="w-full bg-dark-700 border border-primary-800/30 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-magenta-500 focus:border-transparent"
            @change="filter"
          />
        </div>

        <!-- Date To -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Updated To</label>
          <input
            v-model="form.date_to"
            type="date"
            class="w-full bg-dark-700 border border-primary-800/30 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-magenta-500 focus:border-transparent"
            @change="filter"
          />
        </div>
      </div>

      <!-- Selected Status Bubbles -->
      <div v-if="form.status.length > 0" class="mt-4 flex flex-wrap gap-2">
        <div
          v-for="statusValue in form.status"
          :key="statusValue"
          class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-sm font-medium bg-magenta-500/20 text-magenta-300 border border-magenta-500/50"
        >
          {{ statusOptions[statusValue] }}
          <button
            @click="removeStatus(statusValue)"
            class="hover:text-magenta-100 transition-colors"
          >
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
              <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
          </button>
        </div>
      </div>

      <!-- Clear Filters Button -->
      <div v-if="hasFilters" class="mt-4">
        <button
          @click="clearFilters"
          class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
        >
          Clear Filters
        </button>
      </div>
    </div>

    <!-- Applications Table -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl shadow-2xl overflow-hidden border border-primary-800/30">
      <table class="w-full whitespace-nowrap">
        <thead>
          <tr class="text-left font-bold bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <th class="pb-4 pt-6 px-6 text-magenta-400">Application</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400">Account Name</th>
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
              <span v-if="application.account_name" class="text-gray-300">
                {{ application.account_name }}
              </span>
              <span v-else class="text-gray-500">-</span>
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
          <tr v-if="applications.data.length === 0">
            <td class="px-6 py-8 border-t border-primary-800/20 text-gray-400 text-center" colspan="7">
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
import { Head, Link, router } from '@inertiajs/vue3'
import Icon from '@/Shared/Icon.vue'
import Layout from '@/Shared/Layout.vue'
import Pagination from '@/Shared/Pagination.vue'
import { reactive, computed, ref } from 'vue'
import throttle from 'lodash/throttle'

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
    filters: Object,
    statusOptions: Object,
  },
  setup(props) {
    const statusDropdownOpen = ref(false)

    const form = reactive({
      search: props.filters.search || '',
      account_search: props.filters.account_search || '',
      status: props.filters.status || [],
      date_from: props.filters.date_from || '',
      date_to: props.filters.date_to || '',
    })

    const hasFilters = computed(() => {
      return (
        form.search ||
        form.account_search ||
        (Array.isArray(form.status) && form.status.length > 0) ||
        form.date_from ||
        form.date_to
      )
    })

    const filter = () => {
      router.get('/progress-tracker', form, {
        preserveState: true,
        preserveScroll: true,
      })
    }

    const debouncedFilter = throttle(filter, 300)

    const clearFilters = () => {
      form.search = ''
      form.account_search = ''
      form.status = []
      form.date_from = ''
      form.date_to = ''
      statusDropdownOpen.value = false
      filter()
    }

    const toggleStatus = (statusValue) => {
      const index = form.status.indexOf(statusValue)
      if (index > -1) {
        form.status.splice(index, 1)
      } else {
        form.status.push(statusValue)
      }
      filter()
    }

    const removeStatus = (statusValue) => {
      const index = form.status.indexOf(statusValue)
      if (index > -1) {
        form.status.splice(index, 1)
        filter()
      }
    }

    return {
      form,
      hasFilters,
      filter,
      debouncedFilter,
      clearFilters,
      toggleStatus,
      removeStatus,
      statusDropdownOpen,
    }
  },
  methods: {
    formatStatus(status) {
      const statusMap = {
        created: 'Application Created',
        contract_sent: 'Contract Sent',
        documents_uploaded: 'Documents Uploaded',
        documents_approved: 'Documents Approved',
        contract_signed: 'Contract Signed',
        application_sent: 'Contract Sent',
        contract_submitted: 'Contract Submitted',
        application_approved: 'Application Approved',
        approval_email_sent: 'Approval Sent',
        invoice_sent: 'Invoice Sent',
        invoice_paid: 'Payment Received',
        gateway_integrated: 'Integration Complete',
        account_live: 'Account Live',
      }
      return statusMap[status] || status
    },
    getStatusClass(status) {
      const classMap = {
        created: 'bg-slate-700/50 text-slate-300',
        contract_sent: 'bg-sky-900/50 text-sky-300',
        documents_uploaded: 'bg-purple-900/50 text-purple-300',
        documents_approved: 'bg-indigo-900/50 text-indigo-300',
        contract_signed: 'bg-blue-900/50 text-blue-300',
        application_sent: 'bg-yellow-900/50 text-yellow-300',
        contract_completed: 'bg-blue-900/50 text-blue-300',
        contract_submitted: 'bg-teal-900/50 text-teal-300',
        application_approved: 'bg-green-900/50 text-green-300',
        approval_email_sent: 'bg-purple-900/50 text-purple-300',
        invoice_sent: 'bg-orange-900/50 text-orange-300',
        invoice_paid: 'bg-cyan-900/50 text-cyan-300',
        gateway_integrated: 'bg-emerald-900/50 text-emerald-300',
        account_live: 'bg-green-600/50 text-green-200 font-bold',
      }
      return classMap[status] || 'bg-gray-700 text-gray-300'
    },
  },
}
</script>