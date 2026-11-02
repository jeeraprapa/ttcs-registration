@extends('layouts.admin')
@section('title', 'Send test email')
@section('content')
    <header class="page-head">
        <h1>Send test email</h1><p><a href="{{ route('admin.mail-logs.index') }}">View email logs →</a></p>
        <p>Test your SMTP settings with a single email.</p>
    </header>
    <section class="panel session-form">
        <form action="{{ route('admin.send-testmail.store') }}" method="POST" data-submit-once>
            @csrf
            <h2>SMTP configuration</h2>
            <p class="quiet">Defaults come from .env through Laravel configuration. Changes apply only to this test and do not update .env or registration emails.</p>
            <div class="grid-two">
                <div class="field"><label for="host">SMTP host *</label><input id="host" name="host" value="{{ old('host', $mailSettings['host']) }}" maxlength="253" required spellcheck="false"></div>
                <div class="field"><label for="port">SMTP port *</label><input type="number" id="port" name="port" value="{{ old('port', $mailSettings['port']) }}" min="1" max="65535" required></div>
            </div>
            <div class="field"><label for="scheme">Encryption *</label><select id="scheme" name="scheme" required><option value="smtp" @selected(old('scheme', $mailSettings['scheme']) === 'smtp')>STARTTLS (SMTP, usually port 587)</option><option value="smtps" @selected(old('scheme', $mailSettings['scheme']) === 'smtps')>SSL/TLS (SMTPS, usually port 465)</option></select></div>
            <div class="field"><label for="username">SMTP username *</label><input id="username" name="username" value="{{ old('username', $mailSettings['username']) }}" maxlength="254" autocomplete="off" required spellcheck="false"></div>
            <div class="field"><label for="password">SMTP password</label><input type="password" id="password" name="password" autocomplete="new-password" maxlength="4096" placeholder="{{ $hasSavedPassword ? 'Leave blank to use the password from .env' : 'Enter SMTP password' }}"><p class="quiet">{{ $hasSavedPassword ? 'A password is configured in .env. It is never displayed here.' : 'No password is configured in .env.' }} Enter a password when changing the host or username.</p></div>
            <div class="grid-two">
                <div class="field"><label for="from_address">From email *</label><input type="email" id="from_address" name="from_address" value="{{ old('from_address', $mailSettings['from_address']) }}" maxlength="254" required></div>
                <div class="field"><label for="from_name">From name</label><input id="from_name" name="from_name" value="{{ old('from_name', $mailSettings['from_name']) }}" maxlength="150"></div>
            </div>
            <h2>Send a test message</h2>
            <div class="field"><label for="email">Recipient email *</label><input type="email" id="email" name="email" value="{{ old('email') }}" maxlength="254" autocomplete="email" placeholder="recipient@example.com" required></div>
            <p class="quiet">Sends one test email immediately. No booking is created. Up to 3 attempts per minute.</p>
            <button class="button" type="submit" data-loading-text="Sending…">Send test email</button>
        </form>
    </section>
@endsection
