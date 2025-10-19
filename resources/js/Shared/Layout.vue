<template>
  <div class="min-h-screen bg-dark-900 text-gray-200">
    <!-- Dropdown for mobile -->
    <div id="dropdown" />

    <div class="md:flex md:flex-col">
      <div class="md:flex md:flex-col md:h-screen">
        <!-- Top bar -->
        <div class="md:flex md:shrink-0">
          <div class="flex items-center justify-between px-6 py-2 bg-dark-800 border-b border-primary-700">
            <Link class="mt-1" href="/">
              <Logo class="fill-magenta-400" width="140" height="38" />
            </Link>
            <Dropdown class="md:hidden" placement="bottom-end">
              <template #default>
                <svg class="w-6 h-6 fill-magenta-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                  <path d="M0 3h20v2H0V3zm0 6h20v2H0V9zm0 6h20v2H0v-2z" />
                </svg>
              </template>
              <template #dropdown>
                <div class="mt-2 px-8 py-4 bg-dark-800 rounded-xl shadow-lg border border-primary-700">
                  <MainMenu />
                </div>
              </template>
            </Dropdown>
          </div>

          <!-- User bar -->
          <div class="md:text-md flex items-center justify-end md:pl-20 md:px-12 pl-10 pr-8 w-full text-sm bg-dark-800 border-b border-primary-700">
            <Dropdown class="mt-1" placement="bottom-end">
              <template #default>
                <div class="group flex items-center cursor-pointer select-none">
                  <div class="mr-1 text-gray-300 group-hover:text-magenta-400 whitespace-nowrap font-medium transition-colors">
                    <span>{{ auth.user.first_name }}</span>
                    <span class="hidden md:inline">&nbsp;{{ auth.user.last_name }}</span>
                  </div>
                  <Icon name="cheveron-down" class="w-5 h-5 fill-gray-400 group-hover:fill-magenta-400 transition-colors" />
                </div>
              </template>

              <template #dropdown>
                <div class="mt-2 py-2 text-sm bg-dark-800 rounded-xl shadow-xl border border-primary-700 w-48">
                  <Link 
                    class="block px-6 py-2 text-gray-300 hover:text-white hover:bg-magenta-500 rounded transition-all duration-200" 
                    :href="`/users/${auth.user.id}/edit`"
                  >
                    My Profile
                  </Link>
                  <Link 
                    class="block px-6 py-2 w-full text-left text-gray-300 hover:text-white hover:bg-magenta-500 rounded transition-all duration-200" 
                    href="/logout" method="delete" as="button"
                  >
                    Logout
                  </Link>
                </div>
              </template>
            </Dropdown>
          </div>
        </div>

        <!-- Main content -->
        <div class="md:flex md:grow md:overflow-hidden">
          <MainMenu class="hidden shrink-0 p-6 bg-dark-800 border-r border-primary-700 overflow-y-auto md:block rounded-xl" />
          <div class="px-4 py-8 md:flex-1 md:p-12 md:overflow-y-auto" scroll-region>
            <FlashMessages />
            <slot />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
import { Link } from '@inertiajs/vue3'
import Icon from '@/Shared/Icon.vue'
import Logo from '@/Shared/Logo.vue'
import Dropdown from '@/Shared/Dropdown.vue'
import MainMenu from '@/Shared/MainMenu.vue'
import FlashMessages from '@/Shared/FlashMessages.vue'

export default {
  components: {
    Dropdown,
    FlashMessages,
    Icon,
    Link,
    Logo,
    MainMenu,
  },
  props: {
    auth: Object,
  },
}
</script>