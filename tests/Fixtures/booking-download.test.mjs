import test from 'node:test';
import assert from 'node:assert/strict';

test('booking download exports current details, respects cancellation, and recovers from failure', async t => {
    for (const scenario of ['active', 'cancelled', 'failure']) {
        await t.test(scenario, async () => {
            let onClick, downloaded = false, qrDrawn = false, downloadedUrl;
            const painted = [];
            const context = {
                measureText: text => ({ width: text.length * 18 }),
                fillRect() {}, strokeRect() {},
                fillText: text => painted.push(text),
                drawImage: () => qrDrawn = true,
            };
            const button = { hidden: true, disabled: false, addEventListener: (name, callback) => onClick = callback };
            const status = {};
            const children = [
                { tagName: 'H1', innerText: 'THANK YOU FOR REGISTERING' },
                { tagName: 'P', innerText: 'Venue\nThe Tong Chong Suite, 9/F Cambridge House\n979 King’s Road, Hong Kong' },
                { tagName: 'P', innerText: 'Booking reference: test-reference' },
            ];
            if (scenario === 'cancelled') children.push({ tagName: 'P', innerText: 'This booking has been cancelled.' });
            else children.push({ tagName: 'IMG', complete: true, naturalWidth: 360 });
            const card = { dataset: { reference: 'test-reference' }, children };
            globalThis.document = {
                fonts: { ready: Promise.resolve() },
                body: { append() {} },
                querySelector: selector => ({
                    '[data-booking-download]': button, '[data-booking-card]': card,
                    '[data-booking-download-status]': status,
                })[selector],
                createElement: tag => tag === 'canvas' ? {
                    getContext: () => context,
                    toBlob: callback => callback(scenario === 'failure' ? null : new Blob(['test'], { type: 'image/png' })),
                } : { click() { downloaded = true; downloadedUrl = this.href; assert.equal(this.download, 'Upper-House-booking-test-reference.png'); }, remove() {} },
            };
            try {
                const { wrapBookingText } = await import(`../../resources/js/booking-download.js?scenario=${scenario}`);
                assert.deepEqual(wrapBookingText(context, 'one two three\nfour', 130), ['one two', 'three', 'four']);
                assert.equal(button.hidden, false);
                await onClick();
                assert.equal(button.disabled, false);
                assert.equal(downloaded, scenario !== 'failure');
                assert.equal(qrDrawn, scenario !== 'cancelled');
                assert.ok(painted.some(text => text.includes('test-reference')));
                if (scenario === 'cancelled') assert.ok(painted.includes('This booking has been cancelled.'));
                if (scenario === 'failure') assert.match(status.textContent, /Unable to create/);
                else {
                    assert.equal(status.textContent, '');
                    URL.revokeObjectURL(downloadedUrl);
                }
            } finally {
                delete globalThis.document;
            }
        });
    }
});
