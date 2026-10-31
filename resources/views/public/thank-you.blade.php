@extends('layouts.public') @section('title','Thank you for registering')
@section('content')
<section class="confirmation">
@include('components.booking-confirmation', ['qrSource' => app(\App\Support\RegistrationQrCode::class)->dataUri($registration)])
@if(!$registration->cancelled_at)<p>Your registration details and QR code will also be sent to your email address.</p>@endif
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
