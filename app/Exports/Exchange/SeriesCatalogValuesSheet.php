<?php

namespace App\Exports\Exchange;

use App\Exports\Exchange\Concerns\FormatsExchangeSheet;
use App\Models\CatalogEdition;
use App\Models\SeriesCatalogValue;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class SeriesCatalogValuesSheet implements FromArray, WithEvents, WithTitle
{
    use FormatsExchangeSheet;

    public function __construct(
        private readonly CatalogEdition $edition
    ) {}

    public function array(): array
    {
        $rows = [
            [
                'exchange_series_id',
                'condition_slug',
                'catalog_number',
                'catalog_value',
                'currency',
                'notes',
            ],
        ];

        $values = SeriesCatalogValue::query()
            ->with(['stampSeries', 'condition'])
            ->where('catalog_edition_id', $this->edition->id)
            ->orderBy('stamp_series_id')
            ->orderBy('condition_id')
            ->orderBy('id')
            ->get();

        foreach ($values as $value) {
            $rows[] = [
                $value->stamp_series_id,
                $value->condition?->slug,
                $value->catalog_number,
                $value->catalog_value,
                $value->currency,
                $value->notes,
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Seriecataloguswaarden';
    }
}
