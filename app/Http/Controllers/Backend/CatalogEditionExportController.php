<?php

namespace App\Http\Controllers\Backend;

use App\Exports\Exchange\CatalogEditionExchangeExcelExport;
use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CatalogEditionExportController extends Controller
{
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

        // Genereer het Excel-uitwisselingsbestand v1.1.
        return Excel::download(
            new CatalogEditionExchangeExcelExport($catalog, $edition),
            'catalogus-'.$catalog->id.'-editie-'.$edition->id.'-uitwisseling-v1.1.xlsx'
        );
    }
}
