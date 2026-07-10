import './assets/main.css'
import 'sweetalert2/dist/sweetalert2.min.css'

import { createApp } from 'vue'
import { createPinia } from 'pinia'

import App from './App.vue'
import router from './router/index'

const app = createApp(App)

app.use(createPinia())
app.use(router)

app.mount('#app')