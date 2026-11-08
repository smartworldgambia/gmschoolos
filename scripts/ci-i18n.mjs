#!/usr/bin/env node
// Local stand-in for the GitHub Actions i18n job. Exit non-zero on the first failing gate.
import { spawnSync } from 'child_process';

const gates = [
    'scripts/check-phase6-i18n.mjs',
    'scripts/check-phase7-i18n.mjs',
    'scripts/check-phase8-i18n.mjs',
    'scripts/check-phase9-i18n.mjs',
    'scripts/check-phase10-i18n.mjs',
];

let bad = 0;
for (const gate of gates) {
    const r = spawnSync(process.execPath, [gate], { stdio: 'inherit' });
    if (r.status !== 0) {
        bad++;
        console.error(`FAILED: ${gate}`);
    }
}
process.exit(bad ? 1 : 0);
