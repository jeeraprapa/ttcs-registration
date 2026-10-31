<!doctype html>
<html lang="en">
<body
    style="font-family:Arial,sans-serif;color:#26362b;max-width:600px;margin:auto;padding:30px">
<h1 style="font-size:24px">Thank you for registering</h1>
<p>Dear {{ $registration->first_name }},</p>
<p>We look forward to welcoming you to the Upper House Residences Private Preview in Hong
    Kong.</p>
<p>
    <strong>Date:</strong> {{ \Carbon\Carbon::parse($registration->session_date_snapshot)->format('l, j F Y') }}
    <br><strong>Time:</strong> {{ \Carbon\Carbon::parse($registration->session_time_snapshot)->format('h.i A') }}
    ({{ $registration->timezone_snapshot }}
    )<br><strong>Venue:</strong> {{ $registration->venue_snapshot }}
    <br><strong>Guests:</strong> {{ $registration->guest_count }}, including yourself</p>
<p>Booking reference: {{ $registration->reference }}</p>
<p>We look forward to seeing you.</p><a href="https://www.uhresidencesbangkok.com/"
                                        target="_blank" rel="noopener noreferrer">Upper
    House Residences</a></body>
</html>
