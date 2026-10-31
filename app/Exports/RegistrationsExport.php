<?php

namespace App\Exports;

use Illuminate\Database\Eloquent\Builder;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

class RegistrationsExport
{
    public function write(Builder $query): void
    {
        $writer = new Writer;
        $writer->openToFile('php://output');
        try {
            $sheet = $writer->getCurrentSheet();
            $sheet->setName('Registrations');
            $sheet->setColumnWidthForRange(24, 1, 15);
            $headers = ['Reference', 'Title', 'First name', 'Last name', 'Email', 'Phone country', 'Dial code', 'Phone number', 'Session date', 'Session time', 'Session timezone', 'Guests', 'Referral source', 'Registered at ('.config('registration.timezone').')'];
            $headerStyle = new Style(fontBold: true, backgroundColor: 'DCE6DF');
            $headers[] = 'Registration URL';
            $writer->addRow(new Row(array_map(fn ($value) => new StringCell($value, $headerStyle), $headers)));
            // Lazy chunks include relationships without loading the entire result set.
            foreach ($query->reorder()->lazyById(500) as $registration) {
                $values = [
                    $registration->reference, $registration->title, $registration->first_name,
                    $registration->last_name, $registration->email, $registration->phone_country_code,
                    $registration->phone_dial_code, $registration->phone_number,
                    $registration->session_date_snapshot->format('Y-m-d'), $registration->session_time_snapshot,
                    $registration->timezone_snapshot, $registration->guest_count,
                    $registration->referral_source,
                    $registration->created_at->timezone(config('registration.timezone'))->format('Y-m-d H:i:s'),
                    $registration->registration_url,
                ];
                // Explicit string cells preserve phone numbers and prevent formula injection.
                $writer->addRow(new Row(array_map(fn ($value) => is_int($value)
                    ? new NumericCell($value) : new StringCell((string) $value), $values)));
            }
        } finally {
            $writer->close();
        }
    }
}
