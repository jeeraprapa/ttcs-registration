@extends('layouts.admin') @section('title','Event sessions')
@section('content')<header class="page-head"><h1>Event sessions</h1><p>Manage registration availability for preview sessions.</p></header>
<form method="GET" action="{{ route('admin.sessions.index') }}" class="panel">
    <div class="filter-grid">
        <div class="field"><label for="session-filter-month">Month</label>
            <select name="month" id="session-filter-month" data-session-filter-month>
                <option value="">All months</option>
                @foreach($months as $month)<option value="{{ $month->format('Y-m') }}" @selected(($filters['month'] ?? '') === $month->format('Y-m'))>{{ $month->format('F Y') }}</option>@endforeach
            </select>
        </div>
        <div class="field"><label for="session-filter-day">Day</label>
            <select name="day" id="session-filter-day" data-session-filter-day>
                <option value="">All days</option>
                @foreach($dates as $day)<option value="{{ $day->format('Y-m-d') }}" data-month="{{ $day->format('Y-m') }}" @selected(($filters['day'] ?? '') === $day->format('Y-m-d'))>{{ $day->format('D, j M Y') }}</option>@endforeach
            </select>
        </div>
        <div class="field"><label for="session-filter-time">Time session</label>
            <select name="time" id="session-filter-time">
                <option value="">All time sessions</option>
                @foreach($times as $time)<option value="{{ substr($time, 0, 5) }}" @selected(($filters['time'] ?? '') === substr($time, 0, 5))>{{ substr($time, 0, 5) }}</option>@endforeach
            </select>
        </div>
    </div>
    <div class="filter-actions"><button class="button" type="submit">Apply filters</button><a href="{{ route('admin.sessions.index') }}" class="quiet">Clear filters</a></div>
</form>
<section class="panel"><h2>{{ $sessions->count() }} sessions</h2><div class="table-wrap"><table><thead><tr><th>Date</th><th>Time · Hong Kong</th><th>Bookings</th><th>Guests</th><th>Capacity</th><th>Status</th><th></th></tr></thead><tbody>@forelse($sessions as $session)<tr><td>{{ $session->local_date->format('l, j M Y') }}</td><td>{{ \Carbon\Carbon::parse($session->local_start_time)->format('h.i A') }}</td><td>{{ $session->registrations_count }}</td><td>{{ $session->registrations_sum_guest_count??0 }}</td><td>1 booking</td><td><span class="badge {{ !$session->is_open?'closed':'' }}">{{ $session->registrations_count ? 'Booked' : ($session->is_open?'Open':'Closed') }}</span></td><td><a href="{{ route('admin.sessions.edit',$session) }}">Manage →</a></td></tr>@empty<tr><td colspan="7"><div class="empty-state">No sessions match these filters.</div></td></tr>@endforelse</tbody></table></div></section>@endsection
