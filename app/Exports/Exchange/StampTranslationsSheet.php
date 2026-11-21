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
            ->with([
                'stamp.translations',
                'stamp.parentStamp.translations',
            ])
            ->orderBy('catalog_number')
            ->get();

        // Houd bij welke bovenliggende postzegels al zijn geëxporteerd.
        $exportedParentStampIds = [];

        // Verzamel de ID's van postzegels die zelf in deze cataloguseditie voorkomen.
        $editionStampIds = $entries
            ->pluck('stamp_id')
            ->map(fn ($id) => (int) $id)
            ->all();

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

            // Controleer of deze postzegel een bovenliggende postzegel heeft.
            $parentStamp = $stamp->parentStamp;

            if ($parentStamp === null) {
                continue;
            }

            // Bovenliggende postzegels die zelf in deze editie voorkomen,
            // worden al via hun eigen catalogusvermelding geëxporteerd.
            if (in_array((int) $parentStamp->id, $editionStampIds, true)) {
                continue;
            }

            // Exporteer dezelfde bovenliggende postzegel niet meerdere keren.
            if (isset($exportedParentStampIds[$parentStamp->id])) {
                continue;
            }

            // Markeer de bovenliggende postzegel als verwerkt.
            $exportedParentStampIds[$parentStamp->id] = true;

            // Exporteer de vertalingen van de externe bovenliggende postzegel.
            foreach ($parentStamp->translations as $translation) {
                $rows[] = [
                    'parent-'.$parentStamp->id,
                    null,
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
