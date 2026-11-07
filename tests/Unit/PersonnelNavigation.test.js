import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const script = readFileSync(new URL('../../public/js/personnel-navigation.js', import.meta.url), 'utf8');

function navigation() {
  const events = new Map();
  const classes = new Set();
  const attributes = new Set();
  const preferences = new Map([['smartbrgy_theme', 'dark']]);
  const styles = ['admin.css', 'figma-admin.css', 'admin-iot.css', 'figma-iot.css', 'government.css', 'app.css', 'staff-ui-Ab12_c.css']
    // Already parsed stylesheets, so the script switches layouts immediately instead of waiting for their load events.
    .map(name => ({ href: `http://localhost/${/^(app|staff-ui)/.test(name) ? 'build/assets' : 'css'}/${name}`, disabled: false, sheet: { cssRules: [{}] }, addEventListener() {} }));
  const context = vm.createContext({
    URL,
    window: { location: { href: 'http://localhost/admin' }, setTimeout: () => 1, clearTimeout() {} },
    localStorage: { getItem: key => preferences.get(key) ?? null, setItem: (key, value) => preferences.set(key, value) },
    document: {
      body: { dataset: { personnelLayout: 'admin' }, classList: { toggle() {} }, hasAttribute: name => attributes.has(name) },
      documentElement: { dataset: {}, classList: {
        contains: name => classes.has(name),
        toggle(name, enabled) { enabled ? classes.add(name) : classes.delete(name); },
      } },
      getElementById: () => null,
      querySelectorAll: selector => (selector.includes('stylesheet') ? styles : []),
      addEventListener: (type, handler) => events.set(type, handler),
    },
  });
  vm.runInContext(script, context);
  return { context, styles, events, attributes, preferences };
}

test('bottom logout opens confirmation without submitting and restores focus when cancelled', () => {
  const { context, events } = navigation();
  let prevented = false;
  let closedMenu = false;
  let focused = false;
  let closeHandler;
  const summary = { focus() { focused = true; } };
  const menu = { querySelector: () => summary, removeAttribute() { closedMenu = true; } };
  const dialog = {
    open: false,
    showModal() { this.open = true; },
    close() { this.open = false; closeHandler(); },
    addEventListener(type, handler) { closeHandler = handler; },
  };
  context.document.getElementById = () => dialog;
  context.document.querySelectorAll = () => [];

  events.get('submit')({ target: { matches: () => true, closest: () => menu }, preventDefault() { prevented = true; } });
  assert.equal(prevented, true);
  assert.equal(closedMenu, true);
  assert.equal(dialog.open, true);

  events.get('click')({ target: { closest: selector => (selector === '[data-logout-cancel]' ? {} : null) } });
  assert.equal(dialog.open, false);
  assert.equal(focused, true);
});

test('logout keeps ordinary form submission for confirmation and when dialogs are unavailable', () => {
  const { context, events } = navigation();
  let prevented = false;
  const preventDefault = () => { prevented = true; };
  context.document.getElementById = () => null;

  events.get('submit')({ target: { matches: () => true }, preventDefault });
  assert.equal(prevented, false);
  events.get('submit')({ target: { matches: () => false }, preventDefault });
  assert.equal(prevented, false);
});

test('layout navigation keeps only the destination styles active and preserves the selected theme', () => {
  const { context, styles } = navigation();
  for (const [layout, expected] of [
    ['admin', [false, false, true, true, false, true, true]],
    ['iot', [true, true, false, false, false, true, true]],
    ['settings', [true, true, true, true, false, false, true]],
    ['admin', [false, false, true, true, false, true, true]],
  ]) {
    context.document.body.dataset.personnelLayout = layout;
    context.window.PersonnelNavigation.syncStyles();
    assert.deepEqual(styles.map(style => style.disabled), expected);
    assert.equal(context.document.documentElement.dataset.theme, 'dark');
  }
});

test('browser history does not replay the login greeting from a cached workspace page', () => {
  const { context, events } = navigation();
  events.get('livewire:navigate')({ detail: { history: true } });
  context.window.PERSONNEL_LOGIN_GREETING = true;
  events.get('livewire:navigated')();
  assert.equal(context.window.PERSONNEL_LOGIN_GREETING, false);

  events.get('livewire:navigate')({ detail: { history: false } });
  context.window.PERSONNEL_LOGIN_GREETING = true;
  events.get('livewire:navigated')();
  assert.equal(context.window.PERSONNEL_LOGIN_GREETING, true);
});

test('navigation restores ordinary page styles outside the personnel layouts', () => {
  const { context, styles } = navigation();
  context.window.PersonnelNavigation.syncStyles();
  delete context.document.body.dataset.personnelLayout;
  context.window.PersonnelNavigation.syncStyles();
  assert.equal(styles[4].disabled, false);
  assert.equal(styles[5].disabled, false);
});

test('the staff UI bundle is active only on workspace pages that opt in, and the theme stays shared with Flux', () => {
  const { context, styles, attributes, preferences } = navigation();
  const staffUi = styles[6];
  for (const [layout, optIn, disabled] of [
    ['iot', true, false],
    ['iot', false, true],
    ['admin', true, false],
    ['settings', true, true],
    ['iot', true, false],
  ]) {
    context.document.body.dataset.personnelLayout = layout;
    optIn ? attributes.add('data-staff-ui') : attributes.delete('data-staff-ui');
    context.window.PersonnelNavigation.syncStyles();
    assert.equal(staffUi.disabled, disabled, `${layout} ${optIn ? 'opted in' : 'not opted in'}`);
    assert.equal(styles[5].disabled, layout !== 'settings', 'the app bundle with preflight stays off the workspace layouts');
  }

  delete context.document.body.dataset.personnelLayout;
  context.window.PersonnelNavigation.syncStyles();
  assert.equal(staffUi.disabled, true);

  context.window.PersonnelNavigation.setTheme('light');
  assert.equal(preferences.get('smartbrgy_theme'), 'light');
  assert.equal(preferences.get('flux.appearance'), 'light');
});

test('the phone menu toggle opens and closes the side navigation and returns focus on Escape', () => {
  const { context, events } = navigation();
  const attributes = { 'aria-controls': 'staff-side-nav', 'aria-expanded': 'false' };
  const navClasses = new Set();
  let focused = null;
  const link = { focus() { focused = 'first link'; } };
  const toggle = {
    getAttribute: name => attributes[name],
    setAttribute: (name, value) => { attributes[name] = value; },
    focus() { focused = 'toggle'; },
  };
  const nav = { classList: { toggle(name, on) { on ? navClasses.add(name) : navClasses.delete(name); } }, querySelector: () => link };
  context.document.querySelector = selector => {
    if (selector === '[data-staff-nav-toggle]') return toggle;
    if (selector === '[data-staff-nav-toggle][aria-expanded="true"]') return attributes['aria-expanded'] === 'true' ? toggle : null;
    return null;
  };
  context.document.getElementById = id => (id === 'staff-side-nav' ? nav : null);
  const click = target => events.get('click')({ target: { closest: selector => target(selector) } });

  click(selector => (selector === '[data-staff-nav-toggle]' ? toggle : null));
  assert.equal(attributes['aria-expanded'], 'true');
  assert.ok(navClasses.has('is-open'));
  assert.equal(focused, 'first link');

  events.get('keydown')({ key: 'Escape' });
  assert.equal(attributes['aria-expanded'], 'false');
  assert.ok(!navClasses.has('is-open'));
  assert.equal(focused, 'toggle');

  click(selector => (selector === '[data-staff-nav-toggle]' ? toggle : null));
  click(selector => (selector === '#staff-side-nav a[href]' ? link : null));
  assert.equal(attributes['aria-expanded'], 'false', 'choosing a link closes the menu');
});
