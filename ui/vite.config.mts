import { build, defineConfig, loadEnv, type Plugin } from 'vite'
import preact from '@preact/preset-vite'
import { readdirSync } from 'node:fs'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Dev proxy musí mířit tam, kam compose publikuje web: stejná proměnná, stejný výchozí port 80.
 * Čte ji z prostředí a z kořenových `.env*` souborů; compose čte jen `.env`.
 *
 * @see ../docker-compose.yml
 */
const webPort = (mode: string) =>
  loadEnv(mode, fileURLToPath(new URL('..', import.meta.url)), 'WEB_HTTP_').WEB_HTTP_HOST_MAPPING_PORT || '80'

const ZDROJE_EDITORU_TEXTU = fileURLToPath(new URL('./src/editorTextu', import.meta.url))

/**
 * Editor textu v administraci (CodeMirror, src/editorTextu) je vlastní soubor `editor-textu.js` vedle `bundle.js`:
 * potřebují ho jen stránky administrace s textem v Markdownu a v `bundle.js` by ho stahoval každý, kdo otevře program.
 * Formát IIFE neumí víc vstupů ani dynamický import, proto po hlavním buildu proběhne druhý do téhož `outDir`. Tak
 * platí i `--outDir` (make check-ui-bundles, build:admin) a `--watch` (build:watch) bez dalších skriptů.
 */
const editorTextu = (): Plugin => {
  let outDir = ''
  let mode = 'production'
  let sleduje = false

  return {
    name: 'gamecon-editor-textu',
    apply: 'build',
    configResolved(config) {
      outDir = config.build.outDir
      mode = config.mode
      sleduje = config.build.watch !== null
    },
    buildStart() {
      if (!sleduje) {
        return
      }
      // změna editoru tak spustí hlavní build a po něm i ten jeho
      for (const soubor of readdirSync(ZDROJE_EDITORU_TEXTU, { recursive: true, encoding: 'utf8' })) {
        this.addWatchFile(join(ZDROJE_EDITORU_TEXTU, soubor))
      }
    },
    async closeBundle() {
      await build({
        configFile: false,
        root: fileURLToPath(new URL('.', import.meta.url)),
        mode,
        logLevel: 'warn',
        publicDir: false,
        build: {
          target: 'es2020',
          outDir,
          emptyOutDir: false,
          minify: true,
          sourcemap: true,
          lib: {
            entry: join(ZDROJE_EDITORU_TEXTU, 'main.ts'),
            name: 'editorTextu',
            fileName: () => 'editor-textu.js',
            formats: ['iife'],
          },
        },
      })
    },
  }
}

// https://vitejs.dev/config/
export default defineConfig(({ mode }) => ({
  plugins: [preact(), editorTextu()],
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
