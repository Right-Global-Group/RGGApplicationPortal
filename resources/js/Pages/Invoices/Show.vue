 <template>
    <div>
      <Head :title="`Invoice - ${merchantName}`" />
      
      <div class="mb-6">
        <a 
          :href="importId ? `/invoices?import_id=${importId}` : '/invoices'" 
          class="text-magenta-400 hover:text-magenta-300 transition-colors flex items-center gap-2 mb-4"
        >
          <span>←</span>
          <span>Back to Invoices</span>
        </a>
        
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold text-white">{{ merchantName }}</h1>
            <p class="text-sm text-gray-400 mt-1">Invoice for {{ importFilename }}</p>
          </div>
          
          <div class="flex items-center gap-4">
            <label class="flex items-center gap-2 px-3 py-2 rounded-lg bg-dark-700/50 border border-primary-800/30 hover:border-primary-700/50 transition-colors cursor-pointer">
              <input 
                type="checkbox" 
                v-model="isFirstMonth"
                class="w-4 h-4 rounded border-primary-700 bg-dark-800 text-magenta-500 focus:ring-2 focus:ring-magenta-500 focus:ring-offset-0 cursor-pointer transition-colors"
              />
              <span class="text-sm text-gray-200 select-none">Is First Month</span>
            </label>

            <label class="flex items-center gap-2 px-3 py-2 rounded-lg bg-dark-700/50 border border-primary-800/30 hover:border-primary-700/50 transition-colors cursor-pointer">
              <input 
                type="checkbox" 
                v-model="removeDeclineFee"
                class="w-4 h-4 rounded border-primary-700 bg-dark-800 text-magenta-500 focus:ring-2 focus:ring-magenta-500 focus:ring-offset-0 cursor-pointer transition-colors"
              />
              <span class="text-sm text-gray-200 select-none">Remove Decline Fee</span>
            </label>

            <label class="flex items-center gap-2 px-3 py-2 rounded-lg bg-dark-700/50 border border-primary-800/30 hover:border-primary-700/50 transition-colors cursor-pointer">
              <input 
                type="checkbox" 
                v-model="addChargebackFee"
                class="w-4 h-4 rounded border-primary-700 bg-dark-800 text-magenta-500 focus:ring-2 focus:ring-magenta-500 focus:ring-offset-0 cursor-pointer transition-colors"
              />
              <span class="text-sm text-gray-200 select-none">Add Chargeback Fee</span>
            </label>
            
            <button
              @click="exportToXero"
              class="btn-primary flex items-center gap-2 ml-4"
              title="Export invoice to Xero CSV format"
            >
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 fill-current" viewBox="0 0 20 20">
                <path d="M13 8V2H7v6H2l8 8 8-8h-5zM0 18h20v2H0v-2z"/>
              </svg>
              <span>Export to Xero</span>
            </button>
          </div>
        </div>
      </div>

      <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl shadow-2xl overflow-hidden border border-primary-800/30">
        <div class="bg-gradient-to-r from-primary-900/50 to-magenta-900/50 px-6 py-4 border-b border-primary-800/30">
          <h2 class="text-xl font-bold text-magenta-400">Invoice Details</h2>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="text-left font-bold bg-dark-700/50 border-b border-primary-800/30">
                <th class="pb-3 pt-4 px-6 text-magenta-400 text-sm">Item</th>
                <th class="pb-3 pt-4 px-6 text-magenta-400 text-sm">Description</th>
                <th class="pb-3 pt-4 px-6 text-magenta-400 text-sm text-center">Qty</th>
                <th class="pb-3 pt-4 px-6 text-magenta-400 text-sm text-right">Price</th>
                <th class="pb-3 pt-4 px-6 text-magenta-400 text-sm text-right">Tax Rate</th>
                <th class="pb-3 pt-4 px-6 text-magenta-400 text-sm text-right">Tax Amount</th>
                <th class="pb-3 pt-4 px-6 text-magenta-400 text-sm text-right">Amount GBP</th>
              </tr>
            </thead>

            <tbody>
              <!-- Transaction Fee Row -->
              <tr class="border-b border-primary-800/20">
                <td class="px-6 py-4 text-gray-200">Transaction Fee</td>
                <td class="px-6 py-4 text-gray-300 text-sm">Transaction Fee</td>
                <td class="px-6 py-4 text-center text-gray-200">{{ transactionFeeQty }}</td>
                <td class="px-6 py-4 text-right text-gray-200">{{ formatCurrency(transactionFeePrice) }}</td>
                <td class="px-6 py-4 text-right text-gray-300 text-sm">20% (VAT on Income)</td>
                <td class="px-6 py-4 text-right text-gray-200">{{ formatCurrency(transactionFeeTax) }}</td>
                <td class="px-6 py-4 text-right text-gray-200 font-medium">{{ formatCurrency(transactionFeeAmount) }}</td>
              </tr>

              <!-- Monthly Mini Top Up Row - Only show if there's a shortfall -->
              <tr v-if="shouldShowMonthlyMiniTopUp" class="border-b border-primary-800/20">
                <td class="px-6 py-4 text-gray-200">Monthly Mini Top Up</td>
                <td class="px-6 py-4 text-gray-300 text-sm">Monthly Mini Top Up</td>
                <td class="px-6 py-4 text-center text-gray-200">1</td>
                <td class="px-6 py-4 text-right text-gray-200">{{ formatCurrency(monthlyMiniTopUpPrice) }}</td>
                <td class="px-6 py-4 text-right text-gray-300 text-sm">20% (VAT on Income)</td>
                <td class="px-6 py-4 text-right text-gray-200">{{ formatCurrency(monthlyMiniTopUpTax) }}</td>
                <td class="px-6 py-4 text-right text-gray-200 font-medium">{{ formatCurrency(monthlyMiniTopUpAmount) }}</td>
              </tr>

              <!-- Decline Fee Row -->
              <tr v-if="!removeDeclineFee" class="border-b border-primary-800/20">
                <td class="px-6 py-4 text-gray-200">Decline Fee</td>
                <td class="px-6 py-4 text-gray-300 text-sm">Decline Fee</td>
                <td class="px-6 py-4 text-center text-gray-200">{{ declineFeeQty }}</td>
                <td class="px-6 py-4 text-right text-gray-200">{{ formatCurrency(declineFeePrice) }}</td>
                <td class="px-6 py-4 text-right text-gray-300 text-sm">20% (VAT on Income)</td>
                <td class="px-6 py-4 text-right text-gray-200">{{ formatCurrency(declineFeeTax) }}</td>
                <td class="px-6 py-4 text-right text-gray-200 font-medium">{{ formatCurrency(declineFeeAmount) }}</td>
              </tr>

              <!-- Chargeback Fee Row -->
              <tr v-if="addChargebackFee" class="border-b border-primary-800/20">
                <td class="px-6 py-4 text-gray-200">Chargeback Fee</td>
                <td class="px-6 py-4 text-gray-300 text-sm">Chargeback Fee</td>
                <td class="px-6 py-4 text-center text-gray-200">1</td>
                <td class="px-6 py-4 text-right text-gray-200">{{ formatCurrency(chargebackFeePrice) }}</td>
                <td class="px-6 py-4 text-right text-gray-300 text-sm">20% (VAT on Income)</td>
                <td class="px-6 py-4 text-right text-gray-200">{{ formatCurrency(chargebackFeeTax) }}</td>
                <td class="px-6 py-4 text-right text-gray-200 font-medium">{{ formatCurrency(chargebackFeeAmount) }}</td>
              </tr>

              <!-- Monthly Fee Row -->
              <tr class="border-b border-primary-800/20">
                <td class="px-6 py-4 text-gray-200">Monthly Fee</td>
                <td class="px-6 py-4 text-gray-300 text-sm">Monthly Fee</td>
                <td class="px-6 py-4 text-center text-gray-200">1</td>
                <td class="px-6 py-4 text-right text-gray-200">{{ formatCurrency(monthlyFeePrice) }}</td>
                <td class="px-6 py-4 text-right text-gray-300 text-sm">20% (VAT on Income)</td>
                <td class="px-6 py-4 text-right text-gray-200">{{ formatCurrency(monthlyFeeTax) }}</td>
                <td class="px-6 py-4 text-right text-gray-200 font-medium">{{ formatCurrency(monthlyFeeAmount) }}</td>
              </tr>
            </tbody>

            <!-- Totals Footer -->
            <tfoot class="bg-dark-700/50">
              <tr class="border-b border-primary-800/30">
                <td colspan="6" class="px-6 py-3 text-right text-gray-300 font-medium">Subtotal</td>
                <td class="px-6 py-3 text-right text-gray-200 font-bold">{{ formatCurrency(subtotal) }}</td>
              </tr>
              <tr class="border-b border-primary-800/30">
                <td colspan="6" class="px-6 py-3 text-right text-gray-300 font-medium">Total 20% (VAT on Income)</td>
                <td class="px-6 py-3 text-right text-gray-200 font-bold">{{ formatCurrency(totalTax) }}</td>
              </tr>
              <tr>
                <td colspan="6" class="px-6 py-4 text-right text-magenta-400 font-bold text-lg">Total</td>
                <td class="px-6 py-4 text-right text-magenta-400 font-bold text-lg">{{ formatCurrency(total) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>
    </template>

    <script>
    import { Head } from '@inertiajs/vue3'
    import Layout from '@/Shared/Layout.vue'
    import { computed, ref } from 'vue'

    export default {
    components: {
      Head,
    },
    layout: Layout,
    props: {
      merchantName: String,
      merchantStats: Object,
      applicationData: Object,
      importFilename: String,
      importId: Number,
    },
    setup(props) {
      const isFirstMonth = ref(false)
      const removeDeclineFee = ref(false)
      const addChargebackFee = ref(false)
      const TAX_RATE = 0.20 // 20% VAT

      // Transaction Fee Calculations
      const transactionFeeQty = computed(() => props.merchantStats.accepted)
      const transactionFeePrice = computed(() => parseFloat(props.applicationData.transaction_fixed_fee || 0))
      const transactionFeeSubtotal = computed(() => transactionFeeQty.value * transactionFeePrice.value)
      const transactionFeeTax = computed(() => transactionFeeSubtotal.value * TAX_RATE)
      const transactionFeeAmount = computed(() => transactionFeeSubtotal.value)

      // Monthly Mini Top Up Calculations
      const monthlyMiniTopUpBasePrice = computed(() => {
        const scalingFee = parseFloat(props.applicationData.scaling_fee || 0)
        const monthlyMinimum = parseFloat(props.applicationData.monthly_minimum || 0)
        
        if (isFirstMonth.value) {
          return monthlyMinimum
        }
        
        if (scalingFee === 0) {
          return monthlyMinimum
        }
        
        return scalingFee
      })

      const monthlyMiniTopUpPrice = computed(() => {
        const basePrice = monthlyMiniTopUpBasePrice.value
        
        // Only return a top-up amount if transaction fees are below the minimum
        if (transactionFeeSubtotal.value < basePrice) {
          return Math.max(0, basePrice - transactionFeeSubtotal.value)
        }
        
        // No top-up needed
        return 0
      })

      // Computed property to determine if the Monthly Mini Top Up row should be shown
      const shouldShowMonthlyMiniTopUp = computed(() => monthlyMiniTopUpPrice.value > 0)

      const monthlyMiniTopUpTax = computed(() => monthlyMiniTopUpPrice.value * TAX_RATE)
      const monthlyMiniTopUpAmount = computed(() => monthlyMiniTopUpPrice.value)

      // Decline Fee Calculations
      const declineFeeQty = computed(() => props.merchantStats.declined)
      const declineFeePrice = computed(() => {
        const declined = props.merchantStats.declined
        return declined * 0.10
      })
      const declineFeeTax = computed(() => declineFeePrice.value * TAX_RATE)
      const declineFeeAmount = computed(() => declineFeePrice.value)

      // Chargeback Fee Calculations
      const chargebackFeePrice = 15.00
      const chargebackFeeTax = computed(() => chargebackFeePrice * TAX_RATE)
      const chargebackFeeAmount = computed(() => chargebackFeePrice)

      // Monthly Fee Calculations
      const monthlyFeePrice = computed(() => parseFloat(props.applicationData.monthly_fee || 0))
      const monthlyFeeTax = computed(() => monthlyFeePrice.value * TAX_RATE)
      const monthlyFeeAmount = computed(() => monthlyFeePrice.value)

      // Totals
      const subtotal = computed(() => {
        let total = transactionFeeSubtotal.value + 
                    monthlyFeePrice.value
        
        // Only add Monthly Mini Top Up if it should be shown
        if (shouldShowMonthlyMiniTopUp.value) {
          total += monthlyMiniTopUpPrice.value
        }
        
        if (!removeDeclineFee.value) {
          total += declineFeePrice.value
        }
        
        if (addChargebackFee.value) {
          total += chargebackFeePrice
        }
        
        return total
      })

      const totalTax = computed(() => {
        let total = transactionFeeTax.value + 
                    monthlyFeeTax.value
        
        // Only add Monthly Mini Top Up tax if it should be shown
        if (shouldShowMonthlyMiniTopUp.value) {
          total += monthlyMiniTopUpTax.value
        }
        
        if (!removeDeclineFee.value) {
          total += declineFeeTax.value
        }
        
        if (addChargebackFee.value) {
          total += chargebackFeeTax.value
        }
        
        return total
      })

      const total = computed(() => subtotal.value + totalTax.value)

      const formatCurrency = (value) => {
        return new Intl.NumberFormat('en-GB', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }).format(value)
      }
      
      const exportToXero = () => {
        const params = new URLSearchParams({
          import_id: props.importId,
          is_first_month: isFirstMonth.value ? '1' : '0',
          remove_decline_fee: removeDeclineFee.value ? '1' : '0',
          add_chargeback_fee: addChargebackFee.value ? '1' : '0',
        })
        
        window.location.href = `/invoices/${encodeURIComponent(props.merchantName)}/export-xero?${params.toString()}`
      }

      return {
        isFirstMonth,
        removeDeclineFee,
        addChargebackFee,
        transactionFeeQty,
        transactionFeePrice,
        transactionFeeTax,
        transactionFeeAmount,
        monthlyMiniTopUpPrice,
        monthlyMiniTopUpTax,
        monthlyMiniTopUpAmount,
        shouldShowMonthlyMiniTopUp,
        declineFeeQty,
        declineFeePrice,
        declineFeeTax,
        declineFeeAmount,
        chargebackFeePrice,
        chargebackFeeTax,
        chargebackFeeAmount,
        monthlyFeePrice,
        monthlyFeeTax,
        monthlyFeeAmount,
        subtotal,
        totalTax,
        total,
        formatCurrency,
        exportToXero,
      }
    },
    }
    </script>