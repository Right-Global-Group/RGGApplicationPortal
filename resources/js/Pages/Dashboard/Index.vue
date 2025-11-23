<template>
  <div>
    <Head title="Dashboard" />
    
    <!-- Header -->
    <div class="mb-6 flex justify-between">
      <div>
        <h1 class="text-3xl font-bold text-white">Dashboard</h1>
      </div>
    </div>

    <!-- Global Filters Section -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl mb-6">
      <div class="flex flex-row justify-between items-center gap-6">
        <h2 class="text-xl font-bold text-white">Global Filters</h2>

        <!-- Filter Buttons -->
        <div class="flex gap-3">
          <button
            @click="applyFilters"
            class="px-6 py-2 bg-gradient-to-r from-magenta-500 to-primary-500 hover:from-magenta-600 hover:to-primary-600 text-white rounded-lg transition-colors font-medium shadow-lg"
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
      
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mt-4">
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

    <!-- Tabs Navigation -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-primary-800/30 shadow-2xl mb-6 overflow-hidden">
      <div class="flex overflow-x-auto scrollbar-thin scrollbar-thumb-primary-600 scrollbar-track-dark-900">
        <button
          v-for="tab in filteredTabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          class="flex-shrink-0 px-8 py-2 text-center font-semibold transition-all duration-200 border-b-2"
          :class="activeTab === tab.id 
            ? 'bg-gradient-to-r from-purple-600 to-primary-600 text-white border-purple-400' 
            : 'text-gray-400 hover:text-white hover:bg-primary-900/30 border-transparent'"
        >
          <div class="flex items-center gap-2">
            <component :is="tab.icon" class="w-5 h-5" />
            <span>{{ tab.label }}</span>
          </div>
        </button>
      </div>
    </div>

    <!-- Tab Content -->
    
    <!-- ALL TAB -->
    <div v-show="activeTab === 'all'">

      <!-- Status Progress + Stats -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h2 class="text-xl font-bold text-white mb-2">Application Progress by Status</h2>
          <p class="text-sm text-gray-400 mb-1">Shows applications that have reached each stage</p>
          <apexchart
            v-if="statusChartSeries.length > 0"
            type="radialBar"
            :options="statusChartOptions"
            :series="statusChartSeries"
            height="450"
          />
          <div v-else class="text-gray-400 text-center py-8">No data available</div>
        </div>
        
        <!-- Status Stats -->
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h3 class="text-lg font-bold text-white mb-6">Pipeline Metrics</h3>
          <div class="space-y-3">
            <div class="p-3 bg-gradient-to-br from-green-600/20 to-emerald-600/20 rounded-lg border border-green-700/30">
              <div class="text-xs text-gray-400 mb-1">Completion Rate</div>
              <div class="text-2xl font-bold text-white">{{ completionRate }}%</div>
              <div class="text-xs text-emerald-300 mt-1">To Live Account</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-blue-600/20 to-cyan-600/20 rounded-lg border border-blue-700/30">
              <div class="text-xs text-gray-400 mb-1">Approval Rate</div>
              <div class="text-2xl font-bold text-white">{{ approvalRate }}%</div>
              <div class="text-xs text-cyan-300 mt-1">Applications Approved</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-yellow-600/20 to-orange-600/20 rounded-lg border border-yellow-700/30">
              <div class="text-xs text-gray-400 mb-1">Contract Rate</div>
              <div class="text-2xl font-bold text-white">{{ contractRate }}%</div>
              <div class="text-xs text-yellow-300 mt-1">Contracts Signed</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-purple-600/20 to-pink-600/20 rounded-lg border border-purple-700/30">
              <div class="text-xs text-gray-400 mb-1">Invoice Payment</div>
              <div class="text-2xl font-bold text-white">{{ invoicePaymentRate }}%</div>
              <div class="text-xs text-pink-300 mt-1">Invoices Paid</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Top Accounts + Stats -->
      <div v-if="!isAccount" class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h2 class="text-xl font-bold text-white mb-6">Top Accounts by Scaling Fees</h2>
          <apexchart
            v-if="charts.topAccounts.length > 0"
            type="bar"
            :options="barChartOptions"
            :series="barChartSeries"
            height="400"
          />
          <div v-else class="text-gray-400 text-center py-8">No data available</div>
        </div>
        
        <!-- Account Stats -->
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h3 class="text-lg font-bold text-white mb-6">Account Metrics</h3>
          <div class="space-y-3">
            <div class="p-3 bg-gradient-to-br from-green-600/20 to-emerald-600/20 rounded-lg border border-green-700/30">
              <div class="text-xs text-gray-400 mb-1">Active Accounts</div>
              <div class="text-2xl font-bold text-white">{{ charts.topAccounts.length }}</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-purple-600/20 to-pink-600/20 rounded-lg border border-purple-700/30">
              <div class="text-xs text-gray-400 mb-1">Highest Value</div>
              <div class="text-2xl font-bold text-white">£{{ highestAccountFee }}</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-blue-600/20 to-cyan-600/20 rounded-lg border border-blue-700/30">
              <div class="text-xs text-gray-400 mb-1">Avg Apps/Account</div>
              <div class="text-2xl font-bold text-white">{{ avgAppsPerAccount }}</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-indigo-600/20 to-violet-600/20 rounded-lg border border-indigo-700/30">
              <div class="text-xs text-gray-400 mb-1">Avg Revenue/Account</div>
              <div class="text-2xl font-bold text-white">£{{ avgRevenuePerAccount }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Applications Over Time + Stats -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h2 class="text-xl font-bold text-white mb-4">Applications Created Over Time</h2>
          <apexchart
            v-if="charts.applicationsOverTime.length > 0"
            type="area"
            :options="areaChartOptions"
            :series="areaChartSeries"
            height="300"
          />
          <div v-else class="text-gray-400 text-center py-8">No data available</div>
        </div>
        
        <!-- Time Stats -->
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h3 class="text-lg font-bold text-white mb-4">Timeline Metrics</h3>
          <div class="space-y-3">
            <div class="p-3 bg-gradient-to-br from-magenta-600/20 to-primary-600/20 rounded-lg border border-magenta-700/30">
              <div class="text-xs text-gray-400 mb-1">Total in Period</div>
              <div class="text-2xl font-bold text-white">{{ totalInPeriod }}</div>
              <div class="text-xs text-magenta-300 mt-1">Applications</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-yellow-600/20 to-orange-600/20 rounded-lg border border-yellow-700/30">
              <div class="text-xs text-gray-400 mb-1">Peak Day</div>
              <div class="text-2xl font-bold text-white">{{ peakDay }}</div>
              <div class="text-xs text-yellow-300 mt-1">Maximum in one day</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-indigo-600/20 to-violet-600/20 rounded-lg border border-indigo-700/30">
              <div class="text-xs text-gray-400 mb-1">Daily Average</div>
              <div class="text-2xl font-bold text-white">{{ avgPerDay }}</div>
              <div class="text-xs text-indigo-300 mt-1">Apps per day</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-teal-600/20 to-emerald-600/20 rounded-lg border border-teal-700/30">
              <div class="text-xs text-gray-400 mb-1">Last Application Created</div>
              <div class="text-2xl font-bold text-white">{{ lastAppCreated }}</div>
            </div>
          </div>
        </div>
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
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Scaling Fee</th>
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
                <td class="px-6 py-4 text-gray-300">£{{ app.scaling_fee }}</td>
                <td class="px-6 py-4 text-gray-400 text-sm">{{ app.created_at }}</td>
                <td class="px-6 py-4">
                  <Link
                    :href="`/applications/${app.id}/status`"
                    class="text-magenta-400 hover:text-magenta-300 font-medium"
                  >
                    View →
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

    <!-- OVERVIEW TAB -->
    <div v-show="activeTab === 'overview'">
      <!-- Key Metrics Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-4">
        <div class="bg-gradient-to-br from-primary-600/20 to-magenta-600/20 backdrop-blur-sm rounded-xl p-4 border border-primary-700/30 hover:border-magenta-500/50 transition-all duration-300">
          <div class="text-3xl font-bold text-white mb-2">{{ metrics.totalApplications }}</div>
          <div class="text-magenta-400 font-semibold">Total Applications</div>
        </div>

        <div class="bg-gradient-to-br from-green-600/20 to-emerald-600/20 backdrop-blur-sm rounded-xl p-4 border border-green-700/30 hover:border-emerald-500/50 transition-all duration-300">
          <div class="text-3xl font-bold text-white mb-2">{{ metrics.totalAccounts }}</div>
          <div class="text-emerald-400 font-semibold">Total Accounts</div>
        </div>

        <div v-if="isAdmin" class="bg-gradient-to-br from-blue-600/20 to-cyan-600/20 backdrop-blur-sm rounded-xl p-4 border border-blue-700/30 hover:border-cyan-500/50 transition-all duration-300">
          <div class="text-3xl font-bold text-white mb-2">{{ metrics.totalUsers }}</div>
          <div class="text-cyan-400 font-semibold">Total Users</div>
        </div>

        <div class="bg-gradient-to-br from-purple-600/20 to-pink-600/20 backdrop-blur-sm rounded-xl p-4 border border-purple-700/30 hover:border-pink-500/50 transition-all duration-300">
          <div class="text-3xl font-bold text-white mb-2">£{{ metrics.totalSetupFees.toFixed(2) }}</div>
          <div class="text-pink-400 font-semibold">Total Scaling Fees</div>
          <div class="text-sm text-gray-400 mt-1">Avg: £{{ metrics.avgSetupFee.toFixed(2) }}</div>
        </div>
      </div>

      <!-- Applications Over Time -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-4">
        <div class="lg:col-span-2 bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h2 class="text-xl font-bold text-white mb-4">Applications Created Over Time</h2>
          <apexchart
            v-if="charts.applicationsOverTime.length > 0"
            type="area"
            :options="areaChartOptions"
            :series="areaChartSeries"
            height="300"
          />
          <div v-else class="text-gray-400 text-center py-8">No data available</div>
        </div>
        
        <!-- Time Stats -->
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h3 class="text-lg font-bold text-white mb-4">Timeline Metrics</h3>
          <div class="space-y-3">
            <div class="p-3 bg-gradient-to-br from-magenta-600/20 to-primary-600/20 rounded-lg border border-magenta-700/30">
              <div class="text-xs text-gray-400 mb-1">Total in Period</div>
              <div class="text-2xl font-bold text-white">{{ totalInPeriod }}</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-yellow-600/20 to-orange-600/20 rounded-lg border border-yellow-700/30">
              <div class="text-xs text-gray-400 mb-1">Peak Day</div>
              <div class="text-2xl font-bold text-white">{{ peakDay }}</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-indigo-600/20 to-violet-600/20 rounded-lg border border-indigo-700/30">
              <div class="text-xs text-gray-400 mb-1">Daily Average</div>
              <div class="text-2xl font-bold text-white">{{ avgPerDay }}</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-teal-600/20 to-emerald-600/20 rounded-lg border border-teal-700/30">
              <div class="text-xs text-gray-400 mb-1">Last Application Created</div>
              <div class="text-2xl font-bold text-white">{{ lastAppCreated }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Status Distribution Donut -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h2 class="text-xl font-bold text-white mb-4">Current Status Distribution</h2>
          <apexchart
            v-if="donutChartSeries.length > 0"
            type="donut"
            :options="donutChartOptions"
            :series="donutChartSeries"
            height="350"
          />
          <div v-else class="text-gray-400 text-center py-8">No data available</div>
        </div>

        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h2 class="text-xl font-bold text-white mb-4">Application Velocity</h2>
          <apexchart
            v-if="charts.applicationsOverTime.length > 0"
            type="line"
            :options="velocityChartOptions"
            :series="velocityChartSeries"
            height="350"
          />
          <div v-else class="text-gray-400 text-center py-8">No data available</div>
        </div>
      </div>
    </div>

    <!-- STATUS TAB -->
    <div v-show="activeTab === 'status'">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h2 class="text-xl font-bold text-white mb-2">Application Progress by Status</h2>
          <p class="text-sm text-gray-400 mb-1">Shows applications that have reached each stage</p>
          <apexchart
            v-if="statusChartSeries.length > 0"
            type="radialBar"
            :options="statusChartOptions"
            :series="statusChartSeries"
            height="450"
          />
          <div v-else class="text-gray-400 text-center py-8">No data available</div>
        </div>
        
        <!-- Status Stats -->
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h3 class="text-lg font-bold text-white mb-4">Status Breakdown</h3>
          <div class="space-y-2 max-h-[450px] overflow-y-auto pr-2 scrollbar-thin scrollbar-thumb-primary-600 scrollbar-track-dark-900">
            <div v-for="(count, status) in charts.statusCounts" :key="status" class="flex items-center justify-between p-3 bg-dark-900/50 rounded-lg hover:bg-primary-900/20 transition-colors">
              <span class="text-sm text-gray-300">{{ formatStatus(status) }}</span>
              <span class="font-bold text-white">{{ count }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Status Distribution -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h2 class="text-xl font-bold text-white mb-4">Status Distribution (Donut)</h2>
          <apexchart
            v-if="donutChartSeries.length > 0"
            type="donut"
            :options="donutChartOptions"
            :series="donutChartSeries"
            height="350"
          />
          <div v-else class="text-gray-400 text-center py-8">No data available</div>
        </div>

        <!-- Completion Rates -->
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h3 class="text-lg font-bold text-white mb-4">Completion Rates</h3>
          <apexchart
            type="bar"
            :options="completionRatesOptions"
            :series="completionRatesSeries"
            height="350"
          />
        </div>
      </div>

      <!-- Status Funnel -->
      <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
        <h2 class="text-xl font-bold text-white mb-4">Application Funnel</h2>
        <apexchart
          v-if="funnelChartSeries.length > 0"
          type="bar"
          :options="funnelChartOptions"
          :series="funnelChartSeries"
          height="400"
        />
        <div v-else class="text-gray-400 text-center py-8">No data available</div>
      </div>
    </div>

    <!-- ACCOUNTS TAB -->
    <div v-show="activeTab === 'accounts' && !isAccount">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h2 class="text-xl font-bold text-white mb-4">Top Accounts by Scaling fees</h2>
          <apexchart
            v-if="charts.topAccounts.length > 0"
            type="bar"
            :options="barChartOptions"
            :series="barChartSeries"
            height="400"
          />
          <div v-else class="text-gray-400 text-center py-8">No data available</div>
        </div>
        
        <!-- Account Stats -->
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h3 class="text-lg font-bold text-white mb-4">Account Metrics</h3>
          <div class="space-y-3">
            <div class="p-3 bg-gradient-to-br from-green-600/20 to-emerald-600/20 rounded-lg border border-green-700/30">
              <div class="text-xs text-gray-400 mb-1">Total Accounts</div>
              <div class="text-2xl font-bold text-white">{{ metrics.totalAccounts }}</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-purple-600/20 to-pink-600/20 rounded-lg border border-purple-700/30">
              <div class="text-xs text-gray-400 mb-1">Highest Value</div>
              <div class="text-2xl font-bold text-white">£{{ highestAccountFee }}</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-blue-600/20 to-cyan-600/20 rounded-lg border border-blue-700/30">
              <div class="text-xs text-gray-400 mb-1">Avg Apps/Account</div>
              <div class="text-2xl font-bold text-white">{{ avgAppsPerAccount }}</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-indigo-600/20 to-violet-600/20 rounded-lg border border-indigo-700/30">
              <div class="text-xs text-gray-400 mb-1">Avg Revenue</div>
              <div class="text-2xl font-bold text-white">£{{ avgRevenuePerAccount }}</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Applications per Account -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h2 class="text-xl font-bold text-white mb-4">Applications per Account</h2>
          <apexchart
            v-if="charts.topAccounts.length > 0"
            type="bar"
            :options="appsPerAccountChartOptions"
            :series="appsPerAccountSeries"
            height="350"
          />
          <div v-else class="text-gray-400 text-center py-8">No data available</div>
        </div>

        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h2 class="text-xl font-bold text-white mb-4">Revenue Distribution</h2>
          <apexchart
            v-if="charts.topAccounts.length > 0"
            type="pie"
            :options="revenuePieOptions"
            :series="revenuePieSeries"
            height="350"
          />
          <div v-else class="text-gray-400 text-center py-8">No data available</div>
        </div>
      </div>
    </div>

    <!-- FINANCIAL TAB -->
    <div v-show="activeTab === 'financial'">
      <!-- Financial Metrics -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-gradient-to-br from-green-600/20 to-emerald-600/20 backdrop-blur-sm rounded-xl p-6 border border-green-700/30">
          <div class="text-3xl font-bold text-white mb-2">£{{ metrics.totalSetupFees.toFixed(2) }}</div>
          <div class="text-emerald-400 font-semibold mb-2">Total Scaling fees</div>
          <div class="text-sm text-gray-400">Total revenue</div>
        </div>

        <div class="bg-gradient-to-br from-blue-600/20 to-cyan-600/20 backdrop-blur-sm rounded-xl p-6 border border-blue-700/30">
          <div class="text-3xl font-bold text-white mb-2">£{{ metrics.avgSetupFee.toFixed(2) }}</div>
          <div class="text-cyan-400 font-semibold mb-2">Average Fee</div>
          <div class="text-sm text-gray-400">Per application</div>
        </div>

        <div class="bg-gradient-to-br from-purple-600/20 to-pink-600/20 backdrop-blur-sm rounded-xl p-6 border border-purple-700/30">
          <div class="text-3xl font-bold text-white mb-2">£{{ highestAccountFee }}</div>
          <div class="text-pink-400 font-semibold mb-2">Highest Value</div>
          <div class="text-sm text-gray-400">Top account</div>
        </div>

        <div class="bg-gradient-to-br from-yellow-600/20 to-orange-600/20 backdrop-blur-sm rounded-xl p-6 border border-yellow-700/30">
          <div class="text-3xl font-bold text-white mb-2">£{{ projectedRevenue }}</div>
          <div class="text-yellow-400 font-semibold mb-2">Projected</div>
          <div class="text-sm text-gray-400">+20% growth</div>
        </div>
      </div>

      <!-- Revenue Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h2 class="text-xl font-bold text-white mb-4">Revenue by Account</h2>
          <apexchart
            v-if="charts.topAccounts.length > 0"
            type="bar"
            :options="revenueChartOptions"
            :series="revenueChartSeries"
            height="350"
          />
          <div v-else class="text-gray-400 text-center py-8">No data available</div>
        </div>

        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h2 class="text-xl font-bold text-white mb-4">Revenue Share</h2>
          <apexchart
            v-if="charts.topAccounts.length > 0"
            type="pie"
            :options="revenuePieOptions"
            :series="revenuePieSeries"
            height="350"
          />
          <div v-else class="text-gray-400 text-center py-8">No data available</div>
        </div>
      </div>

      <!-- Financial Breakdown Table -->
      <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-primary-800/30 shadow-2xl overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
          <h2 class="text-xl font-bold text-magenta-400">Financial Breakdown by Account</h2>
        </div>
        <div v-if="charts.topAccounts.length > 0" class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-dark-900/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Account</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Applications</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Total Fees</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Avg Fee</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">% of Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-primary-800/20">
              <tr
                v-for="account in charts.topAccounts"
                :key="account.name"
                class="hover:bg-primary-900/20 transition-colors"
              >
                <td class="px-6 py-4 text-white font-medium">{{ account.name }}</td>
                <td class="px-6 py-4 text-gray-300">{{ account.app_count }}</td>
                <td class="px-6 py-4 text-emerald-400 font-semibold">£{{ account.total_fees.toFixed(2) }}</td>
                <td class="px-6 py-4 text-gray-300">£{{ (account.total_fees / account.app_count).toFixed(2) }}</td>
                <td class="px-6 py-4 text-magenta-400">{{ ((account.total_fees / metrics.totalSetupFees) * 100).toFixed(1) }}%</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div v-else class="px-6 py-8 text-gray-400 text-center">
          No financial data available
        </div>
      </div>
    </div>

    <!-- USERS TAB (Admin only) -->
    <div v-show="activeTab === 'users' && !isAccount">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">

      <!-- Total Users -->
      <div class="bg-gradient-to-br from-blue-600/20 to-cyan-600/20 backdrop-blur-sm rounded-xl p-4 border border-blue-700/30">
        <div class="flex items-center justify-between h-full">
          <div>
            <div class="text-cyan-400 font-semibold">Total Users</div>
            <div class="text-sm text-gray-400">Platform users</div>
          </div>
          <div class="text-3xl font-bold text-white text-right ml-4">
            {{ metrics.totalUsers }}
          </div>
        </div>
      </div>

      <!-- Total Accounts -->
      <div class="bg-gradient-to-br from-purple-600/20 to-pink-600/20 backdrop-blur-sm rounded-xl p-4 border border-purple-700/30">
        <div class="flex items-center justify-between h-full">
          <div>
            <div class="text-pink-400 font-semibold">Total Accounts</div>
            <div class="text-sm text-gray-400">Managed accounts</div>
          </div>
          <div class="text-3xl font-bold text-white text-right ml-4">
            {{ metrics.totalAccounts }}
          </div>
        </div>
      </div>

      <!-- Avg Accounts/User -->
      <div class="bg-gradient-to-br from-green-600/20 to-emerald-600/20 backdrop-blur-sm rounded-xl p-4 border border-green-700/30">
        <div class="flex items-center justify-between h-full">
          <div>
            <div class="text-emerald-400 font-semibold">Avg Accounts/User</div>
            <div class="text-sm text-gray-400">Distribution</div>
          </div>
          <div class="text-3xl font-bold text-white text-right ml-4">
            {{ avgAccountsPerUser }}
          </div>
        </div>
      </div>

      </div>

      <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
        <h2 class="text-xl font-bold text-white mb-4">User & Account Relationship</h2>
        <apexchart
          type="scatter"
          :options="userScatterOptions"
          :series="userScatterSeries"
          height="400"
        />
      </div>
    </div>


    <!-- STATUS MONITORING TAB -->
    <div v-show="activeTab === 'monitoring'">
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h2 class="text-xl font-bold text-white mb-4">Average Days in Each Status</h2>
          <apexchart
            type="bar"
            :options="daysInStatusOptions"
            :series="daysInStatusSeries"
            height="400"
          />
        </div>

        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 border border-primary-800/30 shadow-2xl">
          <h3 class="text-lg font-bold text-white mb-4">Processing Metrics</h3>
          <div class="space-y-3">
            <div class="p-3 bg-gradient-to-br from-green-600/20 to-emerald-600/20 rounded-lg border border-green-700/30">
              <div class="text-xs text-gray-400 mb-1">Fastest Processing</div>
              <div class="text-xl font-bold text-white">{{ Number(processingStats.fastest).toFixed(5) }} days</div>
              <div class="text-xs text-emerald-300 mt-1">Best time</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-blue-600/20 to-cyan-600/20 rounded-lg border border-blue-700/30">
              <div class="text-xs text-gray-400 mb-1">Median Processing</div>
              <div class="text-xl font-bold text-white">{{ Number(processingStats.median).toFixed(5) }} days</div>
              <div class="text-xs text-cyan-300 mt-1">Typical time</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-yellow-600/20 to-orange-600/20 rounded-lg border border-yellow-700/30">
              <div class="text-xs text-gray-400 mb-1">Average Processing</div>
              <div class="text-xl font-bold text-white">{{ Number(processingStats.average).toFixed(5) }} days</div>
              <div class="text-xs text-yellow-300 mt-1">Avg time</div>
            </div>
            <div class="p-3 bg-gradient-to-br from-red-600/20 to-pink-600/20 rounded-lg border border-red-700/30">
              <div class="text-xs text-gray-400 mb-1">Slowest Processing</div>
              <div class="text-xl font-bold text-white">{{ Number(processingStats.slowest).toFixed(5) }} days</div>
              <div class="text-xs text-red-300 mt-1">Needs attention</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Processing Summary Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-gradient-to-br from-green-600/20 to-emerald-600/20 backdrop-blur-sm rounded-xl p-6 border border-green-700/30">
          <div class="text-3xl font-bold text-white mb-2">{{ processingStats.total_completed }}</div>
          <div class="text-emerald-400 font-semibold mb-2">Completed Applications</div>
          <div class="text-sm text-gray-400">Reached account live status</div>
        </div>

        <div class="bg-gradient-to-br from-blue-600/20 to-cyan-600/20 backdrop-blur-sm rounded-xl p-6 border border-blue-700/30">
          <div class="text-3xl font-bold text-white mb-2">{{ processingStats.total_in_progress }}</div>
          <div class="text-cyan-400 font-semibold mb-2">In Progress</div>
          <div class="text-sm text-gray-400">Currently being processed</div>
        </div>
      </div>

      <!-- Application Processing Times Table -->
      <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl border border-primary-800/30 shadow-2xl overflow-hidden">
        <div class="px-6 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
          <h2 class="text-xl font-bold text-magenta-400">Application Processing Times</h2>
          <p class="text-sm text-gray-400 mt-1">Time from creation to current status (sorted by fastest first)</p>
        </div>
        <div v-if="processingApplications.data.length > 0" class="overflow-x-auto">
          <table class="w-full">
            <thead class="bg-dark-900/50">
              <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Application</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Account</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Current Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Created</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Days in Process</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Speed</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-primary-800/20">
              <tr
                v-for="app in processingApplications.data"
                :key="app.id"
                class="hover:bg-primary-900/20 transition-colors"
              >
                <td class="px-6 py-4 text-white font-medium">{{ app.name }}</td>
                <td class="px-6 py-4 text-gray-300">{{ app.account_name }}</td>
                <td class="px-6 py-4">
                  <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="getStatusClass(app.current_step)">
                    {{ formatStatus(app.current_step) }}
                  </span>
                </td>
                <td class="px-6 py-4 text-gray-400 text-sm">{{ app.created_at }}</td>
                <td class="px-6 py-4">
                  <span class="font-bold text-white">{{ Number(app.days_in_process).toFixed(5) }}</span>
                  <span class="text-gray-400 text-sm ml-1">days</span>
                </td>
                <td class="px-6 py-4">
                  <span 
                    class="px-3 py-1 rounded-full text-xs font-semibold"
                    :class="getSpeedClass(app.days_in_process)"
                  >
                    {{ getSpeedLabel(app.days_in_process) }}
                  </span>
                </td>
                <td class="px-6 py-4">
                  <Link
                    :href="`/applications/${app.id}/status`"
                    class="text-magenta-400 hover:text-magenta-300 font-medium"
                  >
                    View →
                  </Link>
                </td>
              </tr>
            </tbody>
          </table>

          <!-- Pagination -->
          <div v-if="processingApplications.last_page > 1" class="px-6 py-4 bg-dark-900/50 border-t border-primary-800/30">
            <div class="flex items-center justify-between">
              <div class="text-sm text-gray-400">
                Showing {{ processingApplications.from }} to {{ processingApplications.to }} of {{ processingApplications.total }} applications
              </div>
              
              <div class="flex gap-2">
                <button
                  @click="changeProcessingPage(processingApplications.current_page - 1)"
                  :disabled="processingApplications.current_page === 1"
                  class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Previous
                </button>
                
                <div class="flex gap-1">
                  <button
                    v-for="page in paginationPages"
                    :key="page"
                    @click="changeProcessingPage(page)"
                    class="px-4 py-2 rounded-lg transition-colors"
                    :class="page === processingApplications.current_page 
                      ? 'bg-magenta-500 text-white font-bold' 
                      : 'bg-gray-700 hover:bg-gray-600 text-white'"
                  >
                    {{ page }}
                  </button>
                </div>
                
                <button
                  @click="changeProcessingPage(processingApplications.current_page + 1)"
                  :disabled="processingApplications.current_page === processingApplications.last_page"
                  class="px-4 py-2 bg-gray-700 hover:bg-gray-600 text-white rounded-lg transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  Next
                </button>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="px-6 py-8 text-gray-400 text-center">
          No application processing data available
        </div>
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
    processingStats: Object,
    processingApplications: Object,
  },
  data() {
    return {
      activeTab: 'all',
      tabs: [
        { id: 'all', label: 'All', icon: 'div' },
        { id: 'overview', label: 'Overview', icon: 'div' },
        { id: 'status', label: 'Status', icon: 'div' },
        { id: 'accounts', label: 'Accounts', icon: 'div' },
        { id: 'financial', label: 'Financial', icon: 'div' },
        { id: 'users', label: 'Users', icon: 'div' },
        { id: 'monitoring', label: 'Monitoring', icon: 'div' },
      ],
      filterForm: {
        date_from: this.filters.date_from,
        date_to: this.filters.date_to,
        account_id: this.filters.account_id || '',
        status: this.filters.status || '',
        search: this.filters.search || '',
      },
      // Mock data for monitoring tab
      topProcessors: [
        { name: 'Acme Corp', apps: 5, avgDays: '3.2' },
        { name: 'TechStart Ltd', apps: 8, avgDays: '4.1' },
        { name: 'Global Solutions', apps: 3, avgDays: '5.8' },
        { name: 'Innovation Hub', apps: 6, avgDays: '6.2' },
        { name: 'Digital Ventures', apps: 4, avgDays: '7.5' },
      ],
    }
  },
  computed: {
    // Timeline Stats
    totalInPeriod() {
      return this.charts.applicationsOverTime.reduce((sum, item) => sum + item.count, 0)
    },
    peakDay() {
      if (this.charts.applicationsOverTime.length === 0) return '0'
      const peak = this.charts.applicationsOverTime.reduce((max, item) => 
        item.count > max.count ? item : max
      , { count: 0 })
      return peak.count
    },
    avgPerDay() {
      if (this.charts.applicationsOverTime.length === 0) return '0'
      return (this.totalInPeriod / this.charts.applicationsOverTime.length).toFixed(1)
    },
    lastAppCreated() {
      if (!this.metrics.mostRecentApplicationDate) return 'No data'
    
      const lastAppDate = new Date(this.metrics.mostRecentApplicationDate)
      
      // Format as: Nov 4, 2025, 3:45:30 PM
      return lastAppDate.toLocaleString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
        hour12: true
      })
    },

    // Account Stats
    highestAccountFee() {
      if (this.charts.topAccounts.length === 0) return '0.00'
      const highest = Math.max(...this.charts.topAccounts.map(a => a.total_fees))
      return highest.toFixed(2)
    },
    avgAppsPerAccount() {
      if (this.metrics.totalAccounts === 0) return '0'
      return (this.metrics.totalApplications / this.metrics.totalAccounts).toFixed(1)
    },
    avgRevenuePerAccount() {
      if (this.metrics.totalAccounts === 0) return '0.00'
      return (this.metrics.totalSetupFees / this.metrics.totalAccounts).toFixed(2)
    },
    avgAccountsPerUser() {
      if (this.metrics.totalUsers === 0) return '0'
      return (this.metrics.totalAccounts / this.metrics.totalUsers).toFixed(1)
    },

    // Financial
    projectedRevenue() {
      return (this.metrics.totalSetupFees * 1.2).toFixed(2)
    },

    // Completion Rates
    completionRate() {
      if (this.metrics.totalApplications === 0) return 0
      const liveCount = this.charts.statusCounts['account_live'] || 0
      return ((liveCount / this.metrics.totalApplications) * 100).toFixed(1)
    },
    approvalRate() {
      if (this.metrics.totalApplications === 0) return 0
      const approvedCount = this.charts.statusCounts['application_approved'] || 0
      return ((approvedCount / this.metrics.totalApplications) * 100).toFixed(1)
    },
    contractRate() {
      if (this.metrics.totalApplications === 0) return 0
      const contractCount = this.charts.statusCounts['contract_completed'] || 0
      return ((contractCount / this.metrics.totalApplications) * 100).toFixed(1)
    },
    invoicePaymentRate() {
      if (this.metrics.totalApplications === 0) return 0
      const paidCount = this.charts.statusCounts['invoice_paid'] || 0
      return ((paidCount / this.metrics.totalApplications) * 100).toFixed(1)
    },

    // Status Radial Chart
    statusChartSeries() {
      const counts = this.charts.statusCounts
      const total = this.metrics.totalApplications
      if (total === 0) return []
      
      const statuses = [
        'created', 'documents_uploaded', 'documents_approved', 'application_sent',
        'contract_completed', 'contract_submitted', 'application_approved',
        'invoice_sent', 'invoice_paid', 'gateway_integrated', 'account_live',
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
              name: { show: false },
              value: { show: false },
            },
            track: {
              background: '#1f2937',
              strokeWidth: '97%',
              margin: 5,
            },
          },
        },
        colors: [
          '#6366f1', '#0ea5e9', '#8b5cf6', '#06b6d4', '#14b8a6',
          '#10b981', '#22c55e', '#84cc16', '#eab308', '#f59e0b', '#f0abfc',
        ],
        labels: [
          'Created', 'Docs Uploaded', 'Docs Approved', 'Contract Sent', 'Contract Signed',
          'Submitted', 'Approved', 'Invoice Sent', 'Invoice Paid', 'Gateway', 'Live',
        ],
        legend: {
          show: true,
          floating: true,
          fontSize: '11px',
          position: 'left',
          offsetX: 0,
          offsetY: 10,
          labels: { useSeriesColors: true },
          markers: { size: 0 },
          formatter: (seriesName, opts) => {
            return seriesName + ": " + opts.w.globals.series[opts.seriesIndex] + "%"
          },
          itemMargin: { vertical: 1 },
        },
        theme: { mode: 'dark' },
      }
    },

    // Donut Chart
    donutChartSeries() {
      return Object.values(this.charts.statusCounts)
    },
    donutChartOptions() {
      return {
        chart: { type: 'donut', background: 'transparent' },
        labels: Object.keys(this.charts.statusCounts).map(s => this.formatStatus(s)),
        colors: [
          '#6366f1', '#0ea5e9', '#8b5cf6', '#06b6d4', '#14b8a6',
          '#10b981', '#22c55e', '#84cc16', '#eab308', '#f59e0b', '#f0abfc',
        ],
        legend: {
          position: 'bottom',
          labels: { colors: '#9ca3af' },
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => val.toFixed(1) + "%",
          style: { fontSize: '11px', colors: ['#fff'] },
        },
        plotOptions: {
          pie: {
            donut: {
              size: '65%',
              labels: {
                show: true,
                total: {
                  show: true,
                  label: 'Total',
                  fontSize: '14px',
                  color: '#9ca3af',
                  formatter: () => this.metrics.totalApplications
                }
              }
            }
          }
        },
        theme: { mode: 'dark' },
      }
    },

    // Area Chart
    areaChartSeries() {
      return [{
        name: 'Applications',
        data: this.charts.applicationsOverTime.map(item => ({
          x: item.date,
          y: item.count,
        })),
      }]
    },
    areaChartOptions() {
      return {
        chart: {
          type: 'area',
          background: 'transparent',
          toolbar: { show: true },
        },
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
        fill: {
          type: 'gradient',
          gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.7,
            opacityTo: 0.2,
          }
        },
        colors: ['#f0abfc'],
        xaxis: {
          type: 'datetime',
          labels: { style: { colors: '#9ca3af' } },
        },
        yaxis: {
          labels: { style: { colors: '#9ca3af' } },
        },
        grid: { borderColor: '#374151' },
        theme: { mode: 'dark' },
        tooltip: { theme: 'dark' },
      }
    },

    // Velocity Chart (Line with markers)
    velocityChartSeries() {
      return this.areaChartSeries
    },
    velocityChartOptions() {
      return {
        chart: {
          type: 'line',
          background: 'transparent',
          toolbar: { show: false },
        },
        stroke: { curve: 'straight', width: 3 },
        markers: {
          size: 5,
          colors: ['#f0abfc'],
          strokeColors: '#fff',
          strokeWidth: 2,
          hover: { size: 7 }
        },
        colors: ['#f0abfc'],
        xaxis: {
          type: 'datetime',
          labels: { style: { colors: '#9ca3af' } },
        },
        yaxis: {
          labels: { style: { colors: '#9ca3af' } },
        },
        grid: { borderColor: '#374151' },
        theme: { mode: 'dark' },
        tooltip: { theme: 'dark' },
      }
    },

    // Bar Chart (Top Accounts)
    barChartSeries() {
      return [{
        name: 'Total Scaling Fees (£)',
        data: this.charts.topAccounts.map(item => item.total_fees),
      }]
    },
    barChartOptions() {
      return {
        chart: {
          type: 'bar',
          background: 'transparent',
          toolbar: { show: false },
        },
        plotOptions: {
          bar: {
            horizontal: true,
            borderRadius: 8,
            dataLabels: { position: 'top' },
          },
        },
        colors: ['#8b5cf6'],
        dataLabels: {
          enabled: true,
          formatter: (val) => '£' + val.toFixed(2),
          offsetX: 30,
          style: { fontSize: '11px', colors: ['#fff'] }
        },
        xaxis: {
          categories: this.charts.topAccounts.map(item => item.name),
          labels: {
            formatter: (val) => '£' + val.toFixed(2),
            style: { colors: '#9ca3af' },
          },
        },
        yaxis: {
          labels: { style: { colors: '#9ca3af' } },
        },
        grid: { borderColor: '#374151' },
        theme: { mode: 'dark' },
        tooltip: {
          theme: 'dark',
          y: { formatter: (val) => '£' + val.toFixed(2) }
        },
      }
    },

    // Apps Per Account
    appsPerAccountSeries() {
      return [{
        name: 'Applications',
        data: this.charts.topAccounts.map(item => item.app_count),
      }]
    },
    appsPerAccountChartOptions() {
      return {
        chart: {
          type: 'bar',
          background: 'transparent',
          toolbar: { show: false },
        },
        plotOptions: {
          bar: {
            borderRadius: 8,
            dataLabels: { position: 'top' },
          },
        },
        colors: ['#06b6d4'],
        dataLabels: {
          enabled: true,
          offsetY: -20,
          style: { fontSize: '11px', colors: ['#fff'] }
        },
        xaxis: {
          categories: this.charts.topAccounts.map(item => item.name),
          labels: { style: { colors: '#9ca3af' } },
        },
        yaxis: {
          labels: { style: { colors: '#9ca3af' } },
        },
        grid: { borderColor: '#374151' },
        theme: { mode: 'dark' },
      }
    },

    // Revenue Charts
    revenueChartSeries() {
      return [{
        name: 'Revenue (£)',
        data: this.charts.topAccounts.map(item => item.total_fees),
      }]
    },
    revenueChartOptions() {
      return {
        ...this.barChartOptions,
        colors: ['#10b981'],
      }
    },

    // Revenue Pie
    revenuePieSeries() {
      return this.charts.topAccounts.map(item => item.total_fees)
    },
    revenuePieOptions() {
      return {
        chart: { type: 'pie', background: 'transparent' },
        labels: this.charts.topAccounts.map(item => item.name),
        colors: ['#8b5cf6', '#06b6d4', '#10b981', '#eab308', '#f59e0b'],
        legend: {
          position: 'bottom',
          labels: { colors: '#9ca3af' },
        },
        dataLabels: {
          enabled: true,
          formatter: (val) => '£' + (this.metrics.totalSetupFees * val / 100).toFixed(0),
          style: { fontSize: '11px', colors: ['#fff'] },
        },
        theme: { mode: 'dark' },
        tooltip: {
          theme: 'dark',
          y: { formatter: (val) => '£' + val.toFixed(2) }
        },
      }
    },

    // Completion Rates Bar
    completionRatesSeries() {
      return [{
        name: 'Completion Rate (%)',
        data: [
          parseFloat(this.completionRate),
          parseFloat(this.approvalRate),
          parseFloat(this.contractRate),
          parseFloat(this.invoicePaymentRate),
        ]
      }]
    },
    completionRatesOptions() {
      return {
        chart: {
          type: 'bar',
          background: 'transparent',
          toolbar: { show: false },
        },
        plotOptions: {
          bar: {
            borderRadius: 8,
            horizontal: true,
            dataLabels: { position: 'top' },
          },
        },
        colors: ['#10b981'],
        dataLabels: {
          enabled: true,
          formatter: (val) => val.toFixed(1) + '%',
          offsetX: 30,
          style: { fontSize: '11px', colors: ['#fff'] }
        },
        xaxis: {
          categories: ['To Live', 'To Approved', 'Contract Signed', 'Invoice Paid'],
          labels: { style: { colors: '#9ca3af' } },
          max: 100,
        },
        yaxis: {
          labels: { style: { colors: '#9ca3af' } },
        },
        grid: { borderColor: '#374151' },
        theme: { mode: 'dark' },
      }
    },

    // Funnel Chart
    funnelChartSeries() {
      const statuses = [
        'created', 'documents_uploaded', 'application_approved',
        'invoice_paid', 'gateway_integrated', 'account_live'
      ]
      return [{
        name: 'Applications',
        data: statuses.map(s => this.charts.statusCounts[s] || 0)
      }]
    },
    funnelChartOptions() {
      return {
        chart: {
          type: 'bar',
          background: 'transparent',
          toolbar: { show: false },
        },
        plotOptions: {
          bar: {
            borderRadius: 0,
            horizontal: true,
            barHeight: '80%',
            isFunnel: true,
          },
        },
        colors: ['#8b5cf6'],
        dataLabels: {
          enabled: true,
          formatter: (val) => val,
          dropShadow: { enabled: false }
        },
        xaxis: {
          categories: [
            'Created',
            'Documents Uploaded',
            'Approved',
            'Invoice Paid',
            'Gateway Integrated',
            'Live'
          ],
        },
        legend: { show: false },
        theme: { mode: 'dark' },
      }
    },

    // Monitoring - Days in Status
    daysInStatusSeries() {
      return [{
        name: 'Avg Days',
        data: [2, 5, 8, 12, 3, 7, 4, 6, 5, 3, 2] // Mock data
      }]
    },
    daysInStatusOptions() {
      return {
        chart: {
          type: 'bar',
          background: 'transparent',
          toolbar: { show: false },
        },
        plotOptions: {
          bar: {
            borderRadius: 8,
            horizontal: true,
            distributed: true,
          },
        },
        colors: [
          '#6366f1', '#0ea5e9', '#8b5cf6', '#06b6d4', '#14b8a6',
          '#10b981', '#22c55e', '#84cc16', '#eab308', '#f59e0b', '#f0abfc',
        ],
        dataLabels: {
          enabled: true,
          formatter: (val) => val + 'd',
          style: { fontSize: '11px', colors: ['#fff'] }
        },
        xaxis: {
          categories: [
            'Created', 'Docs Upload', 'Docs Approved', 'Contract Sent',
            'Contract Signed', 'Submitted', 'Approved', 'Invoice Sent',
            'Invoice Paid', 'Gateway', 'Live'
          ],
          labels: { style: { colors: '#9ca3af' } },
        },
        yaxis: {
          labels: { style: { colors: '#9ca3af' } },
        },
        grid: { borderColor: '#374151' },
        legend: { show: false },
        theme: { mode: 'dark' },
      }
    },
    // User Scatter (for Users tab)
    userScatterSeries() {
      if (!this.charts.userAccountData || this.charts.userAccountData.length === 0) {
        return []
      }
      
      return [{
        name: 'Accounts Managed',
        data: this.charts.userAccountData.map((user, index) => ({
          x: index + 1, // Use index as x-axis position
          y: user.account_count,
          userName: user.name, // Store name for tooltip
        }))
      }]
    },

    userScatterOptions() {
      const userNames = this.charts.userAccountData?.map(user => user.name) || []
      
      return {
        chart: {
          type: 'scatter',
          background: 'transparent',
          toolbar: { show: false },
        },
        colors: ['#8b5cf6'],
        xaxis: {
          title: { text: 'Users', style: { color: '#9ca3af' } },
          labels: { 
            style: { colors: '#9ca3af' },
            formatter: function(val) {
              // Show user name instead of index
              const index = Math.round(val) - 1
              return userNames[index] || ''
            }
          },
          tickAmount: Math.min(userNames.length, 10), // Limit number of ticks
        },
        yaxis: {
          title: { text: 'Accounts Managed', style: { color: '#9ca3af' } },
          labels: { style: { colors: '#9ca3af' } },
        },
        grid: { borderColor: '#374151' },
        theme: { mode: 'dark' },
        tooltip: {
          theme: 'dark',
          custom: function({ series, seriesIndex, dataPointIndex, w }) {
            const data = w.config.series[seriesIndex].data[dataPointIndex]
            return `<div class="px-3 py-2 bg-dark-900 border border-primary-800/30 rounded">
              <div class="font-semibold text-white">${data.userName}</div>
              <div class="text-sm text-gray-400">Accounts: <span class="text-magenta-400 font-bold">${data.y}</span></div>
            </div>`
          }
        },
        markers: {
          size: 6,
          hover: {
            size: 8
          }
        }
      }
    },
    filteredTabs() {
      return this.tabs.filter(tab => {
        // Exclude 'accounts' and 'users' tabs if not admin
        if ((tab.id === 'accounts' || tab.id === 'users') && this.isAccount) {
          return false;
        }
        return true;
      });
    },
    paginationPages() {
      const current = this.processingApplications.current_page
      const last = this.processingApplications.last_page
      const delta = 2
      const pages = []
      
      for (let i = 1; i <= last; i++) {
        if (i === 1 || i === last || (i >= current - delta && i <= current + delta)) {
          pages.push(i)
        }
      }
      
      return pages
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
    changeProcessingPage(page) {
      if (page < 1 || page > this.processingApplications.last_page) return
      
      this.$inertia.get('/dashboard', {
        ...this.filterForm,
        processing_page: page,
      }, {
        preserveState: true,
        preserveScroll: true,
      })
    },
    
    getSpeedClass(days) {
      if (days <= 7) return 'bg-green-900/50 text-green-300'
      if (days <= 14) return 'bg-blue-900/50 text-blue-300'
      if (days <= 21) return 'bg-yellow-900/50 text-yellow-300'
      if (days <= 30) return 'bg-orange-900/50 text-orange-300'
      return 'bg-red-900/50 text-red-300'
    },
    
    getSpeedLabel(days) {
      if (days <= 7) return 'Very Fast'
      if (days <= 14) return 'Fast'
      if (days <= 21) return 'Average'
      if (days <= 30) return 'Slow'
      return 'Very Slow'
    },
    formatStatus(status) {
      if (!status) return 'Created'
      return status.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
    },
    getStatusClass(status) {
      const classes = {
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
      return classes[status] || 'bg-gray-700 text-gray-300'
    },
  },
}
</script>

<style scoped>
.scrollbar-thin::-webkit-scrollbar {
  height: 6px;
}
.scrollbar-thin::-webkit-scrollbar-track {
  background: #1a1a2e;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background: #6b21a8;
  border-radius: 3px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background: #7c3aed;
}
</style>