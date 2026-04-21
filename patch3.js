const fs = require('fs');

let less = fs.readFileSync('ui/src/pages/program/program.less', 'utf8');

less = less.replace(/\.program {([\s\S]*?)position: relative;/m, '.program {$1position: relative;\n  display: grid;\n  grid-template-columns: var(--program-sirka-linie) repeat(96, var(--program-sirka-slotu));\n  row-gap: var(--program-mezera-radku);');

fs.writeFileSync('ui/src/pages/program/program.less', less);

