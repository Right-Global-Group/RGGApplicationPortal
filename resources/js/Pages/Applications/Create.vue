<template>
  <div>
    <Head title="Create Application" />
    <h1 class="mb-6 text-3xl font-bold text-white">
      <Link class="text-magenta-400 hover:text-magenta-500 transition-colors" href="/applications">Applications</Link>
      <span class="text-gray-500 mx-2">/</span>
      Create
    </h1>

    <div class="max-w-3xl bg-dark-800/50 backdrop-blur-sm border border-primary-800/30 rounded-xl shadow-2xl overflow-hidden">
      <form @submit.prevent="store">
        <div class="flex flex-wrap -mb-8 -mr-6 p-8">
          <select-input v-model="form.account_id" :error="form.errors.account_id" class="pb-8 pr-6 w-full lg:w-1/2" label="Account">
            <option :value="null" />
            <option v-for="account in accounts" :key="account.id" :value="account.id">
              {{ account.name }}
            </option>
          </select-input>
          <text-input v-model="form.name" :error="form.errors.name" class="pb-8 pr-6 w-full lg:w-1/2" label="Application Name" />
          
          <!-- Fee Structure Section -->
          <div class="pb-4 pr-6 w-full">
            <h3 class="text-lg font-semibold text-magenta-400 mb-2">Fee Structure</h3>
            <p class="text-sm text-gray-400">Configure the payment processing fees for this application.</p>
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
            v-model="form.scaling_fee" 
            :error="form.errors.scaling_fee" 
            type="number" 
            step="0.01" 
            class="pb-8 pr-6 w-full lg:w-1/2" 
            label="Scaling Fee (£)" 
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
          
          <!-- Fee Explanation -->
          <div class="pb-8 pr-6 w-full">
            <div class="bg-dark-900/50 border border-primary-800/30 rounded-lg p-4 text-gray-300 text-sm">
              <p class="font-semibold mb-2 text-magenta-400">Fee Structure Summary:</p>
              <ul class="list-disc list-inside space-y-1">
                <li>A setup fee of £{{ parseFloat(form.setup_fee || 0).toFixed(2) }} is added</li>
                <li>£{{ parseFloat(form.monthly_fee || 0).toFixed(2) }} monthly fee</li>
                <li>£{{ parseFloat(form.monthly_minimum || 0).toFixed(2) }} monthly minimum made up of transactional fees</li>
                <li>{{ form.transaction_percentage || 0 }}% + £{{ parseFloat(form.transaction_fixed_fee || 0).toFixed(2) }} per transaction</li>
                <li v-if="form.transaction_fixed_fee > 0">Example: {{ Math.floor(parseFloat(form.monthly_minimum || 100) / parseFloat(form.transaction_fixed_fee || 0.20)) }} transactions × £{{ parseFloat(form.transaction_fixed_fee || 0.20).toFixed(2) }} = £{{ (Math.floor(parseFloat(form.monthly_minimum || 100) / parseFloat(form.transaction_fixed_fee || 0.20)) * parseFloat(form.transaction_fixed_fee || 0.20)).toFixed(2) }}</li>
                <li>
                  Scaling fee of £{{ parseFloat(form.scaling_fee || 0).toFixed(2) }}
                </li>
              </ul>
            </div>
          </div>
          <div class="pb-8 pr-6 w-full">
            <div class="bg-dark-800/40 border border-primary-800/20 rounded-lg p-4 text-gray-300 text-sm">
              <p class="font-semibold mb-2 text-blue-400">Notifications:</p>
              <p>
                Once the application has been created, the account holder will receive an email
                with their login details and instructions to access their account.
              </p>
            </div>
          </div>
        </div>
        <div class="flex items-center justify-end px-8 py-4 bg-dark-900/60 border-t border-primary-800/40">
          <loading-button :loading="form.processing" class="btn-primary" type="submit">Create Application</loading-button>
        </div>
      </form>
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
    accounts: Array,
    preselected_account_id: [Number, null],
  },
  data() {
    return {
      form: this.$inertia.form({
        account_id: this.preselected_account_id,
        name: null,
        scaling_fee: 450.00,
        transaction_percentage: 2.00,
        transaction_fixed_fee: 0.20,
        monthly_fee: 18.00,
        monthly_minimum: 100.00,
        setup_fee: 10.00,
      }),
    }
  },
  methods: {
    store() {
      this.form.post('/applications')
    },
  },
}
</script>