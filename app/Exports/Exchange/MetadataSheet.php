<?php

namespace App\Exports\Exchange;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

class MetadataSheet implements FromArray, WithEvents, WithTitle
{
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                // Formaatversie expliciet als tekst opslaan.
                $sheet->getCell('B3')
                    ->setValueExplicit('1.1', DataType::TYPE_STRING);

                // Kolombreedtes instellen.
                $sheet->getColumnDimension('A')->setWidth(22);
                $sheet->getColumnDimension('B')->setWidth(40);
            },
        ];
    }

    public function array(): array
    {
        return [
            ['key', 'value'],
            ['format', 'postalstamps-catalog-exchange'],
            ['format_version', '1.0'],
            ['exported_at', now()->toIso8601String()],
        ];
    }

    public function title(): string
    {
        return 'Metadata';
    }
}
