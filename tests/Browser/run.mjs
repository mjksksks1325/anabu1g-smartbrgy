// Smart Barangay browser regression suite. See tests/Browser/README.md.
//
//   node --experimental-websocket tests/Browser/run.mjs [--only=certificates,two-factor] [--keep]
//
// Builds a throwaway environment (fresh SQLite database, isolated storage, local mail capture, PHP server), runs the
// flows in a headless browser, prints one line per check and exits with 1 if any check failed.
import fs from 'node:fs';
import path from 'node:path';
import { startMailCapture } from './lib/mail.mjs';
import { createEnvironment } from './lib/environment.mjs';
import { openBrowser } from './lib/browser.mjs';

const FLOWS = [
    'resident-registration', 'password-reset', 'two-factor', 'resident-application', 'document-request',
    'certificates', 'resident-records', 'vawc-restrictions', 'sidebar-navigation', 'request-rate-limits',
];
// A stray browser event must not end the run before the environment is cleaned up.
process.on('unhandledRejection', error => console.error(`  (ignored: ${error?.message ?? error})`));
const args = process.argv.slice(2);
const only = args.find(arg => arg.startsWith('--only='))?.slice(7).split(',').filter(Boolean);
const keep = args.includes('--keep');
const selected = only ? FLOWS.filter(flow => only.includes(flow)) : FLOWS;
if (only && selected.length !== only.length) {
    console.error(`Unknown flow. Choose from: ${FLOWS.join(', ')}`);
    process.exit(2);
}

const mail = await startMailCapture();
console.log('Preparing an isolated environment (fresh database, storage and mail capture)...');
let env;
try {
    env = await createEnvironment({ mailPort: mail.port });
} catch (error) {
    await mail.close();
    console.error(`Could not prepare the environment: ${error.stderr || error.message}`);
    process.exit(2);
}
const artifacts = path.join(env.runDir, 'artifacts');
fs.mkdirSync(artifacts, { recursive: true });
console.log(`Server ${env.base}  ·  folder ${env.runDir}\n`);

const results = [];
const browsers = [];
let failed = false;
try {
    for (const flow of selected) {
        let module;
        try {
            module = await import(`./flows/${flow}.mjs`);
        } catch (error) {
            results.push({ flow, label: 'the flow loads', ok: false, detail: error.message });
            console.log(`▶ ${flow}\n  FAIL  the flow could not be loaded  — ${error.message}`);
            continue;
        }
        console.log(`▶ ${module.name}`);
        const check = (label, ok, detail = '') => {
            results.push({ flow, label, ok: Boolean(ok), detail: String(detail ?? '') });
            console.log(`  ${ok ? 'PASS' : 'FAIL'}  ${label}${!ok && detail ? `  — ${String(detail).slice(0, 240)}` : ''}`);
        };
        const open = async (options = {}) => {
            const browser = await openBrowser({ base: env.base, runDir: env.runDir, ...options });
            browsers.push(browser);
            return browser;
        };
        try {
            await module.run({ env, mail, open, check, artifacts });
        } catch (error) {
            check('the flow ran to the end', false, error.message);
        }
        const problems = browsers.flatMap(browser => browser.problems.splice(0)).filter(problem => !/favicon|_boost|status of (401|403|404|413|419|422|429)/.test(problem));
        if (problems.length) check('no script errors in the browser', false, problems.slice(0, 3).join(' | '));
        browsers.splice(0).forEach(browser => browser.close());
    }
} finally {
    browsers.forEach(browser => browser.close());
    const passed = results.filter(result => result.ok).length;
    failed = results.some(result => !result.ok);
    fs.writeFileSync(path.join(artifacts, 'results.json'), JSON.stringify(results, null, 2));
    console.log(`\n${passed} passed, ${results.length - passed} failed  (${selected.length} flow${selected.length === 1 ? '' : 's'})`);
    if (keep) console.log(`Kept for inspection: ${env.runDir}`);
    await mail.close();
    await env.close({ keep });
}
process.exit(failed ? 1 : 0);
