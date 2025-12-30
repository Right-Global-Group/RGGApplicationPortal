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
          
          <div class="flex items-center gap-3">
            <label class="flex items-center gap-2 text-sm text-gray-300">
              <input 
                type="checkbox" 
                v-model="isFirstMonth"
                class="rounded border-gray-600 bg-dark-700 text-magenta-500 focus:ring-magenta-500"
              />
              <span>Is First Month</span>
            </label>
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
  
              <!-- Monthly Mini Top Up Row -->
              <tr class="border-b border-primary-800/20">
                <td class="px-6 py-4 text-gray-200">Monthly Mini Top Up</td>
                <td class="px-6 py-4 text-gray-300 text-sm">Monthly Mini Top Up</td>
                <td class="px-6 py-4 text-center text-gray-200">1</td>
                <td class="px-6 py-4 text-right text-gray-200">{{ formatCurrency(monthlyMiniTopUpPrice) }}</td>
                <td class="px-6 py-4 text-right text-gray-300 text-sm">20% (VAT on Income)</td>
                <td class="px-6 py-4 text-right text-gray-200">{{ formatCurrency(monthlyMiniTopUpTax) }}</td>
                <td class="px-6 py-4 text-right text-gray-200 font-medium">{{ formatCurrency(monthlyMiniTopUpAmount) }}</td>
              </tr>
  
              <!-- Decline Fee Row -->
              <tr class="border-b border-primary-800/20">
                <td class="px-6 py-4 text-gray-200">Decline Fee</td>
                <td class="px-6 py-4 text-gray-300 text-sm">Decline Fee</td>
                <td class="px-6 py-4 text-center text-gray-200">{{ declineFeeQty }}</td>
                <td class="px-6 py-4 text-right text-gray-200">{{ formatCurrency(declineFeePrice) }}</td>
                <td class="px-6 py-4 text-right text-gray-300 text-sm">20% (VAT on Income)</td>
                <td class="px-6 py-4 text-right text-gray-200">{{ formatCurrency(declineFeeTax) }}</td>
                <td class="px-6 py-4 text-right text-gray-200 font-medium">{{ formatCurrency(declineFeeAmount) }}</td>
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
      const TAX_RATE = 0.20 // 20% VAT
  
      // Transaction Fee Calculations
      const transactionFeeQty = computed(() => props.merchantStats.accepted)
      const transactionFeePrice = computed(() => parseFloat(props.applicationData.transaction_fixed_fee || 0))
      const transactionFeeSubtotal = computed(() => transactionFeeQty.value * transactionFeePrice.value)
      const transactionFeeTax = computed(() => transactionFeeSubtotal.value * TAX_RATE)
      const transactionFeeAmount = computed(() => transactionFeeSubtotal.value + transactionFeeTax.value)
  
      // Monthly Mini Top Up Calculations
      const monthlyMiniTopUpBasePrice = computed(() => {
        const scalingFee = parseFloat(props.applicationData.scaling_fee || 0)
        const monthlyMinimum = parseFloat(props.applicationData.monthly_minimum || 0)
        
        // If first month checkbox is ticked, always use monthly_minimum
        if (isFirstMonth.value) {
          return monthlyMinimum
        }
        
        // If scaling_fee is 0, use monthly_minimum
        if (scalingFee === 0) {
          return monthlyMinimum
        }
        
        // Otherwise use scaling_fee
        return scalingFee
      })
  
      const monthlyMiniTopUpPrice = computed(() => {
        const basePrice = monthlyMiniTopUpBasePrice.value
        
        // If transaction fee amount (without tax) is less than the base price,
        // subtract it from the base price
        if (transactionFeeSubtotal.value < basePrice) {
          return Math.max(0, basePrice - transactionFeeSubtotal.value)
        }
        
        return basePrice
      })
  
      const monthlyMiniTopUpTax = computed(() => monthlyMiniTopUpPrice.value * TAX_RATE)
      const monthlyMiniTopUpAmount = computed(() => monthlyMiniTopUpPrice.value + monthlyMiniTopUpTax.value)
  
      // Decline Fee Calculations
      const declineFeeQty = computed(() => props.merchantStats.declined)
      const declineFeePrice = computed(() => {
        const declined = props.merchantStats.declined
        const transactionFixedFee = parseFloat(props.applicationData.transaction_fixed_fee || 0)
        return declined * 2 * transactionFixedFee
      })
      const declineFeeTax = computed(() => declineFeePrice.value * TAX_RATE)
      const declineFeeAmount = computed(() => declineFeePrice.value + declineFeeTax.value)
  
      // Monthly Fee Calculations
      const monthlyFeePrice = computed(() => parseFloat(props.applicationData.monthly_fee || 0))
      const monthlyFeeTax = computed(() => monthlyFeePrice.value * TAX_RATE)
      const monthlyFeeAmount = computed(() => monthlyFeePrice.value + monthlyFeeTax.value)
  
      // Totals
      const subtotal = computed(() => {
        return transactionFeeSubtotal.value + 
               monthlyMiniTopUpPrice.value + 
               declineFeePrice.value + 
               monthlyFeePrice.value
      })
  
      const totalTax = computed(() => {
        return transactionFeeTax.value + 
               monthlyMiniTopUpTax.value + 
               declineFeeTax.value + 
               monthlyFeeTax.value
      })
  
      const total = computed(() => subtotal.value + totalTax.value)
  
      const formatCurrency = (value) => {
        return new Intl.NumberFormat('en-GB', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        }).format(value)
      }
  
      return {
        isFirstMonth,
        transactionFeeQty,
        transactionFeePrice,
        transactionFeeTax,
        transactionFeeAmount,
        monthlyMiniTopUpPrice,
        monthlyMiniTopUpTax,
        monthlyMiniTopUpAmount,
        declineFeeQty,
        declineFeePrice,
        declineFeeTax,
        declineFeeAmount,
        monthlyFeePrice,
        monthlyFeeTax,
        monthlyFeeAmount,
        subtotal,
        totalTax,
        total,
        formatCurrency,
      }
    },
  }
  </script>