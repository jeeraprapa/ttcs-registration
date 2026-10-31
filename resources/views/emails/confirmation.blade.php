<!doctype html>
<html lang="en"><body style="margin:0;background:#ffffff;color:#222;font-family:Arial,sans-serif">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center">
<table role="presentation" width="600" cellspacing="0" cellpadding="24" style="max-width:600px;width:100%"><tr><td align="center" style="background:#efefec;font-size:24px;letter-spacing:3px"><img src="{{ $message->embed(public_path('assets/logo.png')) }}" width="145" alt="Upper House Residences" style="display:block;width:145px;height:auto;margin:auto"></td></tr>
<tr><td style="padding:0"><img src="{{ $message->embed(public_path('assets/residences.jpg')) }}" width="600" alt="Upper House Residences" style="display:block;width:100%;height:auto"></td></tr>
<tr><td style="text-align:center">
@include('components.booking-confirmation', ['qrSource' => $message->embedData(app(\App\Support\RegistrationQrCode::class)->png($registration), 'check-in.png', 'image/png')])
</td></tr><tr><td align="center" style="background:#efefec"><a href="https://www.uhresidencesbangkok.com/" style="color:#222">UPPER HOUSE RESIDENCES</a></td></tr><tr><td style="padding:0"><img src="{{ $message->embed(public_path('assets/bg_footer.png')) }}" width="600" alt="" style="display:block;width:100%;height:auto"></td></tr></table>
</td></tr></table></body></html>
