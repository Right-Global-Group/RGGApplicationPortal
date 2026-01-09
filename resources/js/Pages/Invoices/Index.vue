<template>
  <div>
    <Head title="Invoices" />
    
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-3xl font-bold text-white">Transaction Invoices</h1>
      
      <!-- Upload and Refresh Buttons -->
      <div v-if="$page.props.auth.user.isAdmin" class="flex gap-3">
        <button
          @click="refreshPage"
          class="btn-secondary flex items-center gap-2"
          title="Refresh page"
        >
          <span class="text-lg">↻</span>
          <span>Refresh</span>
        </button>
        
        <label class="btn-primary flex items-center gap-2 cursor-pointer">
          <icon name="upload" class="w-5 h-5 fill-current" />
          <span>Upload Transaction List</span>
          <input
            type="file"
            accept=".xlsx,.xls,.csv"
            class="hidden"
            @change="handleFileUpload"
          />
        </label>
      </div>
    </div>

    <!-- Progress Bar -->
    <div v-if="selectedImport?.status === 'processing'" class="mb-6 p-4 bg-blue-900/30 rounded-xl border border-blue-800/30">
      <div class="flex items-center justify-between mb-2">
        <div class="flex items-center gap-3">
          <div class="w-2 h-2 bg-blue-400 rounded-full animate-pulse"></div>
          <span class="text-sm font-medium text-blue-300">Processing import...</span>
        </div>
        <span class="text-sm text-blue-300 font-mono">
          {{ selectedImport.processed_rows.toLocaleString() }} / {{ selectedImport.estimated_total.toLocaleString() }} rows
        </span>
      </div>
      <div class="w-full bg-gray-700 rounded-full h-2.5 overflow-hidden">
        <div 
          class="bg-gradient-to-r from-blue-500 to-cyan-400 h-2.5 rounded-full transition-all duration-500 ease-out"
          :style="{ width: progressPercentage + '%' }"
        ></div>
      </div>
      <div class="mt-2 text-xs text-blue-400 text-right">
        {{ progressPercentage }}% complete
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
      <!-- Left Side: Imports List -->
      <div class="lg:col-span-1">
        <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl shadow-2xl overflow-hidden border border-primary-800/30">
          <div class="bg-gradient-to-r from-primary-900/50 to-magenta-900/50 px-6 py-4 border-b border-primary-800/30">
            <h2 class="text-xl font-bold text-magenta-400">Previous Imports</h2>
          </div>
          
          <div class="max-h-[600px] overflow-y-auto">
            <div v-if="imports.length === 0" class="px-6 py-8 text-gray-400 text-center">
              No imports yet. Upload an Excel file to get started.
            </div>
            
            <div
              v-for="importItem in imports"
              :key="importItem.id"
              class="border-b border-primary-800/20 last:border-b-0"
            >
              <button
                @click="selectImport(importItem.id)"
                class="w-full px-6 py-4 text-left hover:bg-primary-900/30 transition-colors duration-150"
                :class="{ 'bg-primary-900/50': selectedImportId === importItem.id }"
              >
                <div class="flex items-start justify-between">
                  <div class="flex-1 min-w-0">
                    <div class="font-medium text-white truncate">
                      {{ importItem.filename }}
                    </div>
                    <div class="text-sm text-gray-400 mt-1">
                      {{ importItem.total_rows }} transactions
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                      by {{ importItem.user_name }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ importItem.imported_at }}
                    </div>
                  </div>
                  
                  <button
                    v-if="$page.props.auth.user.isAdmin"
                    @click.stop="deleteImport(importItem.id)"
                    class="ml-3 p-2 text-red-400 hover:text-red-300 hover:bg-red-900/20 rounded transition-colors"
                    title="Delete import"
                  >
                    <icon name="trash" class="w-4 h-4 fill-current" />
                  </button>
                </div>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Side: Merchant Statistics -->
      <div class="lg:col-span-3">
        <div v-if="!selectedImportId" class="bg-dark-800/50 backdrop-blur-sm rounded-xl shadow-2xl border border-primary-800/30 p-12 text-center">
          <icon name="invoices" class="w-16 h-16 fill-gray-600 mx-auto mb-4" />
          <p class="text-gray-400 text-lg">Select an import to view filtered merchant transaction list.</p>
        </div>

        <div v-else class="bg-dark-800/50 backdrop-blur-sm rounded-xl shadow-2xl overflow-hidden border border-primary-800/30">
          <div class="bg-gradient-to-r from-primary-900/50 to-magenta-900/50 px-6 py-4 border-b border-primary-800/30">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-xl font-bold text-magenta-400">Merchant Transactions</h2>
                <p class="text-sm text-gray-400 mt-1">{{ selectedImport?.filename }}</p>
              </div>
              <div class="flex items-center gap-4">
                <div class="text-sm text-gray-400">
                  {{ selectedImport?.imported_at }}
                </div>
                <button
                  v-if="selectedImport?.status === 'completed' && merchantStats.length > 0"
                  @click="exportAllToXero"
                  class="btn-primary flex items-center gap-2"
                  title="Export all merchant invoices to Xero CSV format"
                >
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                    <path d="M13 8V2H7v6H2l8 8 8-8h-5zM0 18h20v2H0v-2z"/>
                  </svg>
                  <span>Export All to Xero</span>
                </button>
              </div>
            </div>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full whitespace-nowrap">
              <thead>
                <tr class="text-left font-bold bg-dark-700/50 border-b border-primary-800/30">
                  <th class="pb-3 pt-4 px-3 text-magenta-400 text-xs">Merchant Name</th>
                  <th class="pb-3 pt-4 px-3 text-magenta-400 text-xs text-center">Total</th>
                  <th class="pb-3 pt-4 px-3 text-magenta-400 text-xs text-center">Accepted</th>
                  <th class="pb-3 pt-4 px-3 text-magenta-400 text-xs text-center">Received</th>
                  <th class="pb-3 pt-4 px-3 text-magenta-400 text-xs text-center">Declined</th>
                  <th class="pb-3 pt-4 px-3 text-magenta-400 text-xs text-center">Canceled</th>
                  <th class="pb-3 pt-4 px-3 text-magenta-400 text-xs text-right">Monthly Min</th>
                  <th class="pb-3 pt-4 px-3 text-magenta-400 text-xs text-right">Scaling Fee</th>
                  <th class="pb-3 pt-4 px-3 text-magenta-400 text-xs text-right">Monthly Fee</th>
                </tr>
              </thead>

              <tbody>
                <!-- Loading State -->
                <tr v-if="selectedImport?.status === 'processing'">
                  <td colspan="9" class="px-6 py-12 text-center">
                    <div class="flex flex-col items-center gap-4">
                      <div class="relative">
                        <div class="w-16 h-16 border-4 border-blue-900/30 border-t-blue-500 rounded-full animate-spin"></div>
                      </div>
                      <div class="text-blue-300 font-medium">
                        Processing transactions...
                      </div>
                      <div class="text-sm text-gray-400">
                        {{ selectedImport.processed_rows.toLocaleString() }} / {{ selectedImport.estimated_total.toLocaleString() }} rows processed
                      </div>
                      <div class="text-xs text-gray-500 mt-2">
                        This may take a few minutes for large files
                      </div>
                    </div>
                  </td>
                </tr>

                <!-- Merchant Stats Rows -->
                <tr
                  v-else
                  v-for="(stat, index) in merchantStats"
                  :key="index"
                  :class="[
                    'border-b border-primary-800/20',
                    stat.monthly_fee ? 'hover:bg-primary-900/30 cursor-pointer transition-colors duration-150' : ''
                  ]"
                  @click="stat.monthly_fee ? navigateToMerchant(stat.merchant_name) : null"
                >
                  <td class="px-3 py-2">
                    <span 
                      :class="stat.monthly_fee ? 'text-gray-200 hover:text-magenta-400 transition-colors duration-150' : 'text-gray-200'"
                    >
                      {{ stat.merchant_name }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-900/30 text-blue-300">
                      {{ stat.total_transactions }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-900/30 text-green-300">
                      {{ stat.accepted }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-900/30 text-yellow-300">
                      {{ stat.received }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-900/30 text-red-300">
                      {{ stat.declined }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-center">
                    <span class="inline-flex items-center justify-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-900/30 text-gray-300">
                      {{ stat.canceled }}
                    </span>
                  </td>
                  <td class="px-3 py-2 text-right text-gray-300 text-sm">
                    <span v-if="stat.monthly_minimum" class="font-medium">
                      £{{ parseFloat(stat.monthly_minimum).toFixed(2) }}
                    </span>
                    <span v-else class="text-gray-500">—</span>
                  </td>
                  <td class="px-3 py-2 text-right text-gray-300 text-sm">
                    <span v-if="stat.scaling_fee !== null && stat.scaling_fee !== undefined" class="font-medium">
                      £{{ parseFloat(stat.scaling_fee).toFixed(2) }}
                    </span>
                    <span v-else class="text-gray-500">—</span>
                  </td>
                  <td class="px-3 py-2 text-right text-gray-300 text-sm">
                    <span v-if="stat.monthly_fee" class="font-medium">
                      £{{ parseFloat(stat.monthly_fee).toFixed(2) }}
                    </span>
                    <span v-else class="text-gray-500">—</span>
                  </td>
                </tr>

                <!-- Empty State (only show if completed and no data) -->
                <tr v-if="merchantStats.length === 0 && selectedImport?.status === 'completed'">
                  <td colspan="9" class="px-6 py-8 text-gray-400 text-center">
                    No merchant data found for this import.
                  </td>
                </tr>
              </tbody>

              <!-- Summary Row -->
              <tfoot v-if="merchantStats.length > 0" class="bg-dark-700/50 font-bold">
                <tr>
                  <td class="px-3 py-2 text-magenta-400 text-sm">TOTAL</td>
                  <td class="px-3 py-2 text-center text-blue-300 text-sm">
                    {{ totals.total_transactions }}
                  </td>
                  <td class="px-3 py-2 text-center text-green-300 text-sm">
                    {{ totals.accepted }}
                  </td>
                  <td class="px-3 py-2 text-center text-yellow-300 text-sm">
                    {{ totals.received }}
                  </td>
                  <td class="px-3 py-2 text-center text-red-300 text-sm">
                    {{ totals.declined }}
                  </td>
                  <td class="px-3 py-2 text-center text-gray-300 text-sm">
                    {{ totals.canceled }}
                  </td>
                  <td class="px-3 py-2 text-right text-magenta-400 text-sm">
                    <span v-if="totals.monthly_minimum > 0">
                      £{{ totals.monthly_minimum.toFixed(2) }}
                    </span>
                    <span v-else>—</span>
                  </td>
                  <td class="px-3 py-2 text-right text-magenta-400 text-sm">
                    <span v-if="totals.scaling_fee > 0">
                      £{{ totals.scaling_fee.toFixed(2) }}
                    </span>
                    <span v-else>—</span>
                  </td>
                  <td class="px-3 py-2 text-right text-magenta-400 text-sm">
                    <span v-if="totals.monthly_fee > 0">
                      £{{ totals.monthly_fee.toFixed(2) }}
                    </span>
                    <span v-else>—</span>
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
  import { Head, router } from '@inertiajs/vue3'
  import Icon from '@/Shared/Icon.vue'
  import Layout from '@/Shared/Layout.vue'
  import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
  
  export default {
    components: {
      Head,
      Icon,
    },
    layout: Layout,
    props: {
      imports: Array,
      selectedImportId: Number,
      merchantStats: Array,
      selectedImport: Object,
    },
    setup(props) {
      const polling = ref(null)
  
      const progressPercentage = computed(() => {
        if (!props.selectedImport?.estimated_total || props.selectedImport.estimated_total === 0) {
          return 0
        }
        return Math.min(
          100,
          Math.round((props.selectedImport.processed_rows / props.selectedImport.estimated_total) * 100)
        )
      })
  
      const totals = computed(() => {
        if (!props.merchantStats || props.merchantStats.length === 0) {
          return {
            total_transactions: 0,
            accepted: 0,
            received: 0,
            declined: 0,
            canceled: 0,
            monthly_minimum: 0,
            scaling_fee: 0,
            monthly_fee: 0,
          }
        }
  
        return props.merchantStats.reduce((acc, stat) => {
          acc.total_transactions += stat.total_transactions
          acc.accepted += stat.accepted
          acc.received += stat.received
          acc.declined += stat.declined
          acc.canceled += stat.canceled
          if (stat.monthly_minimum) {
            acc.monthly_minimum += parseFloat(stat.monthly_minimum)
          }
          if (stat.scaling_fee !== null && stat.scaling_fee !== undefined) {
            acc.scaling_fee += parseFloat(stat.scaling_fee)
          }
          if (stat.monthly_fee) {
            acc.monthly_fee += parseFloat(stat.monthly_fee)
          }
          return acc
        }, {
          total_transactions: 0,
          accepted: 0,
          received: 0,
          declined: 0,
          canceled: 0,
          monthly_minimum: 0,
          scaling_fee: 0,
          monthly_fee: 0,
        })
      })
  
      const startPolling = () => {
        if (polling.value) return
        
        console.log('🟢 Polling started')
        polling.value = setInterval(() => {
          console.log('🔄 Polling refresh...')
          router.reload({ 
            only: ['selectedImport', 'merchantStats'],
            preserveState: true,
            preserveScroll: true,
          })
        }, 2000)
      }
  
      const stopPolling = () => {
        if (polling.value) {
          console.log('🔴 Polling stopped')
          clearInterval(polling.value)
          polling.value = null
        }
      }

      const refreshPage = () => {
        window.location.reload()
      }
  
      // Watch for status changes and manage polling
      watch(() => props.selectedImport?.status, (newStatus, oldStatus) => {
        console.log('📊 Status change:', { from: oldStatus, to: newStatus })
        
        if (newStatus === 'processing') {
          startPolling()
        } else if (oldStatus === 'processing' && newStatus === 'completed') {
          stopPolling()
          // Final refresh to get complete data
          setTimeout(() => {
            router.reload({ 
              only: ['selectedImport', 'merchantStats'],
              preserveState: true,
              preserveScroll: true,
            })
          }, 500)
        } else {
          stopPolling()
        }
      }, { immediate: true })
  
      // Start polling on mount if already processing
      onMounted(() => {
        console.log('🚀 Component mounted, selectedImport:', props.selectedImport)
        if (props.selectedImport?.status === 'processing') {
          startPolling()
        }
      })
  
      // Clean up polling on unmount
      onBeforeUnmount(() => {
        stopPolling()
      })
  
      const selectImport = (importId) => {
        stopPolling()
        router.get('/invoices', { import_id: importId }, {
          preserveState: true,
          preserveScroll: true,
        })
      }
  
      const handleFileUpload = (event) => {
        const file = event.target.files[0]
        if (!file) return
  
        const formData = new FormData()
        formData.append('file', file)
  
        router.post('/invoices/upload', formData, {
          preserveState: false,
          preserveScroll: false,
          onSuccess: () => {
            event.target.value = ''
            window.location.href = response.props.url || window.location.href
          },
          onError: (errors) => {
            console.error('Upload error:', errors)
            event.target.value = ''
          },
        })
      }
  
      const deleteImport = (importId) => {
        if (!confirm('Are you sure you want to delete this import? All associated transaction data will be permanently removed.')) {
          return
        }
  
        router.delete(`/invoices/${importId}`, {
          preserveState: true,
          preserveScroll: true,
        })
      }

      const navigateToMerchant = (merchantName) => {
        window.location.href = `/invoices/${encodeURIComponent(merchantName)}?import_id=${props.selectedImportId}`
      }
      
      const exportAllToXero = () => {
        window.location.href = `/invoices/export-all-xero?import_id=${props.selectedImportId}`
      }
  
      return {
        totals,
        progressPercentage,
        selectImport,
        handleFileUpload,
        deleteImport,
        navigateToMerchant,
        refreshPage,
        exportAllToXero,
        polling,
      }
    },
  }
  </script>