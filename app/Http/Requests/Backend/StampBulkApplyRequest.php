<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;

class StampBulkApplyRequest extends FormRequest
{
    /**
     * Bepaal of de gebruiker de bulkbewerking mag uitvoeren.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Geef de validatieregels voor de definitieve bevestiging.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'confirmed' => [
                'required',
                'accepted',
            ],

            'preview_hash' => [
                'required',
                'string',
                'size:64',
                'regex:/\A[a-f0-9]{64}\z/',
            ],
        ];
    }
}
