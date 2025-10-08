<template>
  <div class="flex items-center">
    <div class="flex w-full bg-gray-800 rounded shadow">
      <dropdown :auto-close="false" class="focus:z-10 px-4 hover:bg-gray-700 border-r focus:border-gray-800 rounded-l focus:ring md:px-6" placement="bottom-start">
        <template #default>
          <div class="flex items-baseline">
            <span class="hidden text-gray-200 md:inline">Filter</span>
            <svg class="w-2 h-2 fill-gray-200 md:ml-2" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 961.243 599.998">
              <path d="M239.998 239.999L0 0h961.243L721.246 240c-131.999 132-240.28 240-240.624 239.999-.345-.001-108.625-108.001-240.624-240z" />
            </svg>
          </div>
        </template>
        <template #dropdown>
          <div class="mt-2 px-4 py-6 w-screen bg-gray-800 rounded shadow-xl" :style="{ maxWidth: `${maxWidth}px` }">
            <slot />
          </div>
        </template>
      </dropdown>
      <input class="relative px-6 py-3 w-full rounded-r focus:shadow-outline bg-gray-700 text-gray-200" autocomplete="off" type="text" name="search" placeholder="Search…" :value="modelValue" @input="$emit('update:modelValue', $event.target.value)" />
    </div>
    <button class="ml-3 text-gray-400 hover:text-gray-200 focus:text-indigo-400 text-sm" type="button" @click="$emit('reset')">Reset</button>
  </div>
</template>

<script>
import Dropdown from '@/Shared/Dropdown.vue'

export default {
  components: {
    Dropdown,
  },
  props: {
    modelValue: String,
    maxWidth: {
      type: Number,
      default: 300,
    },
  },
  emits: ['update:modelValue', 'reset'],
}
</script>
