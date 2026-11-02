import test from 'node:test';
import assert from 'node:assert/strict';
import { externalBrowserPlan } from '../../resources/js/line-browser.js';

const line = 'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 Line/14.0.0';
const chrome = 'Mozilla/5.0 Chrome/130.0 Safari/537.36';

test('LINE links automatically hand off through the public entry page', () => {
    const plan = externalBrowserPlan('https://event.test/admin/check-ins/scan', line);
    const destination = new URL(plan.redirect);
    assert.equal(destination.pathname, '/');
    assert.equal(destination.searchParams.get('openExternalBrowser'), '1');
    assert.equal(plan.target, 'https://event.test/admin/check-ins/scan');
});

test('signed check-in links survive the external handoff unchanged', () => {
    const original = 'https://event.test/admin/check-ins/uuid?expires=123&signature=abc%2Bdef#details';
    const plan = externalBrowserPlan(original, line);
    assert.equal(externalBrowserPlan(plan.handoff, chrome).redirect, original);
});

test('LINE fallback stays on the handoff without an automatic redirect loop', () => {
    const plan = externalBrowserPlan('https://event.test/admin/login', line);
    const fallback = externalBrowserPlan(plan.handoff, line);
    assert.equal(fallback.redirect, null);
    assert.equal(fallback.target, plan.target);
    assert.equal(fallback.handoff, plan.handoff);
});

test('ordinary Safari and Chrome browsing is unchanged', () => {
    assert.equal(externalBrowserPlan('https://event.test/admin', chrome), null);
    assert.equal(externalBrowserPlan('https://event.test/', 'Version/18.0 Mobile/15E148 Safari/604.1'), null);
});

test('handoff rejects external destinations and malformed encoding', () => {
    for (const path of ['//evil.test/admin', '/\\evil.test/admin', 'https://evil.test/', '%broken']) {
        const fragment = path === '%broken' ? path : encodeURIComponent(path);
        assert.equal(externalBrowserPlan('https://event.test/?openExternalBrowser=1#external-browser-return=' + fragment, chrome), null);
    }
});
