import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const script = readFileSync(new URL('../../public/js/personnel-navigation.js', import.meta.url), 'utf8');

function navigation() {
  const events = new Map();
  const classes = new Set();
  const styles = ['admin.css', 'figma-admin.css', 'admin-iot.css', 'figma-iot.css', 'government.css', 'app.css']
    .map(name => ({ href: `http://localhost/${name === 'app.css' ? 'build/assets' : 'css'}/${name}`, disabled: false }));
  const context = vm.createContext({
    URL,
    window: { location: { href: 'http://localhost/admin' } },
    localStorage: { getItem: () => 'dark' },
    document: {
      body: { dataset: { personnelLayout: 'admin' }, classList: { toggle() {} } },
      documentElement: { dataset: {}, classList: {
        contains: name => classes.has(name),
        toggle(name, enabled) { enabled ? classes.add(name) : classes.delete(name); },
      } },
      querySelectorAll: () => styles,
      addEventListener: (type, handler) => events.set(type, handler),
    },
  });
  vm.runInContext(script, context);
  return { context, styles, events };
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

  events.get('click')({ target: { closest: () => true } });
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
    ['admin', [false, false, true, true, false, true]],
    ['iot', [true, true, false, false, false, true]],
    ['settings', [true, true, true, true, false, false]],
    ['admin', [false, false, true, true, false, true]],
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
