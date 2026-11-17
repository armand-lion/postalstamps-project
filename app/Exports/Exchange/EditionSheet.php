<?php

namespace App\Exports\Exchange;

use App\Exports\Exchange\Concerns\FormatsExchangeSheet;
use App\Models\CatalogEdition;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class EditionSheet implements FromArray, WithEvents, WithTitle
{
    use FormatsExchangeSheet;

    public function __construct(
        private readonly CatalogEdition $edition
    ) {}

    public function array(): array
    {
        return [
            [
                'edition_name',
                'edition_year',
                'publication_date',
                'currency',
                'notes',
            ],
            [
                $this->edition->edition_name,
                $this->edition->edition_year,
                $this->edition->publication_date?->format('Y-m-d'),
                $this->edition->currency,
                $this->edition->notes,
            ],
        ];
    }

    public function title(): string
    {
        return 'Editie';
    }
}
