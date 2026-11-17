<?php

namespace App\Exports\Exchange;

use App\Exports\Exchange\Concerns\FormatsExchangeSheet;
use App\Models\CatalogEdition;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class StampTranslationsSheet implements FromArray, WithEvents, WithTitle
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
                'locale',
                'name',
                'description',
            ],
        ];

        $entries = $this->edition->stampEntries()
            ->with('stamp.translations')
            ->orderBy('catalog_number')
            ->get();

        foreach ($entries as $entry) {
            $stamp = $entry->stamp;

            if (! $stamp) {
                continue;
            }

            foreach ($stamp->translations as $translation) {
                $rows[] = [
                    $entry->id,
                    $entry->catalog_number,
                    $translation->locale,
                    $translation->name,
                    $translation->description,
                ];
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Postzegelvertalingen';
    }
}
