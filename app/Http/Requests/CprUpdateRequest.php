<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CprUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Ownership check is handled in CprController::authorizeRecord()
    }

    public function rules(): array
    {
        return [
            // registration_number is the primary identifier — warn but don't
            // block if it's missing (parse errors are a known case).
            'registration_number' => 'nullable|string|max:100',
            'brand_name'          => 'nullable|string|max:255',
            'generic_name'        => 'nullable|string|max:255',

            // expiry_date drives status — require it if you want status to be
            // meaningful. Change to 'required|date' if blank submissions should
            // be blocked outright.
            'expiry_date'         => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'expiry_date.date' => 'Expiry date must be a valid date (e.g. 2026-12-31).',
        ];
    }
}