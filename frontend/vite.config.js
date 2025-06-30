import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue(),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    }
  },
  server: {
    host: '0.0.0.0', # Listen on all network interfaces
    port: 3000,
    watch: {
      usePolling: true # Needed for Docker environments
    }
  },
  build: {
    outDir: 'dist' # Ensure the output directory is 'dist'
  }
})
