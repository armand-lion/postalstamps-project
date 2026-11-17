<?php

namespace App\Exports\Exchange\Concerns;

use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

trait FormatsExchangeSheet
{
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();

                $highestColumn = $sheet->getHighestDataColumn();

                // Kolombreedtes automatisch aanpassen.
                for ($index = 1; $index <= Coordinate::columnIndexFromString($highestColumn); $index++) {
                    $column = Coordinate::stringFromColumnIndex($index);

                    $sheet->getColumnDimension($column)->setAutoSize(true);
                }

                // Lange tekstkolommen een vaste breedte geven.
                $headers = $sheet->rangeToArray('A1:'.$highestColumn.'1')[0];

                foreach ($headers as $index => $header) {
                    if (in_array($header, ['description', 'notes'], true)) {
                        $column = Coordinate::stringFromColumnIndex($index + 1);

                        $sheet->getColumnDimension($column)
                            ->setAutoSize(false)
                            ->setWidth(50);
                    }
                }
                // Lange teksten mogen over meerdere regels lopen.
                $sheet->getStyle(
                    'A1:'.$highestColumn.$sheet->getHighestDataRow()
                )->getAlignment()->setWrapText(true);

                // Kopteksten vetgedrukt weergeven.
                $sheet->getStyle('A1:'.$highestColumn.'1')
                    ->getFont()
                    ->setBold(true);

                // Kopteksten verticaal centreren.
                $sheet->getStyle('A1:'.$highestColumn.'1')
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
