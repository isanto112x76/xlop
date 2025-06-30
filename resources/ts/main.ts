import { createApp } from 'vue'

import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'

// TinyMCE CSS for both light and dark themes:
import '@core-scss/template/index.scss'
import '@styles/styles.scss'
import 'animate.css' // <-- DODAJ TĘ LINIĘ

// Create vue app
const app = createApp(App)

// Register plugins
registerPlugins(app)

// Mount vue app
app.mount('#app')
