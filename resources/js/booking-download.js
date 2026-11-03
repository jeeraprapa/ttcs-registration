export function wrapBookingText(context, text, maxWidth) {
    const lines = [];
    for (const paragraph of text.split('\n')) {
        let line = '';
        for (const word of paragraph.trim().split(/\s+/)) {
            const next = line ? `${line} ${word}` : word;
            if (line && context.measureText(next).width > maxWidth) {
                lines.push(line);
                line = word;
            } else {
                line = next;
            }
        }
        lines.push(line);
    }
    return lines;
}

const downloadButton = document.querySelector('[data-booking-download]');
const card = document.querySelector('[data-booking-card]');

if (downloadButton && card) {
    const status = document.querySelector('[data-booking-download-status]');
    let imageUrl;
    downloadButton.hidden = false;
    downloadButton.addEventListener('click', async () => {
        if (downloadButton.disabled) return;
        downloadButton.disabled = true;
        status.textContent = 'Preparing your booking image…';
        try {
            await document.fonts.ready;
            const canvas = document.createElement('canvas');
            canvas.width = 1200;
            const context = canvas.getContext('2d');
            if (!context) throw new Error('Image downloads are unavailable in this browser.');
            const content = [];
            let y = 200;
            for (const element of card.children) {
                if (element.tagName === 'IMG') {
                    if (!element.complete) {
                        await new Promise((accept, reject) => {
                            element.addEventListener('load', accept, { once: true });
                            element.addEventListener('error', reject, { once: true });
                        });
                    }
                    if (!element.naturalWidth) throw new Error('The booking QR code could not be loaded. Please reload and try again.');
                    content.push({ image: element, y });
                    y += 500;
                } else if (['H1', 'P'].includes(element.tagName)) {
                    const heading = element.tagName === 'H1';
                    const font = heading ? 'bold 48px Arial, sans-serif' : '34px Arial, sans-serif';
                    context.font = font;
                    const lines = wrapBookingText(context, element.innerText, 1000);
                    content.push({ lines, font, y });
                    y += lines.length * 52 + 28;
                }
            }
            canvas.height = y + 100;
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.strokeStyle = '#d6dfd0';
            context.strokeRect(36, 36, canvas.width - 72, canvas.height - 72);
            context.textAlign = 'center';
            context.textBaseline = 'top';
            context.fillStyle = '#243c2c';
            context.font = 'bold 30px Arial, sans-serif';
            context.fillText('UPPER HOUSE RESIDENCES', 600, 105);
            for (const item of content) {
                if (item.image) {
                    context.imageSmoothingEnabled = false;
                    context.drawImage(item.image, 370, item.y, 460, 460);
                } else {
                    context.font = item.font;
                    item.lines.forEach((line, index) => context.fillText(line, 600, item.y + index * 52));
                }
            }
            const blob = await new Promise(accept => canvas.toBlob(accept, 'image/png'));
            if (!blob) throw new Error('Unable to create your booking image. Please try again.');
            if (imageUrl) URL.revokeObjectURL(imageUrl);
            imageUrl = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = imageUrl;
            link.download = `Upper-House-booking-${card.dataset.reference}.png`;
            document.body.append(link);
            link.click();
            link.remove();
            status.textContent = '';
        } catch (error) {
            status.textContent = error.message || 'Unable to save your booking. Please try again or take a screenshot.';
        } finally {
            downloadButton.disabled = false;
        }
    });
}
