<template>
    <teleport to="body">
      <transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="show"
          class="fixed inset-0 z-50 overflow-y-auto"
          @click.self="$emit('close')"
        >
          <!-- Backdrop -->
          <div class="fixed inset-0 bg-black/70 backdrop-blur-sm"></div>
  
          <!-- Modal Content -->
          <div class="flex min-h-full items-center justify-center p-4">
            <transition
              enter-active-class="transition ease-out duration-200"
              enter-from-class="opacity-0 scale-95"
              enter-to-class="opacity-100 scale-100"
              leave-active-class="transition ease-in duration-150"
              leave-from-class="opacity-100 scale-100"
              leave-to-class="opacity-0 scale-95"
            >
              <div
                v-if="show"
                class="relative bg-dark-800 border border-primary-800/30 rounded-xl shadow-2xl"
                :class="maxWidthClass"
              >
                <slot />
              </div>
            </transition>
          </div>
        </div>
      </transition>
    </teleport>
  </template>
  
  <script>
  export default {
    props: {
      show: {
        type: Boolean,
        default: true,
      },
      maxWidth: {
        type: String,
        default: 'md',
      },
    },
    computed: {
      maxWidthClass() {
        const widths = {
          sm: 'max-w-sm',
          md: 'max-w-md',
          lg: 'max-w-lg',
          xl: 'max-w-xl',
          '2xl': 'max-w-2xl',
          '3xl': 'max-w-3xl',
          '4xl': 'max-w-4xl',
        }
        return widths[this.maxWidth] || widths.md
      },
    },
    mounted() {
      document.body.style.overflow = 'hidden'
    },
    beforeUnmount() {
      document.body.style.overflow = null
    },
  }
  </script>