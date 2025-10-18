<template>
    <div class="flex items-start gap-4">
      <div class="flex flex-col items-center">
        <div
          class="w-8 h-8 rounded-full flex items-center justify-center border-2 transition-all"
          :class="stepClasses"
        >
          <icon
            v-if="isCompleted"
            name="check"
            class="w-4 h-4 fill-white"
          />
          <div v-else class="w-2 h-2 rounded-full" :class="dotClasses"></div>
        </div>
        <div class="w-0.5 h-12 mt-2" :class="lineClasses"></div>
      </div>
      <div class="flex-1 pb-8">
        <div class="font-semibold" :class="labelClasses">
          {{ step.label }}
        </div>
        <div v-if="timestamp" class="text-sm text-gray-400 mt-1">
          {{ timestamp }}
        </div>
      </div>
    </div>
  </template>
  
  <script>
  import Icon from '@/Shared/Icon.vue'
  
  export default {
    components: { Icon },
    props: {
      step: Object,
      isCurrent: Boolean,
      isCompleted: Boolean,
      timestamp: String,
    },
    computed: {
      stepClasses() {
        if (this.isCompleted) {
          return 'bg-magenta-600 border-magenta-500'
        }
        if (this.isCurrent) {
          return 'bg-primary-800 border-magenta-500 animate-pulse'
        }
        return 'bg-gray-800 border-gray-600'
      },
      dotClasses() {
        if (this.isCurrent) {
          return 'bg-magenta-400'
        }
        return 'bg-gray-500'
      },
      lineClasses() {
        if (this.isCompleted) {
          return 'bg-magenta-600'
        }
        return 'bg-gray-700'
      },
      labelClasses() {
        if (this.isCompleted) {
          return 'text-white'
        }
        if (this.isCurrent) {
          return 'text-magenta-400'
        }
        return 'text-gray-400'
      },
    },
  }
  </script>