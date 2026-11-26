<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Imports\Exchange\CatalogEditionExchangeImporter;
use App\Imports\Exchange\CatalogEditionExchangePreview;
use App\Imports\Exchange\CatalogEditionExchangeValidator;
use App\Models\Catalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CatalogEditionImportController extends Controller
{
    public function create(Catalog $catalog): View
    {
        return view('backend.catalog-editions.import', [
            'catalog' => $catalog,
        ]);
    }

    public function store(
        Request $request,
        Catalog $catalog,
        CatalogEditionExchangeValidator $validator
    ): RedirectResponse {
        $validated = $request->validate([
            'file' => [
                'required',
                'file',
                'mimes:xlsx',
                'max:20480',
            ],
        ]);

        try {
            $validator->validate(
                $validated['file']->getRealPath()
            );
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'file' => $exception->getMessage(),
            ]);
        }

        // Sla het gevalideerde Excel-bestand tijdelijk en privé op.
        $storedPath = $validated['file']->store(
            'catalog-edition-imports',
            'local'
        );

        // Controleer of het bestand succesvol is opgeslagen.
        if ($storedPath === false) {
            throw ValidationException::withMessages([
                'file' => __('Het Excel-bestand kon niet tijdelijk worden opgeslagen.'),
            ]);
        }

        // Bewaar de tijdelijke importgegevens in de sessie.
        $request->session()->put('catalog_edition_import', [
            'user_id' => $request->user()->id,
            'catalog_id' => $catalog->id,
            'file_path' => $storedPath,
        ]);

        // Open na succesvolle validatie de Excel-voorbeeldpagina.
        return redirect()
            ->route('backend.catalogs.editions.import.preview', $catalog);
    }

    /**
     * Toon de voorbeeldweergave van een gevalideerd Excel-bestand.
     */
    public function preview(
        Request $request,
        Catalog $catalog,
        CatalogEditionExchangePreview $exchangePreview
    ): View|RedirectResponse {
        // Haal de tijdelijke importgegevens uit de sessie.
        $import = $request->session()->get('catalog_edition_import');

        // Controleer of de sessie geldige importgegevens bevat.
        if (
            ! is_array($import)
            || ! isset(
                $import['user_id'],
                $import['catalog_id'],
                $import['file_path']
            )
            || (int) $import['user_id'] !== $request->user()->id
            || (int) $import['catalog_id'] !== $catalog->id
            || ! is_string($import['file_path'])
            || ! str_starts_with($import['file_path'], 'catalog-edition-imports/')
            || ! Storage::disk('local')->exists($import['file_path'])
        ) {
            return redirect()
                ->route('backend.catalogs.editions.import.create', $catalog)
                ->withErrors([
                    'file' => __('Er is geen geldig tijdelijk Excel-importbestand beschikbaar.'),
                ]);
        }

        // Lees de gegevens uit het tijdelijk opgeslagen Excel-bestand.
        $preview = $exchangePreview->getPreview(
            Storage::disk('local')->path($import['file_path'])
        );

        // Controleer of de cataloguseditie al bestaat.
        $existingEdition = $catalog->editions()
            ->where('edition_name', $preview['edition_name'])
            ->where('edition_year', $preview['edition_year'])
            ->first();

        // Toon de Excel-voorbeeldpagina.
        return view('backend.catalog-editions.import-preview', [
            'catalog' => $catalog,
            'preview' => $preview,
            'existingEdition' => $existingEdition,
        ]);
    }

    /**
     * Bevestig en voer de definitieve Excel-import uit.
     */
    public function confirm(
        Request $request,
        Catalog $catalog,
        CatalogEditionExchangeImporter $importer
    ): RedirectResponse {
        // Haal de tijdelijke importgegevens uit de sessie.
        $import = $request->session()->get('catalog_edition_import');

        // Controleer of de tijdelijke importgegevens geldig zijn.
        if (
            ! is_array($import)
            || ! isset(
                $import['user_id'],
                $import['catalog_id'],
                $import['file_path']
            )
            || (int) $import['user_id'] !== $request->user()->id
            || (int) $import['catalog_id'] !== $catalog->id
            || ! is_string($import['file_path'])
            || ! str_starts_with($import['file_path'], 'catalog-edition-imports/')
            || ! Storage::disk('local')->exists($import['file_path'])
        ) {
            return redirect()
                ->route('backend.catalogs.editions.import.create', $catalog)
                ->withErrors([
                    'file' => __('Er is geen geldig tijdelijk Excel-importbestand beschikbaar.'),
                ]);
        }

        // Controleer de gekozen importmethode.
        $validated = $request->validate([
            'import_mode' => [
                'nullable',
                'in:create,merge,replace',
            ],
        ]);

        $importMode = $validated['import_mode'] ?? 'create';

        // Bepaal het volledige pad van het tijdelijke Excel-bestand.
        $filePath = Storage::disk('local')->path($import['file_path']);

        try {
            $importedEdition = $importer->importEdition(
                $filePath,
                $catalog,
                $importMode,
                $request->user()
            );
        } catch (RuntimeException $exception) {
            // Toon de foutmelding zonder de tijdelijke import te verwijderen.
            return redirect()
                ->route('backend.catalogs.editions.import.preview', $catalog)
                ->withErrors([
                    'file' => $exception->getMessage(),
                ]);
        }

        // Verwijder het tijdelijke Excel-bestand na succesvolle import.
        Storage::disk('local')->delete($import['file_path']);

        // Verwijder de tijdelijke importgegevens uit de sessie.
        $request->session()->forget('catalog_edition_import');

        // Open de geïmporteerde cataloguseditie.
        return redirect()
            ->route('backend.catalogs.editions.show', [
                'catalog' => $catalog,
                'edition' => $importedEdition,
            ])
            ->with('success', __('De cataloguseditie is succesvol geïmporteerd.'));
    }
}
