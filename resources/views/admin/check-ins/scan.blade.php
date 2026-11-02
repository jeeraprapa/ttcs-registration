@extends('layouts.admin')
@section('title', 'Scan QR Check-in')
@section('content')
<header class="page-head"><h1>Scan QR Check-in</h1><p>Scan the guest's registration QR, review their booking, then confirm check-in.</p></header>
<section class="panel qr-scanner" data-qr-scanner data-resolve-url="{{ route('admin.check-ins.resolve') }}">
    <form data-qr-form action="{{ route('admin.check-ins.resolve') }}" method="POST">
        @csrf
        <h2>Scan with your camera</h2>
        <p class="quiet">Use your phone's rear camera. Camera access requires HTTPS and your permission.</p>
        <div class="qr-camera">
            <video data-qr-video playsinline muted autoplay aria-label="QR scanner camera preview" hidden></video>
            <div class="qr-camera-placeholder" data-qr-placeholder><i class="bi bi-qr-code-scan" aria-hidden="true"></i><span>Position the QR code inside the frame</span></div>
            <div class="qr-camera-frame" aria-hidden="true"></div>
        </div>
        <div class="qr-actions"><button type="button" class="button" data-qr-start>Start camera</button><button type="button" class="button qr-secondary" data-qr-stop disabled>Stop camera</button></div>
        <p class="qr-status" data-qr-status role="status" aria-live="polite">Ready to scan. Your camera starts only when you tap Start camera.</p>
        <div class="qr-image-field"><label class="button qr-secondary" for="qr-image">Choose a QR image</label><input type="file" id="qr-image" data-qr-image accept="image/png,image/jpeg,image/webp"><p class="quiet">Images are decoded on this device and are not uploaded.</p></div>
        <details class="qr-manual"><summary>Paste a QR link instead</summary><div class="field"><label for="qr">Registration QR link</label><textarea name="qr" id="qr" data-qr-input rows="3" maxlength="4096" placeholder="Paste the complete registration QR link"></textarea></div><button class="button" type="submit" data-qr-open>Open booking</button></details>
        <noscript><p class="notice warning">Enable JavaScript to scan a QR code. You can also scan it with your phone's camera app and open the link.</p></noscript>
    </form>
    <p class="quiet">Scanning does not check the guest in automatically. Cancelled bookings cannot check in; previously checked-in bookings show their existing check-in time.</p>
    <a href="{{ route('admin.registrations.index') }}">Find a booking manually →</a>
</section>
@endsection
