import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFileSync } from 'node:fs';

const script = readFileSync(new URL('../../public/js/staff-settings-theme.js', import.meta.url), 'utf8');

for (const [savedTheme, expectedTheme] of [[null, 'light'], ['light', 'light'], ['dark', 'dark']]) {
    test(`staff settings uses ${expectedTheme} when the website theme is ${savedTheme ?? 'unset'}`, () => {
        const preferences = new Map();
        if (savedTheme !== null) preferences.set('smartbrgy_theme', savedTheme);

        vm.runInNewContext(script, {
            localStorage: {
                getItem: key => preferences.get(key) ?? null,
                setItem: (key, value) => preferences.set(key, value),
            },
        });

        assert.equal(preferences.get('flux.appearance'), expectedTheme);
    });
}

test('settings theme button updates the shared theme and its label', () => {
    const preferences = new Map([['smartbrgy_theme', 'light']]);
    const handlers = new Map();
    const button = { textContent: '' };
    const applied = [];
    const document = {
        getElementById: () => button,
        documentElement: { classList: { toggle() {}, contains: () => false } },
        addEventListener: (name, handler) => handlers.set(name, handler),
    };

    vm.runInNewContext(script, {
        document,
        window: { Flux: { applyAppearance: theme => applied.push(theme) } },
        localStorage: {
            getItem: key => preferences.get(key) ?? null,
            setItem: (key, value) => preferences.set(key, value),
        },
    });

    handlers.get('DOMContentLoaded')();
    assert.equal(button.textContent, 'Dark Mode');
    handlers.get('click')({ target: { closest: () => button } });
    assert.equal(preferences.get('smartbrgy_theme'), 'dark');
    assert.equal(button.textContent, 'Light Mode');
    assert.deepEqual(applied, ['dark']);
});
