@extends('layouts.public')
@section('content')
    @php($isSpecialHost = request()->getHost() === config('registration.domain_sessions.special_host'))
    <section class="event-intro">
        <div class="event-copy"><h1>UPPER HOUSE RESIDENCES<br>HONG KONG PRIVATE PREVIEW
            </h1>
            <p class="eyebrow">A HOUSE TO EXPERIENCE.<br>A HOME TO CALL YOUR OWN – ON
                WIRELESS ROAD, BANGKOK.</p>
            <p>Discover the first Upper House branded residences, thoughtfully designed
                for a new way of living in Bangkok.</p>
            <p>Join us at Upper House Hong Kong for a closer look at the architecture,
                residences and the vision behind them.</p>
            <p>Our team looks forward to welcoming you and sharing more about this
                exceptional address.</p></div>
        <div class="event-meta">
            <div><p class="eyebrow">Event details</p>
                <p>{{ $isSpecialHost ? 'Saturday, 14 November 2026 at 11:00 AM' : '14 – 15 November 2026 | 11:00 AM – 6:00 PM' }}</p>
            </div>
            <div><p class="eyebrow">Venue</p>
                <p>Sky Lounge, 49/F Upper House Hong Kong<br>88 Queensway, Admiralty, Hong Kong</p>
            </div>
        </div>
    </section>
    <div class="booking-panel">@include('components.form-errors')
        <form action="{{ route('registration.store') }}" method="POST"
              data-submit-once>@csrf<input type="hidden" name="idempotency_token"
                                           value="{{ $idempotencyToken }}">@php($selectedDate=old('selected_date',$sessions->first()?->local_date->format('Y-m-d')))
            @if($isSpecialHost)
                <input type="hidden" name="selected_date"
                       value="{{ $sessions->first()?->local_date->format('Y-m-d') }}">
                <input type="hidden" name="event_session_id"
                       value="{{ $sessions->first()?->id }}">

            @else
                <h2 class="section-label">SELECT YOUR PREFERRED SESSION</h2>
                <div class="dates" role="group"
                     aria-label="Event date">@foreach($sessions->unique(fn($s)=>$s->local_date->format('Y-m-d')) as $day)
                        <button type="button" class="date-tab"
                                data-session-date="{{ $day->local_date->format('Y-m-d') }}"
                                aria-pressed="{{ $selectedDate===$day->local_date->format('Y-m-d')?'true':'false' }}">{{ strtoupper($day->local_date->format('l, j F Y')) }}</button>
                    @endforeach</div><input type="hidden" id="selected_date"
                                            name="selected_date"
                                            value="{{ $selectedDate }}">
                <div class="grid-two">
                    <div class="field"><label for="event_session_id">Time session
                            *</label><select id="event_session_id" name="event_session_id"
                                             required>
                            <option value="">Choose an option
                            </option>@foreach($sessions as $session)
                                <option value="{{ $session->id }}"
                                        data-date="{{ $session->local_date->format('Y-m-d') }}" @selected(old('event_session_id')==$session->id) @disabled(!$session->is_open)>{{ \Carbon\Carbon::parse($session->local_start_time)->format('h.ia') }}{{ !$session->is_open?' — Closed':'' }}</option>
                            @endforeach</select></div>
                    <div class="field"><label for="guest_count">Number of guests *</label><select
                            id="guest_count" name="guest_count" required>
                            <option value="">Choose an option
                            </option>@foreach(range(1,4) as $count)
                                <option
                                    value="{{ $count }}" @selected(old('guest_count')==$count)>{{ $count }}</option>
                            @endforeach</select></div>
                </div><p class="quiet availability">All sessions are open for
                    registration. Place are limited.</p>
            @endif
            <div class="reserve-intro"><h2 class="section-label">RESERVE YOUR PLACE</h2>
                <p class="quiet">{{ $isSpecialHost ? 'Please complete the form to confirm your attendance.' : 'Please complete the form below to register for your preferred session.' }}</p>
            </div>
            <div class="grid-name">
                <div class="field"><label for="title">Title *</label><select id="title"
                                                                             name="title"
                                                                             required>
                        <option value="">Title</option>@foreach($titles as $title)
                            <option @selected(old('title')===$title)>{{ $title }}</option>
                        @endforeach</select></div>
                <div class="field"><label for="first_name">First name *</label><input
                        id="first_name" name="first_name" value="{{ old('first_name') }}"
                        required maxlength="150" autocomplete="given-name"></div>
                <div class="field"><label for="last_name">Last name *</label><input
                        id="last_name" name="last_name" value="{{ old('last_name') }}"
                        required maxlength="150" autocomplete="family-name"></div>
            </div>
            <div class="grid-two contact-fields">
                <div class="field"><label for="phone_number">Mobile number *</label>
                    <div class="phone-row"><select id="phone_country_code"
                                                   name="phone_country_code"
                                                   aria-label="Country calling code"
                                                   required>@foreach($countries as $country)
                                <option value="{{ $country['code'] }}"
                                        data-country-name="{{ $country['name'] }}"
                                        data-dial-code="{{ $country['dial_code'] }}" @selected(old('phone_country_code','HK')===$country['code'])>{{ $country['name'] }} {{ $country['dial_code'] }}</option>
                            @endforeach</select><input id="phone_number"
                                                       name="phone_number" type="tel"
                                                       autocomplete="tel-national"
                                                       value="{{ old('phone_number') }}"
                                                       placeholder="081 234 5678"
                                                       maxlength="32" required></div>
                    <input id="phone_dial_code" name="phone_dial_code" type="hidden"
                           value="{{ old('phone_dial_code','+852') }}"></div>
                <div class="field"><label for="email">Email address *</label><input
                        id="email" name="email" type="email" autocomplete="email"
                        value="{{ old('email') }}" maxlength="254" required></div>
            </div>
            @if($isSpecialHost)
<div class="field"><label for="guest_count">Number of guests *</label><select id="guest_count" name="guest_count" required><option value="">Choose an option</option>@foreach(range(1,4) as $count)<option value="{{ $count }}" @selected(old('guest_count')==$count)>{{ $count }}</option>@endforeach</select></div>
@endif
<div class="field"><label for="referral_source">How did you hear about us?
                    *</label><select id="referral_source" name="referral_source" required>
                    <option value="">Choose an option
                    </option>@foreach($referralSources as $source)
                        <option @selected(old('referral_source')===$source)>{{ $source }}</option>
                    @endforeach</select></div>
            <div class="consents">@foreach($consents as $code=>$consent)
                    <label class="consent"
                           @if($code==='mainland_transfer') id="mainland-consent" @endif><input
                            type="checkbox" name="consents[{{ $code }}]"
                            value="1" @checked(old('consents.'.$code)) @required($consent['required'])><span>@if($code==='privacy')
                                @include('components.privacy-text',['text'=>$consent['text']])
                            @else
                                {{ $consent['text'] }}
                            @endif{{ $consent['required']?' *':'' }}</span></label>
                @endforeach</div>
            <button class="button" type="submit" data-loading-text="Registering…">Submit
            </button>
            <p class="submit-note">By submitting this form, you acknowledge and accept our
                <a href="https://www.uhresidencesbangkok.com/privacy-policy/"
                   target="_blank" rel="noopener noreferrer">Privacy Policy</a>.
            </p>@unless($isSpecialHost)
                <noscript><p>Please enable JavaScript to choose a date and time
                        session.</p></noscript>
            @endunless</form>
    </div>
    @include('components.field-errors')
@endsection
