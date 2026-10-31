@php
    $policyText=e($text);
    $policyText=str_replace(
        ['City Dynamic','Swire Properties','Swire Hotels'],
        ['<a href="https://www.uhresidencesbangkok.com/privacy-policy/" target="_blank" rel="noopener noreferrer">City Dynamic</a>','<a href="https://www.swireproperties.com/en/privacy-policy/" target="_blank" rel="noopener noreferrer">Swire Properties</a>','<a href="https://www.swirehotels.com/en/Privacy-Policy/" target="_blank" rel="noopener noreferrer">Swire Hotels</a>'],
        $policyText
    );
@endphp
{!! $policyText !!}
