<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Catalog;
use App\Models\CatalogChangeBatch;
use App\Models\CatalogEdition;
use App\Models\Condition;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogEditionHistoryController extends Controller
{
    /**
     * Toon de wijzigingsgeschiedenis van een cataloguseditie.
     */
    public function index(
        Request $request,
        Catalog $catalog,
        CatalogEdition $edition
    ): View {
        abort_unless($edition->catalog_id === $catalog->id, 404);

        $isAdmin = $request->user()->is_admin === true;

        $batches = CatalogChangeBatch::query()
            ->select($this->batchColumns($isAdmin))
            ->where('catalog_edition_id', $edition->id)
            ->withCount('logs')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('backend.catalog-editions.history.index', [
            'catalog' => $catalog,
            'edition' => $edition,
            'batches' => $batches,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Toon alle wijzigingen binnen een geschiedenisbatch.
     */
    public function show(
        Request $request,
        Catalog $catalog,
        CatalogEdition $edition,
        int $batch
    ): View {
        abort_unless($edition->catalog_id === $catalog->id, 404);

        $isAdmin = $request->user()->is_admin === true;

        $historyBatch = CatalogChangeBatch::query()
            ->select($this->batchColumns($isAdmin))
            ->where('catalog_edition_id', $edition->id)
            ->withCount('logs')
            ->findOrFail($batch);

        $logs = $historyBatch->logs()
            ->with([
                'stamp.translations',
                'stampSeries.translations',
            ])
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        // Verzamel alle gebruikte oude en nieuwe waarderingscategorieën.
        $conditionIds = collect($logs->items())
            ->flatMap(fn ($log) => [
                $log->condition_id,
                $log->old_values['condition_id'] ?? null,
                $log->new_values['condition_id'] ?? null,
            ])
            ->filter()
            ->unique()
            ->values();

        // Haal alle benodigde waarderingscategorieën in één query op.
        $conditionsById = Condition::query()
            ->with('translations')
            ->whereIn('id', $conditionIds)
            ->get()
            ->keyBy('id');

        return view('backend.catalog-editions.history.show', [
            'catalog' => $catalog,
            'edition' => $edition,
            'batch' => $historyBatch,
            'logs' => $logs,
            'conditionsById' => $conditionsById,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Bepaal welke geschiedenisvelden de gebruiker mag raadplegen.
     */
    private function batchColumns(bool $isAdmin): array
    {
        $columns = [
            'id',
            'catalog_edition_id',
            'operation',
            'description',
            'catalog_name',
            'catalog_edition_name',
            'created_at',
        ];

        if ($isAdmin) {
            $columns[] = 'user_id';
            $columns[] = 'user_name';
        }

        return $columns;
    }
}
