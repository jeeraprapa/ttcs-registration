<h1>THANK YOU FOR REGISTERING</h1>
<p>We look forward to welcoming you to<br>the Upper House Residences Private Preview in Hong Kong.</p>
@if($registration->cancelled_at)
<p>This booking has been cancelled. Its QR code is no longer valid for admission.</p>
@else
<p>Please present this QR code on arrival for check-in.</p>
<img class="check-in-qr" src="{{ $qrSource }}" width="240" height="240" alt="Your booking QR code for staff check-in" style="display:block;width:240px;max-width:100%;height:auto;margin:20px auto">
@endif
<p>Booking reference: {{ $registration->reference }}</p>
<p><strong>Venue</strong></p><p>{{ $registration->venue_snapshot }}</p>
<p><strong>Date &amp; time</strong></p>
<p>{{ $registration->session_date_snapshot->format('l, j F Y') }}<br>{{ \Carbon\Carbon::parse($registration->session_time_snapshot)->format('h.i A') }} ({{ $registration->timezone_snapshot }})</p>
<p>{{ $registration->guest_count }} guests, including yourself</p>
<p>We look forward to seeing you.</p>
