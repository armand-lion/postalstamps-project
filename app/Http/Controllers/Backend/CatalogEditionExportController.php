<?php

namespace App\Http\Controllers\Backend;

use App\Exports\CatalogEditionExcelExport;
use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CatalogEditionExportController extends Controller
{
    /**
     * Exporteer de catalogusgegevens van een cataloguseditie.
     */
    public function export(Catalog $catalog, CatalogEdition $edition): StreamedResponse
    {
        // Controleer of de editie bij deze catalogus hoort.
        abort_unless(
            $edition->catalog_id === $catalog->id,
            404
        );

        // Bereid de query voor zonder alle gegevens direct op te halen.
        $entriesQuery = $edition->stampEntries()
            ->with([
                'stamp',
                'values.condition',
            ]);

        // De CSV-export voegen we in de volgende stap toe.
        return response()->streamDownload(
            function () use ($entriesQuery, $catalog, $edition): void {
                $handle = fopen('php://output', 'w');

                if ($handle === false) {
                    throw new \RuntimeException('Kan CSV-uitvoer niet openen.');
                }

                // UTF-8 BOM voor correcte weergave in Excel.
                fwrite($handle, "\xEF\xBB\xBF");

                // Kolomkoppen van het CSV-bestand.
                fputcsv($handle, [
                    'stamp_id',
                    'country_id',
                    'stamp_name',
                    'issue_year',
                    'catalog_id',
                    'catalog_name',
                    'catalog_abbreviation',
                    'catalog_edition_id',
                    'edition_name',
                    'edition_year',
                    'catalog_number',
                    'condition_slug',
                    'catalog_value',
                    'currency',
                    'needs_review',
                ], ';');

                // Verwerk de catalogusvermeldingen in groepen van 500.
                $entriesQuery->chunkById(500, function ($entries) use ($handle, $catalog, $edition): void {
                    foreach ($entries as $entry) {
                        $stamp = $entry->stamp;

                        // Sla vermeldingen zonder gekoppelde postzegel over.
                        if ($stamp === null) {
                            continue;
                        }

                        // Basisgegevens voor iedere CSV-regel.
                        $baseRow = [
                            $stamp->id,
                            $stamp->country_id,
                            $stamp->name,
                            $stamp->issue_year,
                            $catalog->id,
                            $catalog->name,
                            $catalog->abbreviation,
                            $edition->id,
                            $edition->edition_name,
                            $edition->edition_year,
                            $entry->catalog_number,
                        ];

                        // Behoud ook catalogusvermeldingen zonder waarden.
                        if ($entry->values->isEmpty()) {
                            fputcsv($handle, [
                                ...$baseRow,
                                '',
                                '',
                                '',
                                '',
                            ], ';');

                            continue;
                        }

                        // Schrijf voor iedere waarderingscategorie een aparte regel.
                        foreach ($entry->values as $value) {
                            fputcsv($handle, [
                                ...$baseRow,
                                $value->condition->slug ?? '',
                                $value->catalog_value,
                                $value->currency,
                                (int) $value->needs_review,
                            ], ';');
                        }
                    }
                });

                fclose($handle);
            },
            'catalogus-'.$catalog->id.'-editie-'.$edition->id.'.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }

    /**
     * Exporteer de catalogusgegevens van een cataloguseditie naar Excel.
     */
    public function exportExcel(Catalog $catalog, CatalogEdition $edition): BinaryFileResponse
    {
        // Controleer of de editie bij deze catalogus hoort.
        abort_unless(
            $edition->catalog_id === $catalog->id,
            404
        );

        // Genereer het Excel-bestand.
        return Excel::download(
            new CatalogEditionExcelExport($catalog, $edition),
            'catalogus-'.$catalog->id.'-editie-'.$edition->id.'.xlsx'
        );
    }
}
