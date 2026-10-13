import { defineConfig, loadEnv } from 'vite'
import preact from '@preact/preset-vite'
import { fileURLToPath } from 'node:url'

/**
 * Dev proxy musí mířit tam, kam compose publikuje web: stejná proměnná, stejný výchozí port 80.
 * Čte ji z prostředí a z kořenových `.env*` souborů; compose čte jen `.env`.
 *
 * @see ../docker-compose.yml
 */
const webPort = (mode: string) =>
  loadEnv(mode, fileURLToPath(new URL('..', import.meta.url)), 'WEB_HTTP_').WEB_HTTP_HOST_MAPPING_PORT || '80'

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => ({
  plugins: [preact()],
  // V lib/IIFE buildu Vite nenahrazuje `process.env.*` – nechává je na
  // downstream bundleru. Tenhle bundle ale běží přímo v prohlížeči, kde
  // `process` neexistuje, takže závislosti čtoucí `process.env.NODE_ENV`
  // (zustand devtools, immer, preact) jinak shodí celý program hláškou
  // "process is not defined". Proto je nahrazujeme staticky při buildu.
  define: {
    "process.env.NODE_ENV": JSON.stringify(mode === "development" ? "development" : "production"),
    "process.env": "{}",
  },
  build: {
    target: "es6",
    outDir: "./../web/soubory/ui",
    // TODO: výstup půjde i nějak do web/soubory
    // outDir: "./../admin/files/ui",
    emptyOutDir: true,
    cssCodeSplit: false,
    lib: {
      entry: fileURLToPath(new URL('./src/main.ts', import.meta.url)),
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
        target: `http://localhost:${webPort(mode)}/admin/api/`,
      },
    },
    host: true,
  },
}))
