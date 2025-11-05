<template>
  <div>
    <!-- Main Menu -->
    <div
      v-for="(item, index) in filteredMainMenu"
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
    <div v-if="filteredSecondaryMenu.length > 0" class="my-4 border-t border-primary-700"></div>

    <!-- Secondary Menu -->
    <div
      v-for="item in filteredSecondaryMenu"
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
        { 
          name: 'dashboard', 
          label: 'Dashboard', 
          href: '/', 
          match: '',
          adminOnly: false 
        },
        { 
          name: 'users', 
          label: 'Users', 
          href: '/users', 
          match: 'users',
          adminOnly: true // Only admins can see users
        },
        { 
          name: 'office', 
          label: 'Accounts', 
          href: '/accounts', 
          match: 'accounts',
          adminOnly: false
        },
        { 
          name: 'accounts', 
          label: 'Applications', 
          href: '/applications', 
          match: 'applications',
          adminOnly: false 
        },
      ],
      secondaryMenu: [
        { 
          name: 'progress-tracker', 
          label: 'Progress Tracker', 
          href: '/progress-tracker', 
          match: 'progress-tracker',
          adminOnly: false 
        },
        { 
          name: 'mail', 
          label: 'Email Templates', 
          href: '/email-templates', 
          match: 'email-templates',
          adminOnly: true // Only admins can manage email templates
        },
      ],
    }
  },
  computed: {
    isAdmin() {
      return this.$page.props.auth?.user?.isAdmin || false
    },
    filteredMainMenu() {
      return this.mainMenu.filter(item => {
        if (item.adminOnly) {
          return this.isAdmin
        }
        return true
      })
    },
    filteredSecondaryMenu() {
      return this.secondaryMenu.filter(item => {
        if (item.adminOnly) {
          return this.isAdmin
        }
        return true
      })
    },
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