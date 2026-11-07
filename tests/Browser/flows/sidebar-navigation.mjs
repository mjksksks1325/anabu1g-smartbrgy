// Moving through the staff sidebar keeps the neutral page background and the menu's scroll position, and Back opens
// the right workspace screen.
import { staffSignInWithTwoFactor } from '../lib/session.mjs';

export const name = 'Staff sidebar navigation';

const state = `(() => {
    const scroller = [...document.querySelectorAll('#app .sidebar-scroll, #staff-side-nav .side-nav-scroll')].find(e => e.scrollHeight > e.clientHeight + 2);
    const surface = document.querySelector('#main') ?? document.querySelector('#app .content.active');
    return { path: location.pathname, scroll: scroller ? Math.round(scroller.scrollTop) : null, background: surface ? getComputedStyle(surface).backgroundColor : null,
        screen: document.querySelector('#app .content.active')?.id ?? null, current: document.querySelector('[aria-current="page"]')?.textContent.trim() ?? null };
})()`;

/** Scroll the menu so the item is in view, note where the menu stands, click the item and wait for the next address. */
async function follow(page, label, menuScroll = null) {
    const before = await page.evaluate(`(() => {
        const scroller = document.querySelector('#app .sidebar-scroll, #staff-side-nav .side-nav-scroll');
        if (scroller && ${menuScroll !== null}) scroller.scrollTop = ${menuScroll ?? 0};
        const link = [...document.querySelectorAll('#app .sidebar a, #staff-side-nav a')].find(a => a.textContent.trim() === ${JSON.stringify(label)} && a.getClientRects().length);
        link.scrollIntoView({ block: 'nearest' });
        const r = link.getBoundingClientRect();
        return { path: location.pathname, scroll: scroller ? Math.round(scroller.scrollTop) : null, x: r.left + r.width / 2, y: r.top + r.height / 2 };
    })()`);
    await page.sleep(300);
    for (const type of ['mousePressed', 'mouseReleased']) await page.send('Input.dispatchMouseEvent', { type, x: before.x, y: before.y, button: 'left', clickCount: 1 });
    await page.waitFor(`location.pathname !== ${JSON.stringify(before.path)}`, 20000).catch(() => {});
    await page.sleep(1800);
    return { before, after: await page.evaluate(state) };
}

export async function run({ env, open, check }) {
    const page = await open({ width: 1440, height: 760 });
    await staffSignInWithTwoFactor(page, env, 'admin@test.invalid');
    await page.goto('/admin');
    await page.sleep(1000);

    const incidents = await follow(page, 'Incident Reports');
    check('Incident Reports opens in the workspace', incidents.after.path === '/staff/incidents' && incidents.after.screen === 'screen-incidents', JSON.stringify(incidents.after));
    const announcements = await follow(page, 'Announcements', 220);
    check('Announcements reached through the sidebar keeps the neutral page background', announcements.after.path === '/staff/announcements' && announcements.after.background === 'rgb(238, 242, 239)', JSON.stringify(announcements.after));
    check('the menu keeps its scroll position across the page change', announcements.before.scroll > 0 && Math.abs(announcements.after.scroll - announcements.before.scroll) <= 2, `${announcements.before.scroll} -> ${announcements.after.scroll}`);
    const rfid = await follow(page, 'RFID File Tracking');
    check('RFID File Tracking keeps the neutral page background', rfid.after.path === '/staff/rfid-file-tracking' && rfid.after.background === 'rgb(238, 242, 239)', JSON.stringify(rfid.after));

    await page.evaluate('history.back()');
    await page.waitFor(`location.pathname === '/staff/announcements'`, 15000).catch(() => {});
    await page.sleep(1500);
    await page.evaluate('history.back()');
    await page.waitFor(`location.pathname === '/staff/incidents'`, 15000).catch(() => {});
    await page.sleep(2200);
    const back = await page.evaluate(state);
    check('Back returns to the workspace screen its address names', back.path === '/staff/incidents' && back.screen === 'screen-incidents' && back.current === 'Incident Reports', JSON.stringify(back));
    await page.close();
}
