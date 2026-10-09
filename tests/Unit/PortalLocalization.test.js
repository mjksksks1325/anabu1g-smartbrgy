import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const script = readFileSync(new URL('../../public/js/portal-localization.js', import.meta.url), 'utf8');
const catalogs = Object.fromEntries(['en', 'fil'].map(locale => [locale, JSON.parse(readFileSync(new URL('../../lang/' + locale + '.json', import.meta.url), 'utf8'))]));

function portal() {
  const elements = [];
  function element(source = null) {
    const attributes = new Map();
    const node = { nodeType: 1, textContent: '', dataset: {}, disabled: false, listeners: {},
      setAttribute(name, value) { attributes.set(name, String(value)); if (name === 'data-portal-i18n') this.dataset.portalI18n = value; },
      getAttribute: name => attributes.get(name), hasAttribute: name => attributes.has(name), removeAttribute: name => attributes.delete(name),
      querySelectorAll: () => [], addEventListener(name, handler) { this.listeners[name] = handler; },
    };
    if (source) { node.setAttribute('data-portal-i18n', source); node.textContent = catalogs.en[source]; }
    elements.push(node);
    return node;
  }
  const label = element('Request a document');
  const live = element();
  const input = element(); input.value = 'Unsaved resident details'; input.setCustomValidity = message => { input.validationMessage = message; }; input.validity = {};
  const password = element(); password.value = 'PrivatePassword123!';
  const file = element(); file.files = [{ name: 'resident.jpg', type: 'image/jpeg' }];
  const token = { content: 'csrf-token' };
  const buttons = ['en', 'fil'].map(locale => { const button = element(); button.dataset.portalLocale = locale; return button; });
  const events = [];
  const calls = [];
  const config = { locale: 'en', catalogs, url: '/portal/locale', csrfUrl: '/portal/csrf-token' };
  const context = vm.createContext({ console, Object, WeakMap, JSON, RegExp,
    window: { PORTAL_I18N: config, location: { href: 'http://localhost/portal/register?continue=1#account' } },
    document: {
      documentElement: { lang: 'en' }, getElementById: () => live,
      querySelector: selector => selector.startsWith('meta') ? token : null,
      querySelectorAll: selector => selector === '[data-portal-locale]' ? buttons : selector === 'input[name="_token"]' ? [] : selector.startsWith('input') ? [input] : elements.filter(node => node.hasAttribute('data-portal-i18n') || node.hasAttribute('data-portal-message')),
      addEventListener() {}, dispatchEvent: event => events.push(event),
    },
    fetch: async (url, options) => { calls.push({ url, options }); return { ok: true, status: 200, json: async () => ({ locale: JSON.parse(options.body).locale }) }; },
    CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
  });
  vm.runInContext(script, context);
  return { context, element, label, input, password, file, buttons, live, calls, events, config };
}

test('language switching preserves form nodes, private values, uploads, page, and query', async () => {
  const { context, label, input, password, file, buttons, calls, events } = portal();
  const selectedFile = file.files[0];
  await context.switchPortalLocale('fil');
  assert.equal(label.textContent, 'Mag-request ng dokumento');
  assert.equal(input.value, 'Unsaved resident details');
  assert.equal(password.value, 'PrivatePassword123!');
  assert.equal(file.files[0], selectedFile);
  assert.equal(context.window.location.href, 'http://localhost/portal/register?continue=1#account');
  assert.equal(context.document.documentElement.lang, 'fil');
  assert.equal(buttons[1].getAttribute('aria-pressed'), 'true');
  assert.deepEqual(JSON.parse(calls[0].options.body), { locale: 'fil' });
  assert.equal(calls[0].options.credentials, 'same-origin');
  assert.equal(events[0].type, 'portal:locale-changed');
  await context.switchPortalLocale('en');
  assert.equal(label.textContent, 'Request a document');
  assert.equal(file.files[0], selectedFile);
});

test('failed or unsupported locale changes keep the current language and form state', async () => {
  const { context, label, input, live, calls, buttons } = portal();
  await context.switchPortalLocale('es');
  assert.equal(calls.length, 0);
  context.fetch = async () => ({ ok: false, status: 422, json: async () => ({ errors: { locale: ['Invalid locale'] } }) });
  await context.switchPortalLocale('fil');
  assert.equal(label.textContent, 'Request a document');
  assert.equal(context.document.documentElement.lang, 'en');
  assert.equal(input.value, 'Unsaved resident details');
  assert.equal(live.textContent, 'Could not change the language. Please try again.');
  assert.equal(buttons[0].disabled, false);
});

test('dynamic errors, status labels and reference messages can switch languages', async () => {
  const { context, element } = portal();
  const status = element();
  const feedback = element();
  context.portalSetText(status, 'Received');
  context.portalSetText(feedback, 'Na-submit ang request :reference.', { reference: 'REQ-UNCHANGED' });
  await context.switchPortalLocale('fil');
  assert.equal(status.textContent, 'Natanggap');
  assert.equal(feedback.textContent, 'Na-submit ang request REQ-UNCHANGED.');
  await context.switchPortalLocale('en');
  assert.equal(status.textContent, 'Received');
  assert.equal(feedback.textContent, 'Request REQ-UNCHANGED was submitted.');
});

test('rendered UI escapes dynamic values and does not treat resident data as translations', async () => {
  const { context, element } = portal();
  const residentName = element(); residentName.textContent = 'Home';
  const html = context.portalHtml('Na-submit ang request :reference.', { reference: '<script>private</script>' });
  assert.ok(html.includes('&lt;script&gt;private&lt;/script&gt;'));
  assert.ok(!html.includes('<script>'));
  await context.switchPortalLocale('fil');
  assert.equal(residentName.textContent, 'Home');
});

test('existing translated server messages can be identified without losing parameters', () => {
  const { context, element, config } = portal();
  config.catalogs = structuredClone(catalogs);
  config.catalogs.en['The :attribute field is required.'] = 'The :attribute field is required.';
  config.catalogs.fil['The :attribute field is required.'] = 'Kailangan ang :attribute.';
  const error = element();
  context.portalSetText(error, 'Kailangan ang buong pangalan.');
  assert.equal(error.textContent, 'The full name field is required.');
});

test('an expired CSRF token is refreshed without reloading or clearing fields', async () => {
  const { context, input, file } = portal();
  const requests = [];
  context.fetch = async (url, options) => {
    requests.push({ url, options });
    if (requests.length === 1) return { status: 419 };
    if (url.endsWith('csrf-token')) return { ok: true, json: async () => ({ token: 'renewed-token' }) };
    return { ok: true, status: 200, json: async () => ({ locale: 'fil' }) };
  };
  await context.switchPortalLocale('fil');
  assert.equal(requests.length, 3);
  assert.equal(requests[2].options.headers['X-CSRF-TOKEN'], 'renewed-token');
  assert.equal(context.document.documentElement.lang, 'fil');
  assert.equal(input.value, 'Unsaved resident details');
  assert.equal(file.files[0].name, 'resident.jpg');
});

test('native validation uses the selected language and leaves the input value alone', async () => {
  const { context, input, config } = portal();
  config.catalogs = structuredClone(catalogs);
  config.catalogs.en['The :attribute field is required.'] = 'The :attribute field is required.';
  config.catalogs.fil['The :attribute field is required.'] = 'Kailangan ang :attribute.';
  input.name = 'full_name';
  input.validity = { valueMissing: true };
  await context.switchPortalLocale('fil');
  assert.equal(input.validationMessage, 'Kailangan ang buong pangalan.');
  assert.equal(input.value, 'Unsaved resident details');
  await context.switchPortalLocale('en');
  assert.equal(input.validationMessage, 'The full name field is required.');
});
