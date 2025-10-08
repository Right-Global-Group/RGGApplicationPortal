<template>
  <div>
    <!-- Main Menu -->
    <div
        v-for="(item, index) in mainMenu"
        :key="item.href"
        :class="[
          'mb-2',
          index === 0 ? 'mt-6' : ''
        ]"
      >
      <Link
        class="group flex items-center py-3 px-4 rounded-lg transition-all duration-200 hover:bg-primary-800/50"
        :class="{ 'bg-primary-800/50': isUrl(item.match) }"
        :href="item.href"
      >
        <Icon
          :name="item.name"
          class="flex-shrink-0 mr-3 w-5 h-5 transition-colors"
          :class="isUrl(item.match)
            ? 'fill-magenta-400'
            : 'fill-primary-400 group-hover:fill-magenta-400'"
        />
        <div
          class="font-medium transition-colors"
          :class="isUrl(item.match)
            ? 'text-white'
            : 'text-gray-300 group-hover:text-white'"
        >
          {{ item.label }}
        </div>
      </Link>
    </div>

    <!-- Divider -->
    <div class="my-4 border-t border-primary-700"></div>

    <!-- Secondary Menu -->
    <div
      v-for="item in secondaryMenu"
      :key="item.href"
      class="mb-2"
    >
      <Link
        class="group flex items-center py-3 px-4 rounded-lg transition-all duration-200 hover:bg-primary-800/50"
        :class="{ 'bg-primary-800/50': isUrl(item.match) }"
        :href="item.href"
      >
        <Icon
          :name="item.name"
          class="flex-shrink-0 mr-3 w-5 h-5 transition-colors"
          :class="isUrl(item.match)
            ? 'fill-magenta-400'
            : 'fill-primary-400 group-hover:fill-magenta-400'"
        />
        <div
          class="font-medium transition-colors"
          :class="isUrl(item.match)
            ? 'text-white'
            : 'text-gray-300 group-hover:text-white'"
        >
          {{ item.label }}
        </div>
      </Link>
    </div>
  </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'
import Icon from '@/Shared/Icon.vue'

export default {
  components: {
    Icon,
    Link,
  },
  data() {
    return {
      mainMenu: [
        { name: 'dashboard', label: 'Dashboard', href: '/', match: '' },
        { name: 'office', label: 'Applications', href: '/applications', match: 'applications' },
        { name: 'users', label: 'Contacts', href: '/contacts', match: 'contacts' },
        { name: 'printer', label: 'Reports', href: '/reports', match: 'reports' },
      ],
      secondaryMenu: [
        { name: 'progress-tracker', label: 'Progress Tracker', href: '/progress-tracker', match: 'progress-tracker' },
      ],
    }
  },
  methods: {
    isUrl(...urls) {
      const currentUrl = this.$page.url.substr(1)
      if (urls[0] === '') {
        return currentUrl === ''
      }
      return urls.some((url) => currentUrl.startsWith(url))
    },
  },
}
</script>
