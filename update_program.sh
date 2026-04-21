cp ui/src/pages/program/components/tabulka/ProgramTabulka.tsx ui/src/pages/program/components/tabulka/ProgramTabulka.tsx.bak
cp ui/src/pages/program/components/tabulka/ProgramTabulkaBuňka.tsx ui/src/pages/program/components/tabulka/ProgramTabulkaBuňka.tsx.bak
cp ui/src/pages/program/program.less ui/src/pages/program/program.less.bak

cat << 'INNER_EOF' > patch.js
const fs = require('fs');

// program.less
let less = fs.readFileSync('ui/src/pages/program/program.less', 'utf8');
less = less.replace(/border-spacing: 0 var\(--program-mezera-radku\);/g, '');
less = less.replace(/table-layout: fixed;/g, '');
less = less.replace(/width: max-content;/g, 'width: 100%;');
less = less.replace(/border-collapse: separate;/g, '');
less = less.replace(/\.program {[\s\S]*?position: relative;/, '.program {\n  position: relative;\n  display: grid;\n  grid-template-columns: var(--program-sirka-linie) repeat(96, var(--program-sirka-slotu));\n  row-gap: var(--program-mezera-radku);');
fs.writeFileSync('ui/src/pages/program/program.less', less);

INNER_EOF
node patch.js
