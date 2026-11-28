<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\StampChangeBatch;
use App\Models\StampSeries;
use App\Models\Theme;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StampChangeHistoryController extends Controller
{
    /**
     * Toon de algemene wijzigingsgeschiedenis van postzegels.
     */
    public function index(Request $request): View
    {
        $isAdmin = $request->user()->is_admin === true;

        $batches = StampChangeBatch::query()
            ->select($this->batchColumns($isAdmin))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('backend.stamps.history.index', [
            'batches' => $batches,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Toon alle postzegelwijzigingen binnen een geschiedenisbatch.
     */
    public function show(
        Request $request,
        int $batch
    ): View {
        $isAdmin = $request->user()->is_admin === true;

        $historyBatch = StampChangeBatch::query()
            ->select($this->batchColumns($isAdmin))
            ->findOrFail($batch);

        $logs = $historyBatch->logs()
            ->with('stamp.translations')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        // Verzamel alle oude en nieuwe waarden van de huidige pagina.
        $historicalValues = collect($logs->items())
            ->map(fn ($log) => [
                $log->old_values,
                $log->new_values,
            ])
            ->collapse();

        // Verzamel de gebruikte landen en gebieden.
        $countryIds = $historicalValues
            ->pluck('country_id')
            ->filter()
            ->unique()
            ->values();

        // Verzamel de gebruikte postzegelseries.
        $seriesIds = $historicalValues
            ->pluck('stamp_series_id')
            ->filter()
            ->unique()
            ->values();

        // Verzamel de gebruikte thema's.
        $themeIds = $historicalValues
            ->flatMap(function ($values): array {
                if (! is_array($values)) {
                    return [];
                }

                $ids = $values['theme_ids'] ?? [];

                return is_array($ids) ? $ids : [];
            })
            ->filter()
            ->unique()
            ->values();

        // Haal de benodigde landen en gebieden op.
        $countriesById = Country::query()
            ->with('translations')
            ->whereIn('id', $countryIds)
            ->get()
            ->keyBy('id');

        // Haal de benodigde postzegelseries op.
        $seriesById = StampSeries::query()
            ->with('translations')
            ->whereIn('id', $seriesIds)
            ->get()
            ->keyBy('id');

        // Haal de benodigde thema's op.
        $themesById = Theme::query()
            ->with('translations')
            ->whereIn('id', $themeIds)
            ->get()
            ->keyBy('id');

        return view('backend.stamps.history.show', [
            'batch' => $historyBatch,
            'logs' => $logs,
            'countriesById' => $countriesById,
            'seriesById' => $seriesById,
            'themesById' => $themesById,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Bepaal welke geschiedenisvelden de gebruiker mag raadplegen.
     *
     * @return array<int, string>
     */
    private function batchColumns(bool $isAdmin): array
    {
        $columns = [
            'id',
            'operation',
            'description',
            'stamp_count',
            'created_at',
        ];

        if ($isAdmin) {
            $columns[] = 'user_id';
            $columns[] = 'user_name';
        }

        return $columns;
    }
}
