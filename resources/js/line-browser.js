const returnPrefix = '#external-browser-return=';

export function externalBrowserPlan(href, userAgent) {
    const current = new URL(href);
    const isLine = /\bLine\//i.test(userAgent);
    const isHandoff = current.pathname === '/' && current.searchParams.get('openExternalBrowser') === '1'
        && current.hash.startsWith(returnPrefix);
    let target = new URL(current.href);
    if (isHandoff) {
        try {
            const path = decodeURIComponent(current.hash.slice(returnPrefix.length));
            const candidate = new URL(path, current.origin);
            if (!path.startsWith('/') || path.startsWith('//') || candidate.origin !== current.origin) return null;
            target = candidate;
        } catch { return null; }
    }
    if (!isLine) return isHandoff ? { redirect: target.href } : null;
    const handoff = new URL('/', current.origin);
    handoff.searchParams.set('openExternalBrowser', '1');
    handoff.hash = returnPrefix + encodeURIComponent(target.pathname + target.search + target.hash);
    return { target: target.href, handoff: handoff.href, redirect: isHandoff ? null : handoff.href };
}

export function enforceExternalBrowser() {
    const plan = externalBrowserPlan(window.location.href, navigator.userAgent);
    if (!plan) return;
    if (plan.handoff) {
        const dialog = document.createElement('dialog');
        dialog.className = 'external-browser-dialog';
        dialog.setAttribute('aria-labelledby', 'external-browser-title');
        dialog.innerHTML = '<h1 id="external-browser-title">Open in Safari or Chrome</h1>'
            + '<p>This page must be used in your phone’s main browser. We are trying to open it automatically.</p>'
            + '<a class="button" data-external-open>Open in browser</a>'
            + '<p>If you are still in LINE, tap the menu ⋯ and choose <strong>Open in browser</strong>, or copy this link into Safari / Chrome.</p>'
            + '<label for="external-browser-url">Page link</label><input id="external-browser-url" readonly>'
            + '<button class="button qr-secondary" type="button" data-external-copy>Copy link</button>'
            + '<p role="status" aria-live="polite" data-external-status>You may need to sign in again in the external browser.</p>';
        dialog.querySelector('[data-external-open]').href = plan.handoff;
        const input = dialog.querySelector('input');
        input.value = plan.target;
        dialog.querySelector('[data-external-copy]').addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(plan.target);
                dialog.querySelector('[data-external-status]').textContent = 'Link copied. Paste it into Safari or Chrome.';
            } catch {
                input.focus();
                input.select();
                dialog.querySelector('[data-external-status]').textContent = 'Press and hold the selected link to copy it, then paste it into Safari or Chrome.';
            }
        });
        dialog.addEventListener('cancel', event => event.preventDefault());
        document.body.append(dialog);
        if (dialog.showModal) dialog.showModal();
        else dialog.setAttribute('open', '');
        document.documentElement.classList.add('external-browser-required');
    }
    if (plan.redirect) window.location.replace(plan.redirect);
}
