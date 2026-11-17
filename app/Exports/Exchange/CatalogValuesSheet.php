<?php

namespace App\Exports\Exchange;

use App\Exports\Exchange\Concerns\FormatsExchangeSheet;
use App\Models\CatalogEdition;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class CatalogValuesSheet implements FromArray, WithEvents, WithTitle
{
    use FormatsExchangeSheet;

    public function __construct(
        private readonly CatalogEdition $edition
    ) {}

    public function array(): array
    {
        $rows = [
            [
                'exchange_stamp_id',
                'catalog_number',
                'condition_slug',
                'catalog_value',
                'currency',
                'notes',
                'needs_review',
            ],
        ];

        $entries = $this->edition->stampEntries()
            ->with('values.condition')
            ->orderBy('catalog_number')
            ->get();

        foreach ($entries as $entry) {
            foreach ($entry->values as $value) {
                $rows[] = [
                    $entry->id,
                    $entry->catalog_number,
                    $value->condition?->slug,
                    $value->catalog_value,
                    $value->currency,
                    $value->notes,
                    (int) $value->needs_review,
                ];
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Cataloguswaarden';
    }
}
