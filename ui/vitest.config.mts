import { defineConfig } from 'vitest/config'

// Samostatná konfigurace pro testy – nemíchá se s lib/IIFE buildem ve
// vite.config.mts. Bez @preact/preset-vite: testovaná logika (a její importy)
// nepoužívá JSX, takže stačí vestavěný transform Vite, který navíc neřeší unicode
// v identifikátorech/řetězcích jako babel parser z preact presetu.
// jsdom je potřeba, protože env.ts čte `window` už při importu.
export default defineConfig({
  test: {
    environment: 'jsdom',
    include: ['src/**/*.test.ts', 'src/**/*.test.tsx'],
  },
})
