import test from 'node:test';
import assert from 'node:assert/strict';

test('mobile menu toggles, closes with Escape/outside click, and stays visible on desktop', async () => {
    const events = {};
    const icon = {};
    const attributes = {};
    let focused = false;
    const toggle = {
        setAttribute: (key, value) => attributes[key] = value,
        querySelector: () => icon,
        addEventListener: (name, callback) => events['toggle:' + name] = callback,
        focus: () => focused = true,
    };
    const menu = { hidden: false, contains: element => element === 'menu-link' };
    const sidebar = {
        dataset: {},
        querySelector: selector => selector === '[data-admin-menu-toggle]' ? toggle : menu,
        addEventListener: (name, callback) => events['sidebar:' + name] = callback,
        contains: element => element === toggle,
    };
    const mobile = { matches: true, addEventListener: (name, callback) => events[name] = callback };
    globalThis.document = {
        querySelector: () => sidebar,
        addEventListener: (name, callback) => events['document:' + name] = callback,
    };
    globalThis.window = { matchMedia: () => mobile };
    try {
        await import('../../resources/js/admin-navigation.js');
        assert.equal(menu.hidden, true);
        assert.equal(attributes['aria-expanded'], 'false');
        events['toggle:click']();
        assert.equal(menu.hidden, false);
        assert.equal(attributes['aria-expanded'], 'true');
        assert.equal(icon.className, 'bi bi-x-lg');
        events['sidebar:keydown']({ key: 'Escape' });
        assert.equal(menu.hidden, true);
        assert.equal(focused, true);
        events['toggle:click']();
        events['document:click']({ target: toggle });
        assert.equal(menu.hidden, false);
        events['document:click']({ target: 'outside' });
        assert.equal(menu.hidden, true);
        mobile.matches = false;
        events.change();
        assert.equal(menu.hidden, false);
        mobile.matches = true;
        document.activeElement = 'menu-link';
        focused = false;
        events.change();
        assert.equal(menu.hidden, true);
        assert.equal(focused, true);
    } finally {
        delete globalThis.document;
        delete globalThis.window;
    }
});
