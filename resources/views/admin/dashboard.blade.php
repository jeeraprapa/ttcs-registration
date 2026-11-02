@extends('layouts.admin')
@section('title', 'Overview')
@section('content')
<div class="event-overview">
<header class="page-head overview-head"><div><p class="eyebrow">Hong Kong · 18 November – 6 December 2026</p><h1>Event overview</h1><p>Bookings, availability and arrivals for the current event schedule.</p></div><a class="button" href="{{ route('admin.registrations.index') }}">View registrations</a></header>
<div class="overview-stats">
    <a class="card overview-stat" href="{{ route('admin.registrations.index') }}"><span class="eyebrow">Active bookings</span><strong>{{ number_format($registrationCount) }}</strong><span class="quiet">{{ $cancelledCount }} cancelled · excluded from totals</span></a>
    <a class="card overview-stat" href="{{ route('admin.sessions.index') }}"><span class="eyebrow">Available sessions</span><strong>{{ number_format($availableSessionCount) }}<small> / {{ $sessions->count() }}</small></strong><span class="quiet">Open and ready to book</span></a>
    <a class="card overview-stat" href="{{ route('admin.registrations.index', ['attendance' => 'checked_in']) }}"><span class="eyebrow">Checked in</span><strong>{{ number_format($checkedInCount) }}<small> / {{ $registrationCount }}</small></strong><span class="quiet">{{ max(0, $registrationCount - $checkedInCount) }} bookings yet to arrive</span></a>
    <div class="card overview-stat"><span class="eyebrow">Expected guests</span><strong>{{ number_format($guestCount) }}</strong><span class="quiet">Including registrants</span></div>
</div>
<div class="overview-summary-grid">
<section class="panel overview-summary"><div class="overview-section-head"><h2>Booking progress</h2><span class="overview-percentage">{{ $sessions->count() ? round($bookedSessionCount / $sessions->count() * 100) : 0 }}%</span></div><p class="quiet">{{ $bookedSessionCount }} of {{ $sessions->count() }} sessions booked · one booking per session</p><progress class="overview-progress" value="{{ $bookedSessionCount }}" max="{{ max(1, $sessions->count()) }}" aria-label="Booked sessions">{{ $bookedSessionCount }}</progress><div class="overview-key"><span><i class="booked"></i>{{ $bookedSessionCount }} booked</span><span><i class="available"></i>{{ $availableSessionCount }} available</span><span><i class="closed"></i>{{ $closedSessionCount }} closed</span></div></section>
<section class="panel overview-summary"><div class="overview-section-head"><h2>Confirmation emails</h2><a href="{{ route('admin.mail-logs.index') }}">Email logs →</a></div><div class="overview-mail-stats"><div><strong>{{ $emailCounts->get('pending', 0) + $emailCounts->get('processing', 0) }}</strong><span>Queued / sending</span></div><div class="{{ $emailCounts->get('failed', 0) ? 'needs-attention' : '' }}"><strong>{{ $emailCounts->get('failed', 0) }}</strong><span>Failed</span></div><div><strong>{{ $emailCounts->get('provider_accepted', 0) }}</strong><span>Server accepted</span></div></div><p class="quiet">Server acceptance does not confirm inbox delivery.@if($emailCounts->get('local_preview', 0)) {{ $emailCounts->get('local_preview') }} preview-only emails.@endif</p></section>
</div>
<section class="panel overview-schedule"><div class="overview-section-head"><div><h2>Session availability</h2><p class="quiet">Hong Kong time · Select a booked time to view its registration, or another time to manage availability.</p></div><a href="{{ route('admin.sessions.index') }}">Manage sessions →</a></div>
<div class="overview-key"><span><i class="booked"></i>Booked</span><span><i class="available"></i>Available</span><span><i class="closed"></i>Closed</span></div>
@forelse($sessions->groupBy(fn ($session) => $session->local_date->format('F Y')) as $month => $monthSessions)
<h3 class="overview-month">{{ $month }}</h3><div class="overview-days">
@foreach($monthSessions->groupBy(fn ($session) => $session->local_date->format('Y-m-d')) as $day => $daySessions)
<article class="overview-day"><div class="overview-day-head"><a href="{{ route('admin.registrations.index', ['day' => $day]) }}"><strong>{{ $daySessions->first()->local_date->format('D, j M') }}</strong></a><span>{{ $daySessions->where('registrations_count', '>', 0)->count() }}/{{ $daySessions->count() }} booked</span></div>
<div class="overview-slots">@foreach($daySessions as $session)
@php($slotStatus = $session->registrations_count ? 'booked' : ($session->is_open ? 'available' : 'closed'))
<a class="overview-slot {{ $slotStatus }}" href="{{ $slotStatus === 'booked' ? route('admin.registrations.index', ['day' => $day, 'time' => substr($session->local_start_time, 0, 5)]) : route('admin.sessions.edit', $session) }}" aria-label="{{ $session->local_date->format('j F') }} {{ substr($session->local_start_time, 0, 5) }}, {{ $slotStatus }}"><span>{{ substr($session->local_start_time, 0, 5) }}</span><small>{{ ucfirst($slotStatus) }}</small></a>
@endforeach</div><p class="quiet">{{ $daySessions->sum('registrations_sum_guest_count') }} expected guests</p></article>
@endforeach</div>
@empty<div class="empty-state">No event sessions configured.</div>@endforelse
</section>
<section class="panel"><div class="overview-section-head"><h2>Recent registrations</h2><a href="{{ route('admin.registrations.index') }}">View all →</a></div><div class="table-wrap"><table><thead><tr><th>Guest</th><th>Session</th><th>Guests</th><th>Status</th><th></th></tr></thead><tbody>@forelse($recentRegistrations as $registration)<tr><td>{{ $registration->first_name }} {{ $registration->last_name }}</td><td>{{ $registration->session_date_snapshot->format('j M') }} · {{ substr($registration->session_time_snapshot, 0, 5) }}</td><td>{{ $registration->guest_count }}</td><td><span class="badge {{ $registration->cancelled_at ? 'closed' : '' }}">{{ $registration->cancelled_at ? 'Cancelled' : ($registration->checked_in_at ? 'Checked in' : 'Booked') }}</span></td><td><a href="{{ route('admin.registrations.show', $registration) }}">Details →</a></td></tr>@empty<tr><td colspan="5" class="quiet">No registrations yet.</td></tr>@endforelse</tbody></table></div></section>
</div>
@endsection
