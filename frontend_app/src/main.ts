import { createPinia } from 'pinia'
import { createApp } from 'vue'

import App from '@/App.vue'
import { router } from '@/router'
import '@/styles.css'
import '@/analytics.css'
import '@/analytics-refinements.css'
import '@/statistics.css'
import '@/notifications.css'

createApp(App).use(createPinia()).use(router).mount('#app')
