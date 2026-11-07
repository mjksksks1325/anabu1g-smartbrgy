// A small Chrome DevTools Protocol driver for headless Microsoft Edge or Google Chrome. It needs no npm packages:
// Node 20 (run with --experimental-websocket) talks to the browser's debugging port directly.
import fs from 'node:fs';
import path from 'node:path';
import { spawn } from 'node:child_process';

const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));

export function findBrowser() {
    const candidates = [
        process.env.BROWSER_PATH,
        'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
        'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
        'C:/Program Files/Google/Chrome/Application/chrome.exe',
        '/usr/bin/microsoft-edge', '/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser',
        '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge', '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    ].filter(Boolean);
    const found = candidates.find(candidate => fs.existsSync(candidate));
    if (!found) throw new Error('No Edge or Chrome found. Set BROWSER_PATH to the browser executable.');
    return found;
}

let nextPort = 9600 + Math.floor(Math.random() * 300);

/**
 * Open a new, empty browser profile (no cookies) pointed at the suite's server.
 * @param {{ base: string, runDir: string, width?: number, height?: number }} options
 */
export async function openBrowser({ base, runDir, width = 1280, height = 900 }) {
    const port = nextPort++;
    const profile = path.join(runDir, 'profiles', `p${port}`);
    fs.mkdirSync(profile, { recursive: true });
    const browser = spawn(findBrowser(), [
        '--headless=new', `--remote-debugging-port=${port}`, `--user-data-dir=${profile}`, '--no-first-run',
        '--disable-extensions', '--disable-sync', `--window-size=${width},${height}`, 'about:blank',
    ], { stdio: 'ignore' });
    let targets = [];
    for (let attempt = 0; attempt < 60 && !targets.some(t => t.type === 'page'); attempt++) {
        try { targets = await (await fetch(`http://127.0.0.1:${port}/json/list`)).json(); } catch { /* still starting */ }
        if (!targets.some(t => t.type === 'page')) await sleep(250);
    }
    const target = targets.find(t => t.type === 'page');
    if (!target) throw new Error('The browser did not start.');
    const socket = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise(resolve => socket.addEventListener('open', resolve, { once: true }));

    let id = 0;
    const pending = new Map();
    const listeners = [];
    const problems = [];
    socket.addEventListener('message', ({ data }) => {
        const message = JSON.parse(data);
        if (message.id && pending.has(message.id)) {
            const { resolve, reject } = pending.get(message.id);
            pending.delete(message.id);
            message.error ? reject(new Error(JSON.stringify(message.error))) : resolve(message.result);
            return;
        }
        if (message.method === 'Runtime.exceptionThrown') problems.push(`exception: ${message.params.exceptionDetails.exception?.description ?? message.params.exceptionDetails.text}`);
        if (message.method === 'Runtime.consoleAPICalled' && message.params.type === 'error') problems.push(`console: ${message.params.args.map(a => a.value ?? a.description).join(' ')}`);
        listeners.forEach(listener => listener(message));
    });
    const send = (method, params = {}) => new Promise((resolve, reject) => {
        const messageId = ++id;
        pending.set(messageId, { resolve, reject });
        socket.send(JSON.stringify({ id: messageId, method, params }));
    });
    const waitForEvent = (method, timeout = 45000) => new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error(`timeout waiting for ${method}`)), timeout);
        const listener = message => {
            if (message.method !== method) return;
            clearTimeout(timer);
            listeners.splice(listeners.indexOf(listener), 1);
            resolve(message.params);
        };
        listeners.push(listener);
    });
    await send('Page.enable');
    await send('Runtime.enable');
    await send('DOM.enable');
    await send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: false });

    const evaluate = async expression => {
        const result = await send('Runtime.evaluate', { expression, awaitPromise: true, returnByValue: true });
        if (result.exceptionDetails) throw new Error(`evaluate failed: ${result.exceptionDetails.exception?.description ?? result.exceptionDetails.text}`);
        return result.result.value;
    };

    return {
        send, evaluate, problems, sleep,
        /** Load a page of the suite's server (a path) or any URL, and wait for it. */
        async goto(pathOrUrl) {
            const loaded = waitForEvent('Page.loadEventFired');
            loaded.catch(() => {});
            await send('Page.navigate', { url: /^https?:/.test(pathOrUrl) ? pathOrUrl : base + pathOrUrl });
            await loaded;
            await sleep(400);
        },
        /** Run an expression that submits a form or follows a link, and wait for the next page. */
        async submit(expression) {
            const loaded = waitForEvent('Page.loadEventFired');
            loaded.catch(() => {});
            await evaluate(expression);
            await loaded;
            await sleep(400);
        },
        async waitFor(expression, timeout = 15000) {
            const started = Date.now();
            while (Date.now() - started < timeout) {
                if (await evaluate(`Boolean(${expression})`)) return true;
                await sleep(150);
            }
            throw new Error(`timeout waiting for: ${expression}`);
        },
        async viewport(viewWidth, viewHeight, mobile = false) {
            await send('Emulation.setDeviceMetricsOverride', { width: viewWidth, height: viewHeight, deviceScaleFactor: 1, mobile });
            await sleep(250);
        },
        /** Choose a file in an <input type="file"> as a person would. */
        async setFile(selector, file) {
            const { root } = await send('DOM.getDocument', { depth: 0 });
            const { nodeId } = await send('DOM.querySelector', { nodeId: root.nodeId, selector });
            if (!nodeId) throw new Error(`no file input ${selector}`);
            await send('DOM.setFileInputFiles', { nodeId, files: [file] });
            await sleep(800);
        },
        async click(selector) {
            const box = await evaluate(`(() => { const el = document.querySelector(${JSON.stringify(selector)}); if (!el) return null; el.scrollIntoView({ block: 'center' }); const r = el.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; })()`);
            if (!box) throw new Error(`no element for ${selector}`);
            await sleep(150);
            for (const type of ['mousePressed', 'mouseReleased']) await send('Input.dispatchMouseEvent', { type, x: box.x, y: box.y, button: 'left', clickCount: 1 });
            await sleep(300);
        },
        async key(key, { shift = false } = {}) {
            const codes = { Tab: 9, Enter: 13, Escape: 27 };
            const base = { key, code: key, windowsVirtualKeyCode: codes[key], nativeVirtualKeyCode: codes[key], modifiers: shift ? 8 : 0 };
            await send('Input.dispatchKeyEvent', { type: 'keyDown', ...base });
            await send('Input.dispatchKeyEvent', { type: 'keyUp', ...base });
            await sleep(120);
        },
        async screenshot(file, fullPage = false) {
            const { data } = await send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: fullPage });
            fs.writeFileSync(file, Buffer.from(data, 'base64'));
        },
        async pdf(file) {
            const { data } = await send('Page.printToPDF', { preferCSSPageSize: true, printBackground: true });
            const bytes = Buffer.from(data, 'base64');
            fs.writeFileSync(file, bytes);
            return (bytes.toString('latin1').match(/\/Type\s*\/Page[^s]/g) ?? []).length;
        },
        async close() {
            try { socket.close(); } catch { /* already closed */ }
            browser.kill();
        },
    };
}
