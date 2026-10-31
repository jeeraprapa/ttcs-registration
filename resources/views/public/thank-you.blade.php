@extends('layouts.public') @section('title','Thank you for registering')
@section('content')<section class="confirmation"><h1>THANK YOU FOR REGISTERING</h1><p>We look forward to welcoming you to<br>the Upper House Residences Private Preview in Hong Kong.</p><p>Your registration details will be sent to your email address.</p><p class="eyebrow">Venue</p><p>Sky Lounge, 49/F Upper House Hong Kong<br>88 Queensway, Admiralty, Hong Kong</p><p class="eyebrow">Date &amp; time</p><p>{{ \Carbon\Carbon::parse($registration->session_date_snapshot)->format('l, j F Y') }}<br>{{ \Carbon\Carbon::parse($registration->session_time_snapshot)->format('h.i A') }} (Hong Kong time)</p><p>We look forward to seeing you.</p></section>
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
