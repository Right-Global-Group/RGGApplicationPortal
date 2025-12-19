<template>
    <div>
      <Head title="Invoices" />
      
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-white">Transaction Invoices</h1>
        
        <!-- Upload Button -->
        <div v-if="$page.props.auth.user.isAdmin" class="flex gap-3">
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
  
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
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
        <div class="lg:col-span-2">
          <div v-if="!selectedImportId" class="bg-dark-800/50 backdrop-blur-sm rounded-xl shadow-2xl border border-primary-800/30 p-12 text-center">
            <icon name="invoices" class="w-16 h-16 fill-gray-600 mx-auto mb-4" />
            <p class="text-gray-400 text-lg">Select an import to view merchant statistics</p>
          </div>
  
          <div v-else class="bg-dark-800/50 backdrop-blur-sm rounded-xl shadow-2xl overflow-hidden border border-primary-800/30">
            <div class="bg-gradient-to-r from-primary-900/50 to-magenta-900/50 px-6 py-4 border-b border-primary-800/30">
              <div class="flex items-center justify-between">
                <div>
                  <h2 class="text-xl font-bold text-magenta-400">Merchant Statistics</h2>
                  <p class="text-sm text-gray-400 mt-1">{{ selectedImport?.filename }}</p>
                </div>
                <div class="text-sm text-gray-400">
                  {{ selectedImport?.imported_at }}
                </div>
              </div>
            </div>
  
            <div class="overflow-x-auto">
              <table class="w-full whitespace-nowrap">
                <thead>
                  <tr class="text-left font-bold bg-dark-700/50 border-b border-primary-800/30">
                    <th class="pb-3 pt-4 px-4 text-magenta-400 text-sm">Merchant Name</th>
                    <th class="pb-3 pt-4 px-4 text-magenta-400 text-sm text-center">Total</th>
                    <th class="pb-3 pt-4 px-4 text-magenta-400 text-sm text-center">Accepted</th>
                    <th class="pb-3 pt-4 px-4 text-magenta-400 text-sm text-center">Received</th>
                    <th class="pb-3 pt-4 px-4 text-magenta-400 text-sm text-center">Declined</th>
                    <th class="pb-3 pt-4 px-4 text-magenta-400 text-sm text-center">Canceled</th>
                    <th class="pb-3 pt-4 px-4 text-magenta-400 text-sm text-right">Monthly Fee</th>
                  </tr>
                </thead>
  
                <tbody>
                  <tr
                    v-for="(stat, index) in merchantStats"
                    :key="index"
                    class="hover:bg-primary-900/30 transition-colors duration-150 border-b border-primary-800/20"
                  >
                    <td class="px-4 py-3 text-gray-200">
                      {{ stat.merchant_name }}
                    </td>
                    <td class="px-4 py-3 text-center">
                      <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-sm font-medium bg-blue-900/30 text-blue-300">
                        {{ stat.total_transactions }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                      <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-sm font-medium bg-green-900/30 text-green-300">
                        {{ stat.accepted }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                      <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-900/30 text-yellow-300">
                        {{ stat.received }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                      <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-sm font-medium bg-red-900/30 text-red-300">
                        {{ stat.declined }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                      <span class="inline-flex items-center justify-center px-3 py-1 rounded-full text-sm font-medium bg-gray-900/30 text-gray-300">
                        {{ stat.canceled }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-right text-gray-300">
                      <span v-if="stat.monthly_fee" class="font-medium">
                        £{{ parseFloat(stat.monthly_fee).toFixed(2) }}
                      </span>
                      <span v-else class="text-gray-500">—</span>
                    </td>
                  </tr>
  
                  <tr v-if="merchantStats.length === 0">
                    <td colspan="7" class="px-6 py-8 text-gray-400 text-center">
                      No merchant data found for this import.
                    </td>
                  </tr>
                </tbody>
  
                <!-- Summary Row -->
                <tfoot v-if="merchantStats.length > 0" class="bg-dark-700/50 font-bold">
                  <tr>
                    <td class="px-4 py-3 text-magenta-400">TOTAL</td>
                    <td class="px-4 py-3 text-center text-blue-300">
                      {{ totals.total_transactions }}
                    </td>
                    <td class="px-4 py-3 text-center text-green-300">
                      {{ totals.accepted }}
                    </td>
                    <td class="px-4 py-3 text-center text-yellow-300">
                      {{ totals.received }}
                    </td>
                    <td class="px-4 py-3 text-center text-red-300">
                      {{ totals.declined }}
                    </td>
                    <td class="px-4 py-3 text-center text-gray-300">
                      {{ totals.canceled }}
                    </td>
                    <td class="px-4 py-3 text-right text-magenta-400">
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
  import { computed } from 'vue'
  
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
      const totals = computed(() => {
        if (!props.merchantStats || props.merchantStats.length === 0) {
          return {
            total_transactions: 0,
            accepted: 0,
            received: 0,
            declined: 0,
            canceled: 0,
            monthly_fee: 0,
          }
        }
  
        return props.merchantStats.reduce((acc, stat) => {
          acc.total_transactions += stat.total_transactions
          acc.accepted += stat.accepted
          acc.received += stat.received
          acc.declined += stat.declined
          acc.canceled += stat.canceled
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
          monthly_fee: 0,
        })
      })
  
      const selectImport = (importId) => {
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
          preserveState: true,
          preserveScroll: false,
          onSuccess: () => {
            event.target.value = '' // Reset file input
          },
          onError: (errors) => {
            console.error('Upload error:', errors)
            event.target.value = '' // Reset file input
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
  
      return {
        totals,
        selectImport,
        handleFileUpload,
        deleteImport,
      }
    },
  }
  </script>