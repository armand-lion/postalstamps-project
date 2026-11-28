<?php

namespace App\Http\Requests\Backend;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StampBulkEditRequest extends FormRequest
{
    /**
     * Bepaal of de gebruiker bulkbewerkingen mag uitvoeren.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * Geef de validatieregels voor de voorgestelde bulkwijzigingen.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::operationRules();
    }

    /**
     * Geef de herbruikbare validatieregels voor bulkbewerkingen.
     *
     * @return array<string, mixed>
     */
    public static function operationRules(): array
    {
        $requiredOperations = [
            'unchanged',
            'set',
        ];

        $optionalOperations = [
            'unchanged',
            'set',
            'fill',
            'clear',
        ];

        $themeOperations = [
            'unchanged',
            'add',
            'remove',
            'replace',
            'clear',
        ];

        return [

            // Land of gebied.
            'country_operation' => [
                'required',
                Rule::in($requiredOperations),
            ],

            'country_id' => [
                'exclude_unless:country_operation,set',
                'required',
                'integer',
                'exists:countries,id',
            ],

            // Uitgiftejaar.
            'issue_year_operation' => [
                'required',
                Rule::in($requiredOperations),
            ],

            'issue_year' => [
                'exclude_unless:issue_year_operation,set',
                'required',
                'integer',
                'min:1840',
                'max:2200',
            ],

            // Uitgiftedatum.
            'issue_date_operation' => [
                'required',
                Rule::in($optionalOperations),
            ],

            'issue_date' => [
                'exclude_unless:issue_date_operation,set,fill',
                'required',
                'date_format:Y-m-d',
            ],

            // Postzegelserie.
            'stamp_series_operation' => [
                'required',
                Rule::in($optionalOperations),
            ],

            'stamp_series_id' => [
                'exclude_unless:stamp_series_operation,set,fill',
                'required',
                'integer',
                'exists:stamp_series,id',
            ],

            // Nominale waarde.
            'face_value_operation' => [
                'required',
                Rule::in($optionalOperations),
            ],

            'face_value' => [
                'exclude_unless:face_value_operation,set,fill',
                'required',
                'string',
                'max:50',
            ],

            // Valuta.
            'currency_operation' => [
                'required',
                Rule::in($optionalOperations),
            ],

            'currency' => [
                'exclude_unless:currency_operation,set,fill',
                'required',
                'string',
                'size:3',
            ],

            // Ontwerper.
            'designer_operation' => [
                'required',
                Rule::in($optionalOperations),
            ],

            'designer' => [
                'exclude_unless:designer_operation,set,fill',
                'required',
                'string',
                'max:255',
            ],

            // Druktechniek.
            'printing_method_operation' => [
                'required',
                Rule::in($optionalOperations),
            ],

            'printing_method' => [
                'exclude_unless:printing_method_operation,set,fill',
                'required',
                'string',
                'max:255',
            ],

            // Notities.
            'notes_operation' => [
                'required',
                Rule::in($optionalOperations),
            ],

            'notes' => [
                'exclude_unless:notes_operation,set,fill',
                'required',
                'string',
            ],

            // Thema's.
            'theme_operation' => [
                'required',
                Rule::in($themeOperations),
            ],

            'theme_ids' => [
                'exclude_unless:theme_operation,add,remove,replace',
                'required',
                'array',
                'min:1',
            ],

            'theme_ids.*' => [
                'integer',
                'distinct',
                'exists:themes,id',
            ],
        ];
    }
}
