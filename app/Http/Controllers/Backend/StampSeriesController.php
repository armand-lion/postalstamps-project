<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\StampSeries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StampSeriesController extends Controller
{
    /**
     * Haal postzegelseries op voor een specifiek land en uitgiftejaar.
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'issue_year' => ['required', 'integer', 'min:1840', 'max:2200'],
        ]);

        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'nl');

        $series = StampSeries::query()
            ->with('translations')
            ->where('country_id', $validated['country_id'])
            ->where('issue_year', $validated['issue_year'])
            ->orderBy('name')
            ->get()
            ->map(function (StampSeries $series) use ($locale, $fallbackLocale) {
                $translation = $series->translations->firstWhere('locale', $locale)
                    ?? $series->translations->firstWhere('locale', $fallbackLocale);

                return [
                    'id' => $series->id,
                    'name' => $translation->name ?? $series->name,
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json($series);
    }

    /**
     * Toon alle postzegelseries.
     */
    public function index(): View
    {
        $series = StampSeries::query()
            ->with('country')
            ->withCount('stamps')
            ->orderByDesc('issue_year')
            ->orderBy('name')
            ->paginate(20);

        return view('backend.stamp-series.index', compact('series'));
    }

    /**
     * Toon het formulier om een nieuwe serie aan te maken.
     */
    public function create(): View
    {
        $countries = Country::query()
            ->orderBy('name')
            ->get();

        return view('backend.stamp-series.create', compact('countries'));
    }

    /**
     * Sla een nieuwe postzegelserie op.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:200'],
            'issue_year' => ['nullable', 'integer', 'min:1840', 'max:2200'],
            'description' => ['nullable', 'string'],
        ]);

        StampSeries::create($validated);

        return redirect()
            ->route('backend.stamp-series.index')
            ->with('success', 'Postzegelserie is aangemaakt.');
    }

    /**
     * Toon één postzegelserie.
     */
    public function show(StampSeries $stamp_series): View
    {
        $stamp_series->load([
            'country',
            'stamps',
        ]);

        return view('backend.stamp-series.show', [
            'series' => $stamp_series,
        ]);
    }

    /**
     * Toon het formulier om een serie te bewerken.
     */
    public function edit(StampSeries $stamp_series): View
    {
        $countries = Country::query()
            ->orderBy('name')
            ->get();

        return view('backend.stamp-series.edit', [
            'series' => $stamp_series,
            'countries' => $countries,
        ]);
    }

    /**
     * Werk een bestaande postzegelserie bij.
     */
    public function update(
        Request $request,
        StampSeries $stamp_series
    ): RedirectResponse {
        $validated = $request->validate([
            'country_id' => ['required', 'exists:countries,id'],
            'name' => ['required', 'string', 'max:200'],
            'issue_year' => ['nullable', 'integer', 'min:1840', 'max:2200'],
            'description' => ['nullable', 'string'],
        ]);

        $stamp_series->update($validated);

        return redirect()
            ->route('backend.stamp-series.index')
            ->with('success', 'Postzegelserie is bijgewerkt.');
    }

    /**
     * Verwijder een postzegelserie als er geen postzegels aan gekoppeld zijn.
     */
    public function destroy(StampSeries $stamp_series): RedirectResponse
    {
        $name = $stamp_series->name;

        // Voorkom verwijderen wanneer er gekoppelde gegevens bestaan.
        if (
            $stamp_series->stamps()->exists()
            || $stamp_series->catalogValues()->exists()
        ) {
            return redirect()
                ->route('backend.stamp-series.index')
                ->with(
                    'error',
                    "Postzegelserie '{$name}' kan niet worden verwijderd, omdat er nog postzegels of cataloguswaarden aan gekoppeld zijn."
                );
        }

        $stamp_series->delete();

        return redirect()
            ->route('backend.stamp-series.index')
            ->with('success', "Postzegelserie '{$name}' is verwijderd.");
    }
}
