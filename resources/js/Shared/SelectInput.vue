<template>
  <div :class="$attrs.class">
    <label v-if="label" class="form-label" :for="id">{{ label }}:</label>
    <select
      :id="id"
      ref="input"
      v-model="selected"
      v-bind="{ ...$attrs, class: null }"
      class="form-select"
      :class="{ error: error }"
    >
      <slot />
    </select>
    <div v-if="error" class="form-error">{{ error }}</div>
  </div>
</template>

<script>
let selectCounter = 0;

export default {
  inheritAttrs: false,
  props: {
    id: {
      type: String,
      default() {
        selectCounter++;
        return `select-input-${selectCounter}`;
      },
    },
    error: String,
    label: String,
    modelValue: [String, Number, Boolean],
  },
  emits: ['update:modelValue'],
  data() {
    return {
      selected: this.modelValue,
    }
  },
  watch: {
    selected(value) {
      this.$emit('update:modelValue', value)
    },
  },
  methods: {
    focus() {
      this.$refs.input.focus()
    },
    select() {
      this.$refs.input.select()
    },
  },
}
</script>
