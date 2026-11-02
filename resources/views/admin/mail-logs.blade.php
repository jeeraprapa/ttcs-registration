@extends('layouts.admin')
@section('title', 'Email logs')
@section('content')
<header class="page-head"><h1>Email logs</h1><p>Delivery attempts for registration confirmations and admin test emails.</p></header>
<p class="notice">{{ number_format($pendingCount) }} confirmation emails pending or waiting to retry. “Accepted by mail server” confirms transport acceptance, not inbox delivery. History is recorded from when this feature is enabled.</p>
<form method="GET" action="{{ route('admin.mail-logs.index') }}" class="panel">
    <div class="filter-grid">
        <div class="field"><label for="recipient">Recipient email</label><input type="email" id="recipient" name="recipient" value="{{ $filters['recipient'] ?? '' }}" maxlength="254" placeholder="All recipients"></div>
        <div class="field"><label for="type">Email type</label><select name="type" id="type"><option value="">All types</option>@foreach(['confirmation' => 'Registration confirmation', 'test' => 'Test email'] as $value => $label)<option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="status">Delivery status</label><select name="status" id="status"><option value="">All statuses</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
    </div>
    <div class="filter-actions"><button class="button" type="submit">Apply filters</button><a href="{{ route('admin.mail-logs.index') }}" class="quiet">Clear filters</a><a href="{{ route('admin.send-testmail') }}">Send test email</a></div>
</form>
<section class="panel"><h2>{{ number_format($logs->total()) }} delivery attempts</h2>
<div class="table-wrap"><table><thead><tr><th>Recipient / type</th><th>Subject</th><th>Status</th><th>Attempt</th><th>Started</th><th>Finished</th><th>Result details</th></tr></thead><tbody>
@forelse($logs as $log)
<tr>
    <td>{{ $log->recipient }}<br><span class="quiet">{{ $log->type === 'test' ? 'Test email' : 'Registration confirmation' }}</span>@if($log->registration_id)<br><a href="{{ route('admin.registrations.show', $log->registration_id) }}">{{ $log->reference }}</a>@endif</td>
    <td>{{ $log->subject }}<br><span class="quiet">From: {{ $log->from_address ?? '—' }}</span><br><span class="quiet">{{ $log->mailer }} · {{ $log->smtp_host }}{{ $log->smtp_port ? ':'.$log->smtp_port : '' }}</span></td>
    <td><span class="badge {{ $log->status === 'failed' ? 'failed' : ($log->status === 'processing' ? 'processing' : '') }}">{{ $statuses[$log->status] ?? $log->status }}</span>@if($log->confirmation_status)<br><span class="quiet">Confirmation: {{ $log->confirmation_status }}</span>@endif</td>
    <td>{{ $log->attempt }}</td>
    <td>{{ \Carbon\Carbon::parse($log->created_at, 'UTC')->timezone(config('registration.timezone'))->format('j M Y H:i:s') }}</td>
    <td>{{ $log->finished_at ? \Carbon\Carbon::parse($log->finished_at, 'UTC')->timezone(config('registration.timezone'))->format('j M Y H:i:s') : '—' }}</td>
    <td style="min-width:220px;max-width:400px;overflow-wrap:anywhere">@if($log->error_message)<details><summary>View error</summary><p>{{ $log->error_message }}</p></details>@endif
        @if($log->provider_message_id)<small>Message ID: {{ $log->provider_message_id }}</small>@endif
        @if($log->confirmation_status === 'pending' && $log->next_attempt_at)<p class="quiet">Next retry: {{ \Carbon\Carbon::parse($log->next_attempt_at, 'UTC')->timezone(config('registration.timezone'))->format('j M Y H:i:s') }}</p>@endif
        @if(!$log->error_message && !$log->provider_message_id)—@endif
    </td>
</tr>
@empty<tr><td colspan="7"><div class="empty-state">No email delivery attempts found.</div></td></tr>@endforelse
</tbody></table></div><div class="pagination">{{ $logs->links() }}</div><p class="quiet">Times shown in {{ config('registration.timezone') }}. Passwords and email bodies are not stored in this history.</p>
</section>
@endsection
