<template>
  <div>
    <Head title="Applications" />

    <div class="flex justify-between">
      <h1 class="mb-4 text-3xl font-bold text-white">Applications</h1>

      <!-- Create Button -->
      <div class="flex justify-end mb-6">
        <Link class="btn-primary flex items-center gap-2" href="/applications/create">
          <span>Create</span>
          <span class="hidden md:inline">Application</span>
        </Link>
      </div>
    </div>

    <!-- Filters -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl p-6 mb-6 border border-primary-800/30">
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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

        <!-- Date From -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Created From</label>
          <input
            v-model="form.date_from"
            type="date"
            class="w-full bg-dark-700 border border-primary-800/30 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-magenta-500 focus:border-transparent"
            @change="filter"
          />
        </div>

        <!-- Date To -->
        <div>
          <label class="block text-sm font-medium text-gray-300 mb-2">Created To</label>
          <input
            v-model="form.date_to"
            type="date"
            class="w-full bg-dark-700 border border-primary-800/30 rounded-lg px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-magenta-500 focus:border-transparent"
            @change="filter"
          />
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

    <!-- Table -->
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl shadow-2xl overflow-hidden border border-primary-800/30">
      <table class="w-full whitespace-nowrap">
        <thead>
          <tr class="text-left font-bold bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-800/30">
            <th class="pb-4 pt-6 px-6 text-magenta-400">Name</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400">Created By</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400">Merchant Account</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400">Created At</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400">Updated At</th>
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
                class="flex items-center px-6 py-4 text-gray-200 hover:text-magenta-400 focus:text-magenta-400 transition-colors" 
                :href="`/applications/${application.id}/edit`"
              >
                {{ application.name }}
                <icon v-if="application.deleted_at" name="trash" class="shrink-0 ml-2 w-3 h-3 fill-gray-500" />
              </Link>
            </td>
            <td class="px-6 py-4 text-gray-300">
              {{ application.user_name || '—' }}
            </td>
            <td class="border-t border-primary-800/20">
              <Link 
                class="flex items-center px-6 py-4 text-gray-200 hover:text-magenta-400 focus:text-magenta-400 transition-colors" 
                :href="`/applications/${application.id}/edit`"
              >
                {{ application.account_name }}
              </Link>
            </td>
            <td class="px-6 py-4 text-gray-300 text-sm">
              {{ application.created_at }}
            </td>
            <td class="px-6 py-4 text-gray-300 text-sm">
              {{ application.updated_at }}
            </td>
            <td class="w-px border-t border-primary-800/20">
              <Link 
                class="flex items-center px-4 hover:bg-primary-800/50 py-4 rounded transition-colors" 
                :href="`/applications/${application.id}/edit`" 
                tabindex="-1"
              >
                <icon name="cheveron-right" class="block w-6 h-6 fill-gray-400 group-hover:fill-magenta-400" />
              </Link>
            </td>
          </tr>
          <tr v-if="applications.data.length === 0">
            <td class="px-6 py-8 border-t border-primary-800/20 text-gray-400 text-center" colspan="6">
              No applications found.
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <pagination class="mt-6" :links="applications.links" />
  </div>
</template>

<script>
import { Head, Link, router } from '@inertiajs/vue3'
import Icon from '@/Shared/Icon.vue'
import Layout from '@/Shared/Layout.vue'
import Pagination from '@/Shared/Pagination.vue'
import { reactive, computed } from 'vue'
import throttle from 'lodash/throttle'

export default {
  components: {
    Head,
    Icon,
    Link,
    Pagination,
  },
  layout: Layout,
  props: {
    filters: Object,
    applications: Object,
  },
  setup(props) {
    const form = reactive({
      search: props.filters.search || '',
      account_search: props.filters.account_search || '',
      date_from: props.filters.date_from || '',
      date_to: props.filters.date_to || '',
      deleted: props.filters.deleted || null,
    })

    const hasFilters = computed(() => {
      return form.search || form.account_search || form.date_from || form.date_to || form.deleted
    })

    const filter = () => {
      router.get('/applications', form, {
        preserveState: true,
        preserveScroll: true,
      })
    }

    const debouncedFilter = throttle(filter, 300)

    const clearFilters = () => {
      form.search = ''
      form.account_search = ''
      form.date_from = ''
      form.date_to = ''
      form.deleted = null
      filter()
    }

    return {
      form,
      hasFilters,
      filter,
      debouncedFilter,
      clearFilters,
    }
  },
}
</script>