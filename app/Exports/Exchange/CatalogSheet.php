<?php

namespace App\Exports\Exchange;

use App\Exports\Exchange\Concerns\FormatsExchangeSheet;
use App\Models\Catalog;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;

class CatalogSheet implements FromArray, WithEvents, WithTitle
{
    use FormatsExchangeSheet;

    public function __construct(
        private readonly Catalog $catalog
    ) {}

    public function array(): array
    {
        return [
            [
                'name',
                'abbreviation',
                'publisher',
                'website',
                'description',
            ],
            [
                $this->catalog->name,
                $this->catalog->abbreviation,
                $this->catalog->publisher,
                $this->catalog->website,
                $this->catalog->description,
            ],
        ];
    }

    public function title(): string
    {
        return 'Catalogus';
    }
}
