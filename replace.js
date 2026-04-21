const fs = require('fs');

const content = fs.readFileSync('/home/antylopa/.vscode-server/data/User/workspaceStorage/4c718960985eb307d09bc079fe9d133a/GitHub.copilot-chat/chat-session-resources/d3d1bb42-4c87-4f56-b1e1-82e22b05bddd/call_MHx4T2RLOGtDamFIVXN3aVRtM3E__vscode-1776727000709/content.txt', 'utf8');

const tbx = content.split('```tsx')[1].split('```')[0].trim();
fs.writeFileSync('ui/src/pages/program/components/tabulka/ProgramTabulka.tsx', tbx);

const tbbx = content.split('```tsx')[2].split('```')[0].trim();
fs.writeFileSync('ui/src/pages/program/components/tabulka/ProgramTabulkaBuňka.tsx', tbbx);

