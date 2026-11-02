const scanner = document.querySelector('[data-qr-scanner]');

if (scanner) {
    const video = scanner.querySelector('[data-qr-video]');
    const start = scanner.querySelector('[data-qr-start]');
    const stop = scanner.querySelector('[data-qr-stop]');
    const status = scanner.querySelector('[data-qr-status]');
    const image = scanner.querySelector('[data-qr-image]');
    const input = scanner.querySelector('[data-qr-input]');
    const open = scanner.querySelector('[data-qr-open]');
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });
    let stream, frame, generation = 0, busy = false, lastFrame = 0;
    let decoder;
    const loadDecoder = async () => decoder ??= (await import('jsqr')).default;
    const message = (text, error = false) => {
        status.textContent = text;
        status.dataset.state = error ? 'error' : 'ready';
    };
    const stopCamera = () => {
        generation++;
        cancelAnimationFrame(frame);
        stream?.getTracks().forEach(track => track.stop());
        stream = null;
        video.srcObject = null;
        video.hidden = true;
        scanner.querySelector('[data-qr-placeholder]').hidden = false;
        stop.disabled = true;
        start.disabled = busy;
    };
    const setBusy = value => {
        busy = value;
        start.disabled = value;
        image.disabled = value;
        open.disabled = value;
    };
    const resolve = async text => {
        if (busy) return;
        setBusy(true);
        stopCamera();
        message('Checking QR code…');
        try {
            const response = await fetch(scanner.dataset.resolveUrl, {
                method: 'POST', credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': scanner.querySelector('[name="_token"]').value },
                body: JSON.stringify({ qr: text }),
            });
            if ([401, 419].includes(response.status)) throw new Error('Your session has expired. Reload this page and sign in again.');
            if (response.status === 429) throw new Error('Too many attempts. Please wait one minute and try again.');
            const data = await response.json();
            if (!response.ok) throw new Error(data.errors?.qr?.[0] || 'Unable to check this QR code. Please try again.');
            const destination = new URL(data.url, window.location.origin);
            if (destination.origin !== window.location.origin) throw new Error('Invalid booking link.');
            window.location.assign(destination.href);
        } catch (error) {
            message(error.message || 'Unable to connect. Please try again.', true);
            setBusy(false);
        }
    };
    const decode = (source, width, height, limit) => {
        const ratio = Math.min(1, limit / Math.max(width, height));
        canvas.width = Math.round(width * ratio);
        canvas.height = Math.round(height * ratio);
        context.drawImage(source, 0, 0, canvas.width, canvas.height);
        const pixels = context.getImageData(0, 0, canvas.width, canvas.height);
        return decoder(pixels.data, pixels.width, pixels.height, { inversionAttempts: 'attemptBoth' });
    };
    start.addEventListener('click', async () => {
        if (busy || stream) return;
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            message('Camera access requires HTTPS and a supported browser. You can choose a QR image instead.', true);
            return;
        }
        stopCamera();
        const attempt = generation;
        start.disabled = true;
        stop.disabled = false;
        message('Allow camera access to start scanning…');
        try {
            await loadDecoder();
            if (attempt !== generation) return;
            const camera = await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 720 } } });
            if (attempt !== generation) {
                camera.getTracks().forEach(track => track.stop());
                return;
            }
            stream = camera;
            video.srcObject = camera;
            video.hidden = false;
            scanner.querySelector('[data-qr-placeholder]').hidden = true;
            await video.play();
            if (attempt !== generation) return;
            message('Point your camera at the registration QR code.');
            const scan = timestamp => {
                if (attempt !== generation || busy) return;
                if (video.readyState >= 2 && video.videoWidth && timestamp - lastFrame > 200) {
                    lastFrame = timestamp;
                    try {
                        const result = decode(video, video.videoWidth, video.videoHeight, 960);
                        if (result?.data) { resolve(result.data); return; }
                    } catch {
                        stopCamera();
                        message('Unable to read camera frames. Please restart the camera or choose a QR image.', true);
                        return;
                    }
                }
                frame = requestAnimationFrame(scan);
            };
            frame = requestAnimationFrame(scan);
        } catch (error) {
            if (attempt !== generation) return;
            stopCamera();
            message(error.name === 'NotAllowedError' ? 'Camera permission was denied. Allow access in your browser settings or choose a QR image.' : 'Unable to start the camera. Close other camera apps and try again, or choose a QR image.', true);
        }
    });
    stop.addEventListener('click', () => { stopCamera(); message('Camera stopped. Tap Start camera to scan again.'); });
    scanner.querySelector('[data-qr-form]').addEventListener('submit', event => { event.preventDefault(); resolve(input.value.trim()); });
    image.addEventListener('change', async () => {
        const file = image.files?.[0];
        if (!file || busy) return;
        stopCamera();
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 10 * 1024 * 1024) {
            message('Choose a PNG, JPEG or WebP image up to 10 MB.', true);
            image.value = '';
            return;
        }
        setBusy(true);
        const attempt = generation;
        const url = URL.createObjectURL(file);
        let result;
        try {
            message('Reading QR image…');
            await loadDecoder();
            const picture = new Image();
            await new Promise((accept, reject) => { picture.onload = accept; picture.onerror = reject; picture.src = url; });
            if (attempt !== generation) return;
            result = decode(picture, picture.naturalWidth, picture.naturalHeight, 2000);
            if (!result?.data) throw new Error('No QR code found. Choose a clearer image or scan with the camera.');
        } catch (error) {
            if (attempt === generation) message(error.message || 'Unable to read this image.', true);
        } finally {
            URL.revokeObjectURL(url);
            image.value = '';
            setBusy(false);
        }
        if (result?.data && attempt === generation) resolve(result.data);
    });
    window.addEventListener('pagehide', stopCamera);
    window.addEventListener('pageshow', event => {
        if (event.persisted) {
            setBusy(false);
            stopCamera();
            message('Ready to scan. Tap Start camera.');
        }
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) { stopCamera(); message('Camera stopped while this page is in the background.'); }
    });
}
