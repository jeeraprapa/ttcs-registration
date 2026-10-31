@extends('layouts.admin')
@section('title', 'Guest check-in')
@section('content')
<header class="page-head"><h1>Guest check-in</h1><p>Confirm the guest's booking before checking in.</p></header>
<section class="panel">
<h2>{{ $registration->first_name }} {{ $registration->last_name }}</h2>
<p>{{ $registration->reference }}</p>
<p>{{ $registration->session_date_snapshot->format('l, j F Y') }} · {{ \Carbon\Carbon::parse($registration->session_time_snapshot)->format('H:i') }} ({{ $registration->timezone_snapshot }})</p>
<p>{{ $registration->guest_count }} guests, including registrant</p>
@if($registration->cancelled_at)
<p class="badge closed">Cancelled — admission unavailable</p>
@elseif($registration->checked_in_at)
<p class="badge">Already checked in: {{ $registration->checked_in_at->timezone($registration->timezone_snapshot)->format('j M Y H:i') }}</p>
@else
<form method="POST" action="{{ \Illuminate\Support\Facades\URL::signedRoute('admin.check-ins.store', ['registration' => $registration->reference]) }}">@csrf<button type="submit" class="button">Confirm check-in</button></form>
@endif
<p><a href="{{ route('admin.registrations.show', $registration) }}">View booking details</a></p>
</section>
@endsection
