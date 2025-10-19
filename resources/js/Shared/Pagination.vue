<template>
  <div v-if="links.length > 3">
    <div class="flex flex-wrap -mb-1">
      <template v-for="(link, key) in links">
        <div
          v-if="link.url === null"
          :key="key"
          class="mb-1 mr-1 px-3 py-2 text-gray-400 text-xs leading-4 border rounded"
          v-html="link.label"
        />
        <Link
          v-else
          :key="`link-${key}`"
          class="mb-1 mr-1 px-3 py-2 focus:text-indigo-500 text-xs leading-4 hover:bg-primary-400/30 border focus:border-indigo-500 rounded"
          :class="{ 'bg-primary-400/30': link.active }"
          :href="link.url"
          v-html="link.label"
        />
      </template>
    </div>
  </div>
</template>


<script>
import { Link } from '@inertiajs/vue3'

export default {
  components: {
    Link,
  },
  props: {
    links: Array,
  },
}
</script>
