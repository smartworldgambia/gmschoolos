import fs from 'fs';
import path from 'path';
const files = [
  'resources/js/Components/SchoolOS/ReasonAction.jsx','resources/js/Components/SchoolOS/SeniorRoundForm.jsx',
  ...['ExternalExams','SeniorSecondary','Records','Verify'].flatMap(d => fs.readdirSync(`resources/js/Pages/${d}`).map(f => `resources/js/Pages/${d}/${f}`)),
  'resources/js/Pages/School/SeniorApply.jsx',
];
const keys = new Map(); const dyn = new Set(); const enums = new Set();
for (const f of files) {
  const s = fs.readFileSync(f, 'utf8');
  for (const m of s.matchAll(/['"`]((?:acad|import|apply|ext|col|ss|ssa|rec|ver|err|ok|appNav|results|studentStatus)\.[A-Za-z0-9_.${}]+)['"`]/g)) {
    const k = m[1];
    if (k.includes('${')) dyn.add(f.split('/').pop()+': '+k); else { if(!keys.has(k)) keys.set(k, new Set()); keys.get(k).add(f.split('/').pop()); }
  }
  for (const m of s.matchAll(/enumLabel\(t, '([^']+)'/g)) enums.add(m[1]+'  <- '+f.split('/').pop());
}
fs.writeFileSync('storage/app/_keys.json', JSON.stringify({keys:[...keys.keys()].sort(), dyn:[...dyn], enums:[...enums].sort()}, null, 1));
console.log(keys.size, 'static keys');
