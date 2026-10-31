<?php

return [
    'domain_sessions' => [
        'special_host' => 'registration.sprops.hk.uhresidencesbangkok.com',
        'public_host' => 'registration.hk.uhresidencesbangkok.com',
        'preview_hosts' => ['uh-registration.test', 'localhost', '127.0.0.1', 'g-dev.top', 'registration.uhhk.uhresidencesbangkok.com'],
        'special' => ['date' => '2026-11-14', 'time' => '11:00:00'],
    ],
    // Provisional country/title/consent rules require client sign-off before production.
    'policy_confirmed' => env('REGISTRATION_POLICY_CONFIRMED', false),
    'timezone' => env('EVENT_TIMEZONE', 'Asia/Hong_Kong'),
    'guests_include_registrant' => env('GUESTS_INCLUDE_REGISTRANT', true),
    'mail_mode' => env('REGISTRATION_MAIL_MODE', 'local'),
    'venue' => 'Sky Lounge, 49/F Upper House Hong Kong, 88 Queensway, Admiralty, Hong Kong',
    'titles' => ['Mr.', 'Mrs.', 'Ms.', 'Dr.'],
    'countries' => [
        ['code' => 'HK', 'name' => 'Hong Kong', 'dial_code' => '+852'],
        ['code' => 'TH', 'name' => 'Thailand', 'dial_code' => '+66'],
        ['code' => 'CN', 'name' => 'Chinese Mainland', 'dial_code' => '+86'],
        ['code' => 'SG', 'name' => 'Singapore', 'dial_code' => '+65'],
        ['code' => 'GB', 'name' => 'United Kingdom', 'dial_code' => '+44'],
        ['code' => 'US', 'name' => 'United States', 'dial_code' => '+1'],
    ],
    'referral_sources' => ['eDirect Mail', 'Print Advertising', 'Online Advertising', 'Digital Signage', 'Others'],
    'consents' => [
        'privacy' => ['version' => 'image-2026-10-15', 'required' => true, 'text' => 'I acknowledge and accept the privacy policy City Dynamic, Swire Properties, and Swire Hotels.'],
        'marketing_direct' => ['version' => 'image-2026-10-15', 'required' => true, 'text' => 'I consent to the City Dynamic’s processing of my full name and contact details to provide me with direct marketing materials.'],
        'marketing_sharing' => ['version' => 'image-2026-10-15', 'required' => true, 'text' => 'I consent to the City Dynamic’s disclosure of my full name and contact details to Swire Properties and Swire Hotels for them to provide me with their direct marketing materials.'],
        'mainland_transfer' => ['version' => 'image-2026-10-15', 'required' => false, 'text' => '(For Chinese Mainland clients only), I consent to sharing my personal data with affiliated service providers and partners in the Chinese Mainland/my primary residence, as well as designated overseas service providers situated outside the Chinese Mainland / my primary residence.'],
    ],
];
