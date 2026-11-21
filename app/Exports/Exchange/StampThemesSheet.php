<?php

namespace App\Exports\Exchange;

use App\Exports\Exchange\Concerns\FormatsExchangeSheet;
use App\Models\CatalogEdition;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class StampThemesSheet implements FromArray, WithEvents, WithTitle
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
                'theme_slug',
            ],
        ];

        $entries = $this->edition->stampEntries()
            ->with([
                'stamp.themes',
                'stamp.parentStamp.themes',
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

            foreach ($stamp->themes->sortBy('slug') as $theme) {
                $rows[] = [
                    $entry->id,
                    $theme->slug,
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

            // Exporteer de themakoppelingen van de externe bovenliggende postzegel.
            foreach ($parentStamp->themes->sortBy('slug') as $theme) {
                $rows[] = [
                    'parent-'.$parentStamp->id,
                    $theme->slug,
                ];
            }
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Postzegelthemas';
    }
}
