import { defineConfig } from 'vitest/config'

// Samostatná konfigurace pro testy – nemíchá se s lib/IIFE buildem ve vite.config.ts.
// Bez @preact/preset-vite: babel parser z presetu neřeší unicode v identifikátorech,
// proto JSX komponentových testů překládá esbuild (tsconfig má `preserve`).
// jsdom je potřeba, protože env.ts čte `window` už při importu.
export default defineConfig({
  esbuild: {
    jsx: 'automatic',
    jsxImportSource: 'preact',
  },
  test: {
    environment: 'jsdom',
    include: ['src/**/*.test.ts', 'src/**/*.test.tsx'],
  },
})
