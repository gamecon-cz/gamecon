const fs = require('fs');

let less = fs.readFileSync('ui/src/pages/program/program.less', 'utf8');

// Replace only the first instance of position: relative; which is .program
less = less.replace(/\.program {\n(.*?)position: relative;/, '.program {\n$1position: relative;\n  display: grid;\n  grid-template-columns: var(--program-sirka-linie) repeat(96, var(--program-sirka-slotu));\n  row-gap: var(--program-mezera-radku);');
less = less.replace(/border-spacing: 0 var\(--program-mezera-radku\);/g, '');
less = less.replace(/table-layout: fixed;/g, '');
less = less.replace(/border-collapse: separate;/g, '');
less = less.replace(/\.program tr {[\s\S]*?}/, '.program_row { display: contents; } /* no tr */');
less = less.replace(/\.program td\[rowspan\] {/g, '.program_bunka-linie { grid-column: 1; ');
less = less.replace(/\.program td\.program_bunka-aktivita/g, '.program_bunka-aktivita');
less = less.replace(/\.program td /g, '.program ');

fs.writeFileSync('ui/src/pages/program/program.less', less);

