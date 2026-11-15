<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\CatalogEdition;
use Illuminate\Contracts\View\View;

class CatalogEditionReviewController extends Controller
{
    /**
     * Toon het overzicht van cataloguswaarden die nog gecontroleerd moeten worden.
     */
    public function index(Catalog $catalog, CatalogEdition $edition): View
    {
        // Controleer of de editie bij de geselecteerde catalogus hoort.
        abort_unless(
            $edition->catalog_id === $catalog->id,
            404
        );

        // Haal uitsluitend cataloguswaarden op die nog gecontroleerd moeten worden.
        $valuesNeedingReview = $edition->stampValues()
            ->where('needs_review', true)
            ->with([
                'catalogEntry.stamp',
                'condition.translations',
            ])
            ->orderBy('stamp_catalog_entry_id')
            ->orderBy('condition_id')
            ->paginate(25);

        // Toon het controleoverzicht.
        return view('backend.catalog-editions.review', [
            'catalog' => $catalog,
            'edition' => $edition,
            'valuesNeedingReview' => $valuesNeedingReview,
        ]);
    }
}
