<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRegistrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // Shape validation only: mutable business availability is evaluated after idempotent replay.
        return [
            'idempotency_token' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
            'event_session_id' => ['required', 'integer', 'min:1'], 'selected_date' => ['required', 'date_format:Y-m-d'],
            'guest_count' => ['required', 'integer', 'min:1', 'max:4'], 'title' => ['required', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:150'], 'last_name' => ['required', 'string', 'max:150'],
            'phone_country_code' => ['required', 'string', 'size:2'], 'phone_dial_code' => ['required', 'string', 'max:8'],
            'phone_number' => ['required', 'string', 'max:32', 'regex:/^[0-9 ()+\-]{5,32}$/'],
            'email' => ['required', 'email:rfc', 'max:254'], 'referral_source' => ['required', 'string', 'max:40'],
            'mainland_customer' => ['nullable', 'boolean'], 'consents' => ['nullable', 'array:privacy,marketing_direct,marketing_sharing,mainland_transfer'],
            'consents.*' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $data = [];
        if ($this->boolean('consents.mainland_transfer')) {
            $data['mainland_customer'] = true;
        }
        foreach (['title', 'first_name', 'last_name', 'phone_number', 'phone_dial_code', 'email', 'referral_source'] as $key) {
            if (is_string($this->input($key))) {
                $data[$key] = trim($this->input($key));
            }
        }
        if (is_string($this->input('phone_country_code'))) {
            $data['phone_country_code'] = strtoupper($this->input('phone_country_code'));
        }
        $this->merge($data);
    }
}
