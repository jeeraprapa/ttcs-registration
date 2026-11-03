@extends('layouts.public') @section('title','Thank you for registering')
@section('content')
<section class="confirmation">
<div data-booking-card data-reference="{{ $registration->reference }}">
@include('components.booking-confirmation', [
    'qrSource' => app(\App\Support\RegistrationQrCode::class)->dataUri($registration),
])
</div>
@if(!$registration->cancelled_at)<p>Your registration details and QR code will also be sent to your email address.</p>@endif
<div class="booking-download-actions">
    <button type="button" class="button" data-booking-download hidden><i class="bi bi-download" aria-hidden="true"></i> Download booking</button>
    <p data-booking-download-status role="status" aria-live="polite"></p>
</div>
</section>
@if($submissionId)
<script>
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
        event: 'registration',
        submission_id: {{ Illuminate\Support\Js::from($submissionId) }}
    });
</script>
@endif
@endsection
