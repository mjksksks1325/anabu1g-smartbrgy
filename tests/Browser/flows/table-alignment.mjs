import path from 'node:path';
import { staffSignInWithTwoFactor } from '../lib/session.mjs';

export const name = 'Centered staff table headers, values and actions';

export async function run({ env, open, check, artifacts }) {
    const page = await open();
    await staffSignInWithTwoFactor(page, env, 'admin@test.invalid');
    for (const route of ['/staff/residents', '/staff/voters', '/staff/incidents', '/staff/document-requests', '/admin/audit', '/staff/resident-applications', '/admin/employee-cabinet-access', '/admin/smart-cabinet', '/staff/rfid-file-tracking']) {
        await page.goto(route);
        await page.waitFor('!!document.querySelector("table th, .audit-table-head > span")');
        for (const [theme, width] of [['light', 1280], ['dark', 320]]) {
            await page.viewport(width, 900, width === 320);
            await page.evaluate(`window.PersonnelNavigation.setTheme(${JSON.stringify(theme)})`);
            const state = await page.evaluate(`(() => {
                const visible = el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden';
                const cells = [...document.querySelectorAll('table th, table td, .audit-table-head > *, .audit-log-row > *')].filter(visible);
                const groups = [...document.querySelectorAll('table td > .resident-actions, table td > .flex, table td > [style*="display:flex"], table td > [style*="display: flex"]')].filter(visible);
                return { count: cells.length, badCells: cells.filter(el => getComputedStyle(el).textAlign !== 'center').map(el => el.textContent.trim().slice(0, 45)), badGroups: groups.filter(el => /flex/.test(getComputedStyle(el).display) && getComputedStyle(el).justifyContent !== 'center').map(el => el.className) };
            })()`);
            check(`${route}: headers, cells and actions are centered in ${theme} at ${width}px`, state.count > 0 && !state.badCells.length && !state.badGroups.length, JSON.stringify(state));
        }
        if (route === '/staff/residents') {
            await page.viewport(1280, 900);
            await page.screenshot(path.join(artifacts, 'resident-table-centered-dark.png'));
        }
    }
    await page.close();
}
