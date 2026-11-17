<?php

namespace App\Exports\Exchange;

use App\Exports\Exchange\Concerns\FormatsExchangeSheet;
use App\Models\Catalog;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class ConditionRulesSheet implements FromArray, WithEvents, WithTitle
{
    use FormatsExchangeSheet;

    public function __construct(
        private readonly Catalog $catalog
    ) {}

    public function array(): array
    {
        $rows = [
            [
                'country_slug',
                'country_iso_code',
                'condition_slug',
                'issue_year_from',
                'issue_year_to',
                'sort_order',
            ],
        ];

        $rules = $this->catalog->conditionRules()
            ->with(['country', 'condition'])
            ->orderBy('sort_order')
            ->get();

        foreach ($rules as $rule) {
            $rows[] = [
                $rule->country?->slug,
                $rule->country?->iso_code,
                $rule->condition->slug,
                $rule->issue_year_from,
                $rule->issue_year_to,
                $rule->sort_order,
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Waarderingsregels';
    }
}
