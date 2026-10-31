<?php

namespace App\Support;

use App\Models\Registration;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\URL;

class RegistrationQrCode
{
    public function url(Registration $registration): string
    {
        return URL::signedRoute('admin.check-ins.show', ['registration' => $registration->reference]);
    }

    public function png(Registration $registration): string
    {
        return (new PngWriter)->write(new QrCode(data: $this->url($registration), size: 320, margin: 20))->getString();
    }

    public function dataUri(Registration $registration): string
    {
        return 'data:image/png;base64,'.base64_encode($this->png($registration));
    }
}
