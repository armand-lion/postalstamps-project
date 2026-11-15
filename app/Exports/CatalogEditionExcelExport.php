<?php

namespace App\Exports;

use App\Models\Catalog;
use App\Models\CatalogEdition;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CatalogEditionExcelExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        private readonly Catalog $catalog,
        private readonly CatalogEdition $edition
    ) {}

    public function query(): Builder
    {
        return $this->edition->stampEntries()
            ->getQuery()
            ->with([
                'stamp',
                'values.condition',
            ]);
    }

    public function headings(): array
    {
        return [
            'stamp_id',
            'country_id',
            'stamp_name',
            'issue_year',
            'catalog_id',
            'catalog_name',
            'catalog_abbreviation',
            'catalog_edition_id',
            'edition_name',
            'edition_year',
            'catalog_number',
            'condition_slug',
            'catalog_value',
            'currency',
            'needs_review',
        ];
    }

    public function map($entry): array
    {
        $stamp = $entry->stamp;

        if ($stamp === null) {
            return [];
        }

        $baseRow = [
            $stamp->id,
            $stamp->country_id,
            $stamp->name,
            $stamp->issue_year,
            $this->catalog->id,
            $this->catalog->name,
            $this->catalog->abbreviation,
            $this->edition->id,
            $this->edition->edition_name,
            $this->edition->edition_year,
            $entry->catalog_number,
        ];

        if ($entry->values->isEmpty()) {
            return [[
                ...$baseRow,
                '',
                '',
                '',
                '',
            ]];
        }

        $rows = [];

        foreach ($entry->values as $value) {
            $rows[] = [
                ...$baseRow,
                $value->condition->slug ?? '',
                $value->catalog_value,
                $value->currency,
                (int) $value->needs_review,
            ];
        }

        return $rows;
    }
}
