<template>
  <div>
    <Head title="Dashboard" />
    
    <!-- Header -->
    <div class="mb-6">
      <h1 class="text-3xl font-bold text-white mb-2">Dashboard</h1>
    </div>

    <!-- Filters Section -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <div class="flex flex-row justify-between gap-6">
      <h2 class="text-xl font-bold text-white mb-4">Filters</h2>

      <!-- Filter Buttons -->
      <div class="flex gap-3 mb-4">
        <button
          @click="applyFilters"
          class="px-6 py-2 bg-gradient-to-r from-magenta-500 to-primary-500 hover:from-magenta-600 hover:to-primary-600 text-white rounded-lg transition-colors font-medium"
        >
          Apply Filters
        </button>
        <button
          @click="resetFilters"
          class="px-6 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors"
        >
          Reset
        </button>
      </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
        <!-- Date From -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">From Date</label>
          <input
            v-model="filterForm.date_from"
            type="date"
            class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 focus:outline-none focus:border-magenta-500"
          />
        </div>

        <!-- Date To -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">To Date</label>
          <input
            v-model="filterForm.date_to"
            type="date"
            class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 focus:outline-none focus:border-magenta-500"
          />
        </div>

        <!-- Account Filter (only for non-account users) -->
        <div v-if="!isAccount">
          <label class="block text-sm font-medium text-gray-300 mb-2">Account</label>
          <select
            v-model="filterForm.account_id"
            class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 focus:outline-none focus:border-magenta-500"
          >
            <option value="">All Accounts</option>
            <option v-for="account in availableAccounts" :key="account.id" :value="account.id">
              {{ account.name }}
            </option>
          </select>
        </div>

        <!-- Status Filter -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Status</label>
          <select
            v-model="filterForm.status"
            class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 focus:outline-none focus:border-magenta-500"
          >
            <option value="">All Statuses</option>
            <option v-for="(label, key) in availableStatuses" :key="key" :value="key">
              {{ label }}
            </option>
          </select>
        </div>

        <!-- Search -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Search</label>
          <input
            v-model="filterForm.search"
            type="text"
            placeholder="Application name..."
            class="w-full px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300 focus:outline-none focus:border-magenta-500"
          />
        </div>
      </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
      <div class="bg-gradient-to-br from-primary-600/20 to-magenta-600/20 backdrop-blur-sm rounded-xl p-4 border border-primary-700/30 hover:border-magenta-500/50 transition-all duration-300 hover:scale-105">
        <div class="text-3xl font-bold text-white mb-2">{{ metrics.totalApplications }}</div>
        <div class="text-magenta-400 font-semibold">Total Applications</div>
      </div>

      <div class="bg-gradient-to-br from-green-600/20 to-emerald-600/20 backdrop-blur-sm rounded-xl p-4 border border-green-700/30 hover:border-emerald-500/50 transition-all duration-300 hover:scale-105">
        <div class="text-3xl font-bold text-white mb-2">{{ metrics.totalAccounts }}</div>
        <div class="text-emerald-400 font-semibold">Total Accounts</div>
      </div>

      <div v-if="isAdmin" class="bg-gradient-to-br from-blue-600/20 to-cyan-600/20 backdrop-blur-sm rounded-xl p-4 border border-blue-700/30 hover:border-cyan-500/50 transition-all duration-300 hover:scale-105">
        <div class="text-3xl font-bold text-white mb-2">{{ metrics.totalUsers }}</div>
        <div class="text-cyan-400 font-semibold">Total Users</div>
      </div>

      <div class="bg-gradient-to-br from-purple-600/20 to-pink-600/20 backdrop-blur-sm rounded-xl p-4 border border-purple-700/30 hover:border-pink-500/50 transition-all duration-300 hover:scale-105">
        <div class="text-3xl font-bold text-white mb-2">£{{ metrics.totalSetupFees.toFixed(2) }}</div>
        <div class="text-pink-400 font-semibold">Total Setup Fees</div>
        <div class="text-sm text-gray-400 mt-1">Avg: £{{ metrics.avgSetupFee.toFixed(2) }}</div>
      </div>
    </div>

    <!-- Charts Row: Status Progress (Radial) + Top Accounts (Bar) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
      <!-- Status Progress - Multi-Ring Radial Chart -->
      <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
        <h2 class="text-xl font-bold text-white mb-4">Application Progress by Status</h2>
        <p class="text-sm text-gray-400 mb-4">Shows applications that have reached each stage</p>
        <apexchart
          v-if="statusChartSeries.length > 0"
          type="radialBar"
          :options="statusChartOptions"
          :series="statusChartSeries"
          height="500"
        />
        <div v-else class="text-gray-400 text-center py-8">No data available</div>
      </div>

      <!-- Top Accounts - Bar Chart -->
      <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
        <h2 class="text-xl font-bold text-white mb-4">Top Accounts by Total Setup Fees</h2>
        <apexchart
          v-if="charts.topAccounts.length > 0"
          type="bar"
          :options="barChartOptions"
          :series="barChartSeries"
          height="500"
        />
        <div v-else class="text-gray-400 text-center py-8">No data available</div>
      </div>
    </div>

    <!-- Applications Over Time - Line Chart (Full Width) -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <h2 class="text-xl font-bold text-white mb-4">Applications Created Over Time</h2>
      <apexchart
        v-if="charts.applicationsOverTime.length > 0"
        type="line"
        :options="lineChartOptions"
        :series="lineChartSeries"
        height="350"
      />
      <div v-else class="text-gray-400 text-center py-8">No data available</div>
    </div>

    <!-- Recent Applications Table -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-primary-800/30 shadow-2xl overflow-hidden">
      <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
        <h2 class="text-xl font-bold text-magenta-400">Recent Applications</h2>
      </div>
      <div v-if="recentApplications.length > 0" class="overflow-x-auto">
        <table class="w-full">
          <thead class="bg-dark-900/50">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Name</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Account</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Status</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Created</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-primary-800/20">
            <tr
              v-for="app in recentApplications"
              :key="app.id"
              class="hover:bg-primary-900/20 transition-colors"
            >
              <td class="px-6 py-4 text-white font-medium">{{ app.name }}</td>
              <td class="px-6 py-4 text-gray-300">{{ app.account_name }}</td>
              <td class="px-6 py-4">
                <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="getStatusClass(app.status)">
                  {{ formatStatus(app.status) }}
                </span>
              </td>
              <td class="px-6 py-4 text-gray-400">{{ app.created_at }}</td>
              <td class="px-6 py-4">
                <Link
                  :href="`/applications/${app.id}/status`"
                  class="text-magenta-400 hover:text-magenta-300 font-medium"
                >
                  View
                </Link>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-else class="px-6 py-8 text-gray-400 text-center">
        No applications found
      </div>
    </div>
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import VueApexCharts from 'vue3-apexcharts'

export default {
  components: {
    Head,
    Link,
    apexchart: VueApexCharts,
  },
  layout: Layout,
  props: {
    filters: Object,
    isAccount: Boolean,
    isAdmin: Boolean,
    availableAccounts: Array,
    availableStatuses: Object,
    metrics: Object,
    charts: Object,
    recentApplications: Array,
  },
  data() {
    return {
      filterForm: {
        date_from: this.filters.date_from,
        date_to: this.filters.date_to,
        account_id: this.filters.account_id || '',
        status: this.filters.status || '',
        search: this.filters.search || '',
      },
    }
  },
  computed: {
    // Multi-Ring Status Radial Chart
    statusChartSeries() {
      const counts = this.charts.statusCounts
      const total = this.metrics.totalApplications
      if (total === 0) return []
      
      // All 11 statuses
      const statuses = [
        'created',
        'fees_confirmed',
        'documents_uploaded',
        'application_sent',
        'contract_completed',
        'contract_submitted',
        'application_approved',
        'invoice_sent',
        'invoice_paid',
        'gateway_integrated',
        'account_live',
      ]
      
      return statuses.map(status => {
        const count = counts[status] || 0
        return Math.round((count / total) * 100)
      })
    },
    statusChartOptions() {
      return {
        chart: {
          type: 'radialBar',
          background: 'transparent',
        },
        plotOptions: {
          radialBar: {
            offsetY: 0,
            startAngle: 0,
            endAngle: 270,
            hollow: {
              margin: 5,
              size: '30%',
              background: 'transparent',
            },
            dataLabels: {
              name: {
                show: false,
              },
              value: {
                show: false,
              },
            },
            track: {
              background: '#1f2937',
              strokeWidth: '97%',
              margin: 5,
            },
          },
        },
        colors: [
          '#6366f1', // Created - Indigo
          '#3b82f6', // Fees Confirmed - Blue
          '#0ea5e9', // Documents Uploaded - Sky
          '#06b6d4', // Contract Sent - Cyan
          '#14b8a6', // Contract Completed - Teal
          '#10b981', // Contract Submitted - Emerald
          '#22c55e', // Application Approved - Green
          '#84cc16', // Invoice Sent - Lime
          '#eab308', // Invoice Paid - Yellow
          '#f59e0b', // Gateway Integrated - Amber
          '#f0abfc', // Account Live - Magenta
        ],
        labels: [
          'Created',
          'Fees Confirmed',
          'Documents Uploaded',
          'Contract Sent',
          'Contract Signed',
          'Contract Submitted',
          'Approved',
          'Invoice Sent',
          'Invoice Paid',
          'Gateway Integrated',
          'Account Live',
        ],
        legend: {
          show: true,
          floating: true,
          fontSize: '12px',
          position: 'left',
          offsetX: 0,
          offsetY: 10,
          labels: {
            useSeriesColors: true,
          },
          markers: {
            size: 0,
          },
          formatter: (seriesName, opts) => {
            return seriesName + ":  " + opts.w.globals.series[opts.seriesIndex] + "%"
          },
          itemMargin: {
            vertical: 2,
          },
        },
        responsive: [
          {
            breakpoint: 480,
            options: {
              legend: {
                show: false,
              },
            },
          },
        ],
        theme: {
          mode: 'dark',
        },
      }
    },

    // Line Chart
    lineChartSeries() {
      return [{
        name: 'Applications Created',
        data: this.charts.applicationsOverTime.map(item => ({
          x: item.date,
          y: item.count,
        })),
      }]
    },
    lineChartOptions() {
      return {
        chart: {
          type: 'line',
          background: 'transparent',
          toolbar: {
            show: true,
          },
        },
        stroke: {
          curve: 'smooth',
          width: 3,
        },
        colors: ['#f0abfc'],
        xaxis: {
          type: 'datetime',
          labels: {
            style: {
              colors: '#9ca3af',
            },
          },
        },
        yaxis: {
          labels: {
            style: {
              colors: '#9ca3af',
            },
          },
        },
        grid: {
          borderColor: '#374151',
        },
        theme: {
          mode: 'dark',
        },
        tooltip: {
          theme: 'dark',
        },
      }
    },

    // Bar Chart
    // Bar Chart
    barChartSeries() {
      return [{
        name: 'Total Setup Fees (£)',
        data: this.charts.topAccounts.map(item => item.total_fees),
      }]
    },
    barChartOptions() {
      return {
        chart: {
          type: 'bar',
          background: 'transparent',
          toolbar: {
            show: false,
          },
        },
        plotOptions: {
          bar: {
            horizontal: true,
            borderRadius: 8,
            dataLabels: {
              position: 'top',
            },
          },
        },
        colors: ['#412d6b'],
        dataLabels: {
          enabled: true,
          formatter: function (val) {
            return '£' + val.toFixed(2)
          },
          offsetX: 30,
          style: {
            fontSize: '12px',
            colors: ['#fff']
          }
        },
        xaxis: {
          categories: this.charts.topAccounts.map(item => item.name),
          labels: {
            formatter: function (val) {
              return '£' + val.toFixed(2)
            },
            style: {
              colors: '#9ca3af',
            },
          },
        },
        yaxis: {
          labels: {
            style: {
              colors: '#9ca3af',
            },
          },
        },
        grid: {
          borderColor: '#374151',
        },
        theme: {
          mode: 'dark',
        },
        tooltip: {
          theme: 'dark',
          y: {
            formatter: function (val) {
              return '£' + val.toFixed(2)
            }
          }
        },
      }
    },
  },
  methods: {
    applyFilters() {
      this.$inertia.get('/dashboard', this.filterForm, {
        preserveState: true,
      })
    },
    resetFilters() {
      this.filterForm = {
        date_from: '',
        date_to: '',
        account_id: '',
        status: '',
        search: '',
      }
      this.applyFilters()
    },
    formatStatus(status) {
      if (!status) return 'Created'
      return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
    },
    getStatusClass(status) {
      const classes = {
        created: 'bg-indigo-900/50 text-indigo-300',
        fees_confirmed: 'bg-blue-900/50 text-blue-300',
        documents_uploaded: 'bg-sky-900/50 text-sky-300',
        application_sent: 'bg-cyan-900/50 text-cyan-300',
        contract_completed: 'bg-teal-900/50 text-teal-300',
        contract_submitted: 'bg-emerald-900/50 text-emerald-300',
        application_approved: 'bg-green-900/50 text-green-300',
        invoice_sent: 'bg-lime-900/50 text-lime-300',
        invoice_paid: 'bg-yellow-900/50 text-yellow-300',
        gateway_integrated: 'bg-amber-900/50 text-amber-300',
        account_live: 'bg-magenta-900/50 text-magenta-300',
      }
      return classes[status] || 'bg-gray-700 text-gray-300'
    },
  },
}
</script>