import { defineConfig } from 'vite'
import preact from '@preact/preset-vite'
import * as path from 'path'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [preact()],
  build: {
    target: "es6",
    outDir: "./../web/soubory/ui",
    // TODO: výstup půjde i nějak do web/soubory
    // outDir: "./../admin/files/ui",
    emptyOutDir: true,
    cssCodeSplit: false,
    lib: {
      entry: path.resolve(__dirname, 'src/main.ts'),
      name: "script",
      fileName: () => "bundle.js",
      formats: ["iife"]
    },
    rollupOptions: {
      output: {
        // Vite 3+ jinak pojmenuje lib CSS podle "name" v package.json
        // (preact-test.css); PHP ale odkazuje na stabilní style.css.
        assetFileNames: "style.css",
      },
    },
    minify: true,
    sourcemap: true,
  },
  server: {
    proxy: {
      '/api': {
        target: `http://localhost:80/admin/api/`,
      },
    },
    host: true,
  },
})
