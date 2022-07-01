import { defineConfig } from 'vite'
import preact from '@preact/preset-vite'
import * as path from 'path'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [preact()],
  build: {
    target: "es6",
    outDir: "./../web/soubory/ui",
    emptyOutDir: true,
    lib: {
      entry: path.resolve(__dirname, 'src/main.tsx'),
      name: "script",
      fileName: () => "bundle.js",
      formats: ["iife"]
    },
    minify: true,
    sourcemap: true,
  },
  server: {
    proxy: {
      '/api': {
        target: `http://localhost:80/web/`,
      },
    },
    host: true,
  },
})
