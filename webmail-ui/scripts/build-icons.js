// Builds src/Support/icons.json from lucide-static (ISC license). Run: node scripts/build-icons.js
const fs = require('fs'), path = require('path');
const names = ['inbox','send','file-pen','archive','shield-alert','trash-2','star','mail','mail-open','search','menu','x','paperclip','log-out','sun','moon','pen-line','folder','tag','chevron-left','chevron-right','refresh-cw','settings','users','circle-alert','circle-check','info','reply','reply-all','forward','download','ellipsis','image-off','arrow-left','archive-restore','shield-check','bell'];
const dir = path.join('node_modules','lucide-static','icons');
const out = {};
for (const n of names) {
  const f = path.join(dir, n + '.svg');
  if (!fs.existsSync(f)) { console.error('missing icon', n); continue; }
  out[n] = fs.readFileSync(f,'utf8').replace(/^[\s\S]*?<svg[^>]*>/,'').replace(/<\/svg>\s*$/,'').replace(/\s+/g,' ').trim();
}
fs.writeFileSync('src/Support/icons.json', JSON.stringify(out));
console.log('icons:', Object.keys(out).length);
