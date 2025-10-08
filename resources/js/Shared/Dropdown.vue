<template>
  <button type="button" @click="toggleDropdown" ref="button">
    <slot />
    <teleport v-if="show" to="body">
      <div>
        <!-- backdrop to close dropdown -->
        <div
          style="position: fixed; top: 0; right: 0; left: 0; bottom: 0; z-index: 99998; background: black; opacity: 0.2"
          @click="closeDropdown"
        />
        <!-- dropdown -->
        <div
          ref="dropdown"
          :style="dropdownStyles"
          @click.stop="!autoClose || toggleDropdown()"
        >
          <slot name="dropdown" />
        </div>
      </div>
    </teleport>
  </button>
</template>

<script>
export default {
  props: {
    placement: {
      type: String,
      default: 'bottom-end', // only supports bottom-left / bottom-right in this native version
    },
    autoClose: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    return {
      show: false,
      dropdownStyles: {
        position: 'absolute',
        top: '0px',
        left: '0px',
        zIndex: 99999,
      },
    }
  },
  methods: {
    toggleDropdown() {
      this.show = !this.show
      if (this.show) {
        this.$nextTick(() => {
          this.setDropdownPosition()
        })
      }
    },
    closeDropdown() {
      this.show = false
    },
    setDropdownPosition() {
      const button = this.$refs.button
      const dropdown = this.$refs.dropdown

      if (!button || !dropdown) return

      const rect = button.getBoundingClientRect()

      let top = rect.bottom + window.scrollY
      let left = rect.left + window.scrollX

      // handle simple placement logic
      if (this.placement.endsWith('end')) {
        left = rect.right - dropdown.offsetWidth + window.scrollX
      }

      this.dropdownStyles = {
        ...this.dropdownStyles,
        top: `${top}px`,
        left: `${left}px`,
      }
    },
    handleEscape(e) {
      if (e.key === 'Escape') this.show = false
    },
  },
  mounted() {
    document.addEventListener('keydown', this.handleEscape)
  },
  beforeUnmount() {
    document.removeEventListener('keydown', this.handleEscape)
  },
}
</script>
