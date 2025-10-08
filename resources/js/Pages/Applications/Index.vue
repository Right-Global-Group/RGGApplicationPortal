<template>
  <div>
    <Head title="Applications" />
    <h1 class="mb-4 text-3xl font-bold text-white">Applications</h1>
    <div class="flex items-center justify-between mb-6">
      <search-filter v-model="form.search" class="mr-4 w-full max-w-md" @reset="reset">
        <label class="block text-gray-300 font-medium mb-2">Trashed:</label>
        <select v-model="form.trashed" class="form-select mt-1 w-full bg--800 border-primary-700 text-gray-200 rounded-lg focus:border-magenta-500 focus:ring-magenta-500">
          <option :value="null" />
          <option value="with">With Trashed</option>
          <option value="only">Only Trashed</option>
        </select>
      </search-filter>
      <Link class="btn-primary flex items-center gap-2" href="/applications/create">
        <span>Create</span>
        <span class="hidden md:inline">Application</span>
      </Link>
    </div>
    <div class="bg-dark-800/50 backdrop-blur-sm rounded-xl shadow-2xl overflow-hidden border border-primary-800/30">
      <table class="w-full whitespace-nowrap">
        <thead>
          <tr class="text-left font-bold bg-gradient-to-r from-primary-900/50 to-magenta-900/50 border-b border-primary-700/50">
            <th class="pb-4 pt-6 px-6 text-magenta-400">Name</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400">City</th>
            <th class="pb-4 pt-6 px-6 text-magenta-400" colspan="2">Phone</th>
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
            <td class="border-t border-primary-800/20">
              <Link 
                class="flex items-center px-6 py-4 text-gray-300 hover:text-gray-100 transition-colors" 
                :href="`/applications/${application.id}/edit`" 
                tabindex="-1"
              >
                {{ application.city }}
              </Link>
            </td>
            <td class="border-t border-primary-800/20">
              <Link 
                class="flex items-center px-6 py-4 text-gray-300 hover:text-gray-100 transition-colors" 
                :href="`/applications/${application.id}/edit`" 
                tabindex="-1"
              >
                {{ application.phone }}
              </Link>
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
            <td class="px-6 py-8 border-t border-primary-800/20 text-gray-400 text-center" colspan="4">
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
    applications: Object,
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
        this.$inertia.get('/applications', pickBy(this.form), { preserveState: true })
      }, 150),
    },
  },
  methods: {
    reset() {
      this.form = mapValues(this.form, () => null)
    },
  },
}
</script>