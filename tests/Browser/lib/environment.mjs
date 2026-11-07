// The isolated environment for the browser suite: a temporary folder holding a fresh SQLite database, the
// application's storage and the generated test photos, and a PHP development server pointed at them. Nothing is
// written to the project's database, storage or logs, and mail goes only to the in-memory capture.
import fs from 'node:fs';
import os from 'node:os';
import net from 'node:net';
import path from 'node:path';
import crypto from 'node:crypto';
import { execFileSync, spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

export const PROJECT = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../../..');
const SUPPORT = path.join(PROJECT, 'tests', 'Browser', 'support');
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

async function freePort() {
    const server = net.createServer();
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    const { port } = server.address();
    await new Promise(resolve => server.close(resolve));
    return port;
}

/** PHP_BINARY, else php.exe on the PATH (Windows; Herd's `php` is a .bat Node cannot start), else Herd's newest PHP. */
function php() {
    if (process.env.PHP_BINARY) return process.env.PHP_BINARY;
    if (process.platform !== 'win32') return 'php';
    for (const folder of (process.env.PATH ?? '').split(';')) {
        const candidate = path.join(folder, 'php.exe');
        if (folder && fs.existsSync(candidate)) return candidate;
    }
    const herd = path.join(os.homedir(), '.config', 'herd', 'bin');
    const versions = fs.existsSync(herd) ? fs.readdirSync(herd).filter(name => /^php\d+$/.test(name)).sort().reverse() : [];
    const found = versions.map(version => path.join(herd, version, 'php.exe')).find(candidate => fs.existsSync(candidate));
    if (!found) throw new Error('PHP not found. Set PHP_BINARY to php.exe.');
    return found;
}

/**
 * Create the environment. The PHP server runs with the upload limits the deployment guide requires
 * (12M per file, 16M per form, 256M memory), whatever the machine's php.ini says.
 * @param {{ mailPort: number, keep?: boolean }} options
 */
export async function createEnvironment({ mailPort }) {
    const runDir = fs.mkdtempSync(path.join(os.tmpdir(), 'smartbrgy-browser-'));
    const storage = path.join(runDir, 'storage');
    for (const folder of ['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs']) {
        fs.mkdirSync(path.join(storage, folder), { recursive: true });
    }
    const database = path.join(runDir, 'suite.sqlite');
    fs.writeFileSync(database, '');
    const port = await freePort();
    const base = `http://127.0.0.1:${port}`;
    const env = {
        ...process.env,
        APP_ENV: 'local', APP_URL: base, APP_DEBUG: 'true',
        DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '',
        LARAVEL_STORAGE_PATH: storage, BROWSER_SUITE_DIR: runDir,
        SESSION_DRIVER: 'database', CACHE_STORE: 'database', QUEUE_CONNECTION: 'sync',
        MAIL_MAILER: 'smtp', MAIL_SCHEME: 'smtp', MAIL_HOST: '127.0.0.1', MAIL_PORT: String(mailPort),
        MAIL_USERNAME: '', MAIL_PASSWORD: '', MAIL_FROM_ADDRESS: 'no-reply@test.invalid',
        PRIVILEGED_TWO_FACTOR: 'true', TRUSTED_PROXIES: '',
    };
    const artisan = (...args) => execFileSync(php(), ['artisan', ...args], { cwd: PROJECT, env, encoding: 'utf8' });

    let seed;
    const images = path.join(runDir, 'images');
    try {
        const configured = artisan('config:show', 'database.connections.sqlite.database');
        if (!configured.includes(runDir)) throw new Error(`Refusing to continue: the database is not the suite's own (${configured.trim()}).`);
        artisan('migrate', '--force', '--no-interaction');
        seed = JSON.parse(artisan('tinker', '--execute', `require '${path.join(SUPPORT, 'seed.php').replace(/\\/g, '/')}';`).trim().split('\n').pop());
        execFileSync(php(), ['-d', 'memory_limit=1G', path.join(SUPPORT, 'images.php'), images], { encoding: 'utf8' });
    } catch (error) {
        fs.rmSync(runDir, { recursive: true, force: true });
        throw error;
    }

    const server = spawn(php(), ['-d', 'upload_max_filesize=12M', '-d', 'post_max_size=16M', '-d', 'memory_limit=256M',
        '-S', `127.0.0.1:${port}`, path.join(PROJECT, 'vendor', 'laravel', 'framework', 'src', 'Illuminate', 'Foundation', 'resources', 'server.php')],
    { cwd: path.join(PROJECT, 'public'), env, stdio: 'ignore' });
    for (let attempt = 0; attempt < 60; attempt++) {
        try { if ((await fetch(`${base}/up`)).ok) break; } catch { /* starting */ }
        await sleep(250);
    }

    return {
        runDir, storage, database, base, seed, images,
        /** Read-only query of the suite's database, for checking what a flow stored. */
        query(sql, ...bindings) {
            if (!/^\s*select\b/i.test(sql)) throw new Error('Only SELECT queries are allowed.');
            const code = '$pdo = new PDO("sqlite:" . $argv[1]); $s = $pdo->prepare($argv[2]); $s->execute(array_slice($argv, 3)); echo json_encode($s->fetchAll(PDO::FETCH_ASSOC));';
            return JSON.parse(execFileSync(php(), ['-r', code, database, sql, ...bindings.map(String)], { encoding: 'utf8' }));
        },
        /** The current six-digit code for a two-factor secret (base32), as an authenticator app shows it. */
        totp(secret, offset = 0) {
            const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
            const bits = [...secret.replace(/=+$/, '').toUpperCase()].map(c => alphabet.indexOf(c).toString(2).padStart(5, '0')).join('');
            const key = Buffer.from(bits.match(/.{8}/g).map(b => parseInt(b, 2)));
            const counter = Buffer.alloc(8);
            counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000) + offset));
            const hmac = crypto.createHmac('sha1', key).update(counter).digest();
            const at = hmac[hmac.length - 1] & 0xf;
            return String((hmac.readUInt32BE(at) & 0x7fffffff) % 1000000).padStart(6, '0');
        },
        async close({ keep = false } = {}) {
            server.kill();
            await sleep(500);
            if (!keep) fs.rmSync(runDir, { recursive: true, force: true, maxRetries: 5, retryDelay: 400 });
        },
    };
}
