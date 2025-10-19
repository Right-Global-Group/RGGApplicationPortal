<template>
  <div>
    <Head title="Accounts" />
    <h1 class="mb-4 text-3xl font-bold text-white">Merchant Accounts</h1>

    <!-- Filters + Create button -->
    <div class="flex items-center justify-between mb-6">

      <search-filter v-model="form.search" class="mr-4 w-full max-w-md" @reset="reset">
        <label class="block text-gray-300 font-medium mb-2">Trashed:</label>
        <select
          v-model="form.trashed"
          class="form-select mt-1 w-full bg-dark-800 border-primary-700 text-gray-200 rounded-lg focus:border-magenta-500 focus:ring-magenta-500"
        >
          <option :value="null" />
          <option value="with">With Trashed</option>
          <option value="only">Only Trashed</option>
        </select>
      </search-filter>

      <Link class="btn-primary flex items-center gap-2" href="/accounts/create">
        <span>Create</span>
        <span class="hidden md:inline">Account</span>
      </Link>
    </div>

    <!-- Table -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl shadow-2xl overflow-hidden border border-primary-800/30">
      <table class="w-full whitespace-nowrap">
        <thead>
          <tr class="text-left font-bold bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <th class="pb-4 pt-6 px-6 text-magenta-400">Name</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400">Created By</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400">Created At</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400">Updated At</th>
          </tr>
        </thead>

        <tbody>
          <tr
            v-for="account in accounts.data"
            :key="account.id"
            class="hover:bg-primary-900/30 transition-colors duration-150 border-b border-primary-800/20"
          >
            <td class="px-6 py-4 text-white">
              {{ account.name }}
            </td>
            <td class="px-6 py-4 text-gray-300">
              {{ account.user_name || '—' }}
            </td>
            <td class="px-6 py-4 text-gray-300">
              {{ formatDate(account.created_at) }}
            </td>
            <td class="px-6 py-4 text-gray-300">
              {{ formatDate(account.updated_at) }}
            </td>
            <td class="w-px border-t border-primary-800/20">
              <Link 
                class="flex items-center px-4 hover:bg-primary-800/50 py-4 rounded transition-colors" 
                :href="`/accounts/${account.id}/edit`" 
                tabindex="-1"
              >
                <icon name="cheveron-right" class="block w-6 h-6 fill-gray-400 group-hover:fill-magenta-400" />
              </Link>
            </td>
          </tr>

          <tr v-if="accounts.data.length === 0">
            <td class="px-6 py-8 border-t border-primary-800/20 text-gray-400 text-center" colspan="5">
              No accounts found.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <pagination class="mt-6" :links="accounts.links" />
  </div>
</template>

<script>
import { Head, Link } from '@inertiajs/vue3'
import Icon from '@/Shared/Icon.vue'
import pickBy from 'lodash/pickBy'
import Layout from '@/Shared/Layout.vue'
import throttle from 'lodash/throttle'
import mapValues from 'lodash/mapValues'
import Pagination from '@/Shared/Pagination.vue'
import SearchFilter from '@/Shared/SearchFilter.vue'

export default {
  components: {
    Head,
    Icon,
    Link,
    Pagination,
    SearchFilter,
  },
  layout: Layout,
  props: {
    filters: Object,
    accounts: Object,
  },
  data() {
    return {
      form: {
        search: this.filters.search,
        trashed: this.filters.trashed,
      },
    }
  },
  watch: {
    form: {
      deep: true,
      handler: throttle(function () {
        this.$inertia.get('/accounts', pickBy(this.form), { preserveState: true })
      }, 150),
    },
  },
  methods: {
    reset() {
      this.form = mapValues(this.form, () => null)
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
