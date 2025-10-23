<template>
  <div>
    <Head :title="application.name" />
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-3xl font-bold text-white">
        <Link class="text-magenta-400 hover:text-magenta-500 transition-colors" href="/applications">Applications</Link>
        <span class="text-gray-500 mx-2">/</span>
        {{ application.name }}
      </h1>
    </div>

    <!-- Top Section: Two columns -->
    <div class="flex flex-col lg:flex-row gap-6 mb-8">
      <!-- Left Column: Details + Form -->
      <div class="flex-1 flex flex-col gap-6">
        <!-- Application Details -->
        <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
          <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <h2 class="text-magenta-400 font-bold text-lg">Application Details</h2>
          </div>
          <div class="p-8 space-y-4">
            <div>
              <label class="block text-gray-300 font-medium mb-2">Account</label>
              <Link v-if="application.account_id" :href="`/accounts/${application.account_id}/edit`" class="inline-block px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-magenta-400 hover:text-magenta-300 hover:border-magenta-500/50 transition-colors">
                {{ application.account_name || '—' }}
              </Link>
              <div v-else class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                {{ application.account_name || '—' }}
              </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-gray-300 font-medium mb-2">Created By</label>
                <Link v-if="application.user_id" :href="`/users/${application.user_id}/edit`" class="inline-block px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-magenta-400 hover:text-magenta-300 hover:border-magenta-500/50 transition-colors">
                  {{ application.user_name || '—' }}
                </Link>
                <div v-else class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  {{ application.user_name || '—' }}
                </div>
              </div>
              <div>
                <label class="block text-gray-300 font-medium mb-2">Created At</label>
                <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                  {{ formatDate(application.created_at) }}
                </div>
              </div>
            </div>

            <!-- Fee Structure Display (Read-only) -->
            <div class="mt-6 pt-6 border-t border-primary-800/30">
              <h3 class="text-lg font-semibold text-magenta-400 mb-4">Fee Structure</h3>
              <div class="grid grid-cols-2 gap-4">
                <div>
                  <label class="block text-gray-300 font-medium mb-2">Setup Fee</label>
                  <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                    £{{ parseFloat(application.setup_fee).toFixed(2)}}
                  </div>
                </div>
                <div>
                  <label class="block text-gray-300 font-medium mb-2">Transaction Fee</label>
                  <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                    {{ application.transaction_percentage }}% + £{{ parseFloat(application.transaction_fixed_fee).toFixed(2) }}
                  </div>
                </div>
                <div>
                  <label class="block text-gray-300 font-medium mb-2">Monthly Fee</label>
                  <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                    £{{ parseFloat(application.monthly_fee).toFixed(2) }}
                  </div>
                </div>
                <div>
                  <label class="block text-gray-300 font-medium mb-2">Monthly Minimum</label>
                  <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                    £{{ parseFloat(application.monthly_minimum).toFixed(2) }}
                  </div>
                </div>
                <div>
                  <label class="block text-gray-300 font-medium mb-2">Service Fee</label>
                  <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                    £{{ parseFloat(application.service_fee).toFixed(2) }}
                  </div>
                </div>
                <div>
                  <label class="block text-gray-300 font-medium mb-2">Fees Confirmed</label>
                  <div class="px-4 py-2 bg-dark-900/50 border border-primary-800/30 rounded-lg text-gray-300">
                    <span v-if="application.fees_confirmed" class="text-green-400">
                      ✓ Confirmed {{ application.fees_confirmed_at ? `at ${application.fees_confirmed_at}` : '' }}
                    </span>
                    <span v-else class="text-yellow-400">Pending Confirmation</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Edit Application Form -->
        <div class="bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
          <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <h2 class="text-magenta-400 font-bold text-lg">Edit Application</h2>
          </div>
          <form @submit.prevent="update">
            <div class="flex flex-wrap -mb-8 -mr-6 p-8">
              <text-input v-model="form.name" :error="form.errors.name" class="pb-8 pr-6 w-full lg:w-1/2" label="Application Name" />
              
              <!-- Editable Fee Fields -->
              <div class="pb-4 pr-6 w-full">
                <h3 class="text-md font-semibold text-gray-300">Fee Adjustments</h3>
                <p class="text-sm text-gray-400">Modify the fee structure if needed.</p>
              </div>
              
              <text-input 
                v-model="form.setup_fee" 
                :error="form.errors.setup_fee" 
                type="number" 
                step="0.01" 
                class="pb-8 pr-6 w-full lg:w-1/2" 
                label="Setup Fee (£)" 
              />
              <text-input 
                v-model="form.transaction_percentage" 
                :error="form.errors.transaction_percentage" 
                type="number" 
                step="0.01" 
                class="pb-8 pr-6 w-full lg:w-1/2" 
                label="Transaction Percentage (%)" 
              />
              <text-input 
                v-model="form.transaction_fixed_fee" 
                :error="form.errors.transaction_fixed_fee" 
                type="number" 
                step="0.01" 
                class="pb-8 pr-6 w-full lg:w-1/2" 
                label="Fixed Fee Per Transaction (£)" 
              />
              <text-input 
                v-model="form.monthly_fee" 
                :error="form.errors.monthly_fee" 
                type="number" 
                step="0.01" 
                class="pb-8 pr-6 w-full lg:w-1/2" 
                label="Monthly Fee (£)" 
              />
              <text-input 
                v-model="form.monthly_minimum" 
                :error="form.errors.monthly_minimum" 
                type="number" 
                step="0.01" 
                class="pb-8 pr-6 w-full lg:w-1/2" 
                label="Monthly Minimum (£)" 
              />
              <text-input 
                v-model="form.service_fee" 
                :error="form.errors.service_fee" 
                type="number" 
                step="0.01" 
                class="pb-8 pr-6 w-full lg:w-1/2" 
                label="Service Fee (£)" 
              />
            </div>
            <div class="flex items-center justify-between px-8 py-4 bg-dark-900/60 border-t border-primary-800/40">
              <Link href="/applications" class="text-gray-400 hover:text-gray-300 transition-colors">
                Back to Applications
              </Link>
              <loading-button :loading="form.processing" class="btn-primary" type="submit">
                Save Changes
              </loading-button>
            </div>
          </form>
        </div>
      </div>

      <!-- Right Column: Application Status -->
      <div class="w-full lg:w-1/3 bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
        <div class="px-8 py-4 bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
          <h2 class="text-magenta-400 font-bold text-lg">Application Status</h2>
        </div>
        <div class="p-8">
          <Link
            :href="`/applications/${application.id}/status`"
            class="btn-primary w-full text-center"
          >
            View Status
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Layout from '@/Shared/Layout.vue'
import TextInput from '@/Shared/TextInput.vue'
import SelectInput from '@/Shared/SelectInput.vue'
import LoadingButton from '@/Shared/LoadingButton.vue'

export default {
  components: { Head, Link, LoadingButton, SelectInput, TextInput },
  layout: Layout,
  remember: 'form',
  props: {
    application: Object,
    accounts: Array,
  },
  data() {
    return {
      form: this.$inertia.form({
        account_id: this.application.account_id,
        name: this.application.name,
        setup_fee: this.application.setup_fee,
        transaction_percentage: this.application.transaction_percentage,
        transaction_fixed_fee: this.application.transaction_fixed_fee,
        monthly_fee: this.application.monthly_fee,
        monthly_minimum: this.application.monthly_minimum,
        service_fee: this.application.service_fee,
      }),
    }
  },
  methods: {
    update() {
      this.form.put(`/applications/${this.application.id}`)
    },
    formatDate(date) {
      if (!date) return '—'
      const d = new Date(date)
      return d.toLocaleString(undefined, {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      })
    },
  },
}
</script>