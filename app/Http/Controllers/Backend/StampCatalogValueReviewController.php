<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\StampCatalogValue;
use App\Services\CatalogChangeHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StampCatalogValueReviewController extends Controller
{
    public function update(
        Request $request,
        StampCatalogValue $stampCatalogValue,
        CatalogChangeHistoryService $history
    ): RedirectResponse {
        // Alleen cataloguswaarden die nog gecontroleerd moeten worden aanpassen.
        if ($stampCatalogValue->needs_review) {

            // Haal de bijbehorende cataloguseditie op.
            $catalogEdition = $stampCatalogValue->catalogEntry->catalogEdition;

            // Haal de gebruiker op die de cataloguswaarde controleert.
            $user = $request->user();

            // Sla de wijziging veilig op binnen een databasetransactie.
            try {
                DB::transaction(function () use ($stampCatalogValue, $catalogEdition, $history, $user) {

                    // Maak een geschiedenisbatch aan voor deze controlebewerking.
                    $batch = $history->createBatch(
                        $catalogEdition,
                        'review',
                        $user,
                        'Cataloguswaarde als gecontroleerd gemarkeerd'
                    );

                    // Activeer de geschiedenisbatch voor de modelobservers.
                    $history->setActiveBatch($batch);

                    $stampCatalogValue->update([
                        'needs_review' => false,
                    ]);

                    // Verwijder de geschiedenisbatch als er geen wijzigingen zijn geregistreerd.
                    if (! $batch->logs()->exists()) {
                        $batch->delete();
                    }

                });
            } finally {
                // Wis altijd de actieve geschiedenisbatch.
                $history->clearActiveBatch();
            }
        }

        return back()->with(
            'success',
            __('De cataloguswaarde is als gecontroleerd gemarkeerd.')
        );
    }
}
