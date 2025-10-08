<template>
  <img :src="logoUrl" :width="width" :height="height" alt="Logo" />
</template>

<script>
export default {
  props: {
    width: {
      type: [Number, String],
      default: 120
    },
    height: {
      type: [Number, String],
      default: 28
    }
  },
  computed: {
    logoUrl() {
      return '/images/RGGLogo.png'
    }
  }
}
</script>