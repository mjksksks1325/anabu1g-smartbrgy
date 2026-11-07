// Signing in as a resident or a staff member through the real forms.
const used = new Set();

export async function residentSignIn(page, email, password) {
    await page.goto('/portal/login');
    await page.submit(`(() => { document.getElementById('resident-email').value = ${JSON.stringify(email)}; document.getElementById('resident-password').value = ${JSON.stringify(password)}; document.getElementById('resident-email').form.requestSubmit(); })()`);
    return page.evaluate('location.pathname');
}

export async function staffSignIn(page, email, password) {
    await page.goto('/staff/login');
    return page.evaluate(`(async () => { const form = document.querySelector('form[action$="/staff/login"]'); const data = new FormData(form); data.set('email', ${JSON.stringify(email)}); data.set('password', ${JSON.stringify(password)}); const response = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin' }); return new URL(response.url).pathname; })()`);
}

/**
 * Sign in a staff member who uses two-factor authentication. Fortify refuses a code used before, so when this
 * window's code was already used (another flow signed in a moment ago), the next window's code is tried, and failing
 * that the helper waits for a fresh window.
 */
export async function staffSignInWithTwoFactor(page, env, email) {
    await staffSignIn(page, email, env.seed.staffPassword);
    for (let attempt = 0; attempt < 3; attempt++) {
        const offset = attempt === 0 ? 0 : 1;
        const code = env.totp(env.seed.twoFactorSecret, offset);
        if (used.has(code)) {
            if (attempt === 2) await page.sleep(31000);
            continue;
        }
        used.add(code);
        await page.goto('/two-factor-challenge');
        const landed = await page.evaluate(`(async () => { const form = document.querySelector('form[action$="/two-factor-challenge"]'); if (!form) return location.pathname; const data = new FormData(form); data.set('code', ${JSON.stringify(code)}); const response = await fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin' }); return new URL(response.url).pathname; })()`);
        if (!/two-factor-challenge|login/.test(landed)) return landed;
        await staffSignIn(page, email, env.seed.staffPassword);
    }
    throw new Error(`Two-factor sign-in failed for ${email}`);
}

/** The text of every notice, validation message and status line on the page. */
export function messages(page) {
    return page.evaluate(`[...document.querySelectorAll('[role=status], [role=alert], .alert, .field-error, [data-portal-message], [data-flux-error]')].filter(e => !e.hidden).map(e => e.textContent.trim().replace(/\\s+/g, ' ')).filter(Boolean).join(' | ')`);
}
