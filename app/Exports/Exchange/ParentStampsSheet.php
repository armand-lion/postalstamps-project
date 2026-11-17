<?php

namespace App\Exports\Exchange;

use App\Exports\Exchange\Concerns\FormatsExchangeSheet;
use App\Models\CatalogEdition;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class ParentStampsSheet implements FromArray, WithEvents, WithTitle
{
    use FormatsExchangeSheet;

    public function __construct(
        private readonly CatalogEdition $edition
    ) {}

    public function array(): array
    {
        $rows = [
            [
                'parent_exchange_id',
                'child_exchange_stamp_id',
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
            ],
        ];

        $entries = $this->edition->stampEntries()
            ->with('stamp.parentStamp.country')
            ->orderBy('catalog_number')
            ->get();

        $stampIds = $entries->pluck('stamp_id')
            ->map(fn ($id) => (int) $id);

        foreach ($entries as $entry) {
            $stamp = $entry->stamp;

            if (! $stamp?->parentStamp) {
                continue;
            }

            $parent = $stamp->parentStamp;

            // De bovenliggende postzegel staat al in deze editie.
            if ($stampIds->contains((int) $parent->id)) {
                continue;
            }

            $rows[] = [
                'parent-'.$parent->id,
                $entry->id,
                $parent->country?->slug,
                $parent->country?->iso_code,
                $parent->name,
                $parent->description,
                $parent->issue_year,
                $parent->issue_date?->format('Y-m-d'),
                $parent->face_value,
                $parent->currency,
                $parent->designer,
                $parent->printing_method,
                $parent->catalog_reference,
                $parent->notes,
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'BovenliggendePostzegels';
    }
}
