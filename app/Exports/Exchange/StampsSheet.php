<?php

namespace App\Exports\Exchange;

use App\Exports\Exchange\Concerns\FormatsExchangeSheet;
use App\Models\CatalogEdition;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class StampsSheet implements FromArray, WithEvents, WithTitle
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
                'parent_exchange_stamp_id',
                'catalog_number',
                'exchange_series_id',
                'country_slug',
                'country_iso_code',
                'name',
                'description',
                'issue_year',
                'issue_date',
                'face_value',
                'currency',
                'designer',
                'printing_method',
                'catalog_reference',
                'notes',
                'catalog_entry_notes',
            ],
        ];

        $entries = $this->edition->stampEntries()
            ->with('stamp.country')
            ->orderBy('catalog_number')
            ->get();

        $exchangeIdsByStampId = $entries->pluck('id', 'stamp_id');

        foreach ($entries as $entry) {
            $stamp = $entry->stamp;

            if (! $stamp) {
                continue;
            }

            $rows[] = [
                $entry->id,
                $stamp->parent_stamp_id
                    ? $exchangeIdsByStampId->get($stamp->parent_stamp_id)
                    : null,
                $entry->catalog_number,
                $stamp->stamp_series_id,
                $stamp->country?->slug,
                $stamp->country?->iso_code,
                $stamp->name,
                $stamp->description,
                $stamp->issue_year,
                $stamp->issue_date?->format('Y-m-d'),
                $stamp->face_value,
                $stamp->currency,
                $stamp->designer,
                $stamp->printing_method,
                $stamp->catalog_reference,
                $stamp->notes,
                $entry->notes,
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Postzegels';
    }
}
