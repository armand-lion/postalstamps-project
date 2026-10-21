<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Collectible;
use App\Models\CollectibleType;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampSeries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollectibleController extends Controller
{
    /**
     * Display a listing of the collectibles.
     */
    public function index(Request $request): View
    {
        $query = Collectible::with([
            'collectibleType.translations',
            'country',
            'stampSeries',
        ]);

        // Filter op land.
        if ($request->filled('country_id')) {
            $query->where(
                'country_id',
                $request->integer('country_id')
            );
        }

        // Filter op uitgiftejaar.
        if ($request->filled('issue_year')) {
            $query->where(
                'issue_year',
                $request->integer('issue_year')
            );
        }

        // Filter op type verzamelobject.
        if ($request->filled('collectible_type_id')) {
            $query->where(
                'collectible_type_id',
                $request->integer('collectible_type_id')
            );
        }

        $collectibles = $query
            ->orderByDesc('issue_year')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $countries = Country::orderBy('name')->get();

        $collectibleTypes = CollectibleType::with('translations')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('backend.collectibles.index', compact(
            'collectibles',
            'countries',
            'collectibleTypes'
        ));
    }

    /**
     * Show the form for creating a collectible.
     */
    public function create(): View
    {
        $countries = Country::orderBy('name')->get();

        $collectibleTypes = CollectibleType::with('translations')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $series = StampSeries::with('country')
            ->orderBy('name')
            ->get();

        // Beschikbare postzegels voor koppeling aan een verzamelobject.
        $stamps = Stamp::orderBy('id')->get();

        return view('backend.collectibles.create', compact(
            'countries',
            'collectibleTypes',
            'series',
            'stamps'
        ));
    }

    /**
     * Store a newly created collectible.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'collectible_type_id' => [
                'required',
                'integer',
                Rule::exists('collectible_types', 'id')
                    ->where('is_active', true),
            ],
            'country_id' => [
                'required',
                'integer',
                'exists:countries,id',
            ],
            'stamp_series_id' => [
                'nullable',
                'integer',
                Rule::exists('stamp_series', 'id')
                    ->where('country_id', $request->input('country_id')),
            ],
            'name' => [
                'required',
                'string',
                'max:200',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'issue_year' => [
                'required',
                'integer',
                'min:1840',
                'max:2200',
            ],
            'issue_date' => [
                'nullable',
                'date',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
            // Geselecteerde postzegels.
            'stamp_ids' => [
                'nullable',
                'array',
            ],

            'stamp_ids.*' => [
                'integer',
                Rule::exists('stamps', 'id')
                    ->where('country_id', $request->input('country_id')),
            ],
        ]);

        // Haal de geselecteerde postzegels uit de gevalideerde gegevens.
        $stampIds = $validated['stamp_ids'] ?? [];

        // Verwijder stamp_ids uit de gegevens voor de collectibles-tabel.
        unset($validated['stamp_ids']);

        // Sla het verzamelobject op.
        $collectible = Collectible::create($validated);

        // Koppel de geselecteerde postzegels aan het verzamelobject.
        $collectible->stamps()->sync($stampIds);

        return redirect()
            ->route('backend.collectibles.index')
            ->with('success', __('Verzamelobject :name is toegevoegd.', [
                'name' => $collectible->name,
            ]));
    }

    /**
     * Display a collectible.
     */
    public function show(Collectible $collectible): View
    {
        $collectible->load([
            'collectibleType.translations',
            'country',
            'stampSeries',
            'stamps',
            'images',
            'catalogEntries.catalogEdition.catalog',
            'catalogEntries.catalogValues.condition',
            'copies.condition.translation',
        ]);

        return view('backend.collectibles.show', compact('collectible'));
    }

    /**
     * Show the form for editing a collectible.
     */
    public function edit(Collectible $collectible): View
    {
        // Laad de bestaande gekoppelde postzegels.
        $collectible->load('stamps');

        // Haal alle landen op.
        $countries = Country::orderBy('name')->get();

        // Haal actieve verzamelobjecttypen op,
        // inclusief het huidige type als dit niet meer actief is.
        $collectibleTypes = CollectibleType::with('translations')
            ->where(function ($query) use ($collectible) {
                $query->where('is_active', true)
                    ->orWhere('id', $collectible->collectible_type_id);
            })
            ->orderBy('sort_order')
            ->get();

        // Haal de beschikbare postzegelseries op.
        $series = StampSeries::with('country')
            ->orderBy('name')
            ->get();

        // Haal de beschikbare postzegels op.
        $stamps = Stamp::orderBy('id')->get();

        return view('backend.collectibles.edit', compact(
            'collectible',
            'countries',
            'collectibleTypes',
            'series',
            'stamps'
        ));
    }

    /**
     * Update an existing collectible.
     */
    public function update(
        Request $request,
        Collectible $collectible
    ): RedirectResponse {
        $validated = $request->validate([
            'collectible_type_id' => [
                'required',
                'integer',
                Rule::exists('collectible_types', 'id')
                    ->where(function ($query) use ($collectible) {
                        $query->where('is_active', true)
                            ->orWhere(
                                'id',
                                $collectible->collectible_type_id
                            );
                    }),
            ],
            'country_id' => [
                'required',
                'integer',
                'exists:countries,id',
            ],
            'stamp_series_id' => [
                'nullable',
                'integer',
                Rule::exists('stamp_series', 'id')
                    ->where(
                        'country_id',
                        $request->input('country_id')
                    ),
            ],
            'name' => [
                'required',
                'string',
                'max:200',
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'issue_year' => [
                'required',
                'integer',
                'min:1840',
                'max:2200',
            ],
            'issue_date' => [
                'nullable',
                'date',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
            // Geselecteerde postzegels.
            'stamp_ids' => [
                'nullable',
                'array',
            ],

            'stamp_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('stamps', 'id')
                    ->where('country_id', $request->input('country_id')),
            ],
        ]);

        // Haal de geselecteerde postzegels uit de gevalideerde gegevens.
        $stampIds = $validated['stamp_ids'] ?? [];

        // Verwijder stamp_ids uit de gegevens voor de collectibles-tabel.
        unset($validated['stamp_ids']);

        // Werk de algemene gegevens van het verzamelobject bij.
        $collectible->update($validated);

        // Synchroniseer de gekoppelde postzegels.
        $collectible->stamps()->sync($stampIds);

        return redirect()
            ->route('backend.collectibles.index')
            ->with('success', __('Verzamelobject :name is bijgewerkt.', [
                'name' => $collectible->name,
            ]));
    }

    /**
     * Remove a collectible.
     */
    public function destroy(Collectible $collectible): RedirectResponse
    {
        if (
            $collectible->copies()->exists() ||
            $collectible->images()->exists() ||
            $collectible->catalogEntries()->exists()
        ) {
            return redirect()
                ->route('backend.collectibles.index')
                ->with('error', __('Verzamelobject :name kan niet worden verwijderd omdat er nog gekoppelde gegevens bestaan.', [
                    'name' => $collectible->name,
                ]));
        }

        $name = $collectible->name;

        $collectible->delete();

        return redirect()
            ->route('backend.collectibles.index')
            ->with('success', __('Verzamelobject :name is verwijderd.', [
                'name' => $name,
            ]));
    }
}
