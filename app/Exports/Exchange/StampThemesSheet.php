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
            ->with('stamp.themes')
            ->orderBy('catalog_number')
            ->get();

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
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Postzegelthemas';
    }
}
