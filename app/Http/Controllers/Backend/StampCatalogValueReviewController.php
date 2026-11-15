<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\StampCatalogValue;
use Illuminate\Http\RedirectResponse;

class StampCatalogValueReviewController extends Controller
{
    public function update(StampCatalogValue $stampCatalogValue): RedirectResponse
    {
        // Alleen cataloguswaarden die nog gecontroleerd moeten worden aanpassen.
        if ($stampCatalogValue->needs_review) {
            $stampCatalogValue->update([
                'needs_review' => false,
            ]);
        }

        return back()->with(
            'success',
            __('De cataloguswaarde is als gecontroleerd gemarkeerd.')
        );
    }
}
