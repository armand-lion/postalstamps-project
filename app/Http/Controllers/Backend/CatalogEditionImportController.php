<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Imports\Exchange\CatalogEditionExchangeValidator;
use App\Models\Catalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return redirect()
            ->route('backend.catalogs.editions.import.create', $catalog)
            ->with(
                'success',
                __('Het Excel-uitwisselingsbestand is succesvol gevalideerd.')
            );
    }
}
