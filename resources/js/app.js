import '../css/app.css'

import { createApp, h } from 'vue'
import { createInertiaApp } from '@inertiajs/vue3'
import VueApexCharts from 'vue3-apexcharts'

// Add global CSRF refresh function
window.refreshCsrfToken = async function() {
  try {
    await fetch('/sanctum/csrf-cookie', {
      credentials: 'same-origin'
    })
    await new Promise(resolve => setTimeout(resolve, 100))
  } catch (e) {
    console.error('Failed to refresh CSRF:', e)
  }
}

createInertiaApp({
  resolve: name => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
    return pages[`./Pages/${name}.vue`]
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(VueApexCharts)
      .mount(el)
  },
})