<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\AcquisitionMethod;
use App\Models\Collectible;
use App\Models\CollectibleCopy;
use App\Models\CollectibleType;
use App\Models\Condition;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollectibleCopyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $collectibleCopies = auth()->user()
            ->collectibleCopies()
            ->with([
                'collectible.collectibleType.translations',
                'collectible.country.translations',
                'condition.translations',
            ])
            ->latest()
            ->paginate(20);

        return view('backend.collectible-copies.index', [
            'collectibleCopies' => $collectibleCopies,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        $collectibles = Collectible::query()
            ->with([
                'collectibleType.translations',
                'country.translations',
            ])
            ->orderBy('name')
            ->get();

        $collectibleTypes = CollectibleType::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        $countries = Country::query()
            ->with('translations')
            ->get();

        $conditions = Condition::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        $acquisitionMethods = AcquisitionMethod::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        $issueYears = Collectible::query()
            ->whereNotNull('issue_year')
            ->distinct()
            ->orderByDesc('issue_year')
            ->pluck('issue_year');

        return view('backend.collectible-copies.create', [
            'collectibles' => $collectibles,
            'collectibleTypes' => $collectibleTypes,
            'countries' => $countries,
            'conditions' => $conditions,
            'acquisitionMethods' => $acquisitionMethods,
            'issueYears' => $issueYears,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'collectible_id' => [
                'required',
                'integer',
                'exists:collectibles,id',
            ],

            'condition_id' => [
                'required',
                'integer',
                'exists:conditions,id',
            ],

            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'acquisition_date' => [
                'nullable',
                'date',
            ],

            'purchase_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'purchase_currency' => [
                'nullable',
                'string',
                Rule::in([
                    'EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD', 'JPY',
                    'NLG', 'DEM', 'BEF', 'FRF', 'ITL', 'ESP', 'ATS',
                ]),
            ],

            'acquisition_method' => [
                'nullable',
                'string',
                'exists:acquisition_methods,slug',
            ],

            'is_variant' => [
                'nullable',
                'boolean',
            ],

            'translations' => [
                'nullable',
                'array',
            ],

            'translations.*.variant_description' => [
                'nullable',
                'string',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $validated['is_variant'] = $request->boolean('is_variant');

        $translations = $validated['translations'] ?? [];

        unset($validated['translations']);

        DB::transaction(function () use ($request, $validated, $translations) {

            $collectibleCopy = $request->user()
                ->collectibleCopies()
                ->create($validated);

            if ($collectibleCopy->is_variant) {

                foreach (['nl', 'en', 'de', 'fr', 'es'] as $locale) {

                    $description = trim(
                        $translations[$locale]['variant_description'] ?? ''
                    );

                    if ($description === '') {
                        continue;
                    }

                    $collectibleCopy->translations()->create([
                        'locale' => $locale,
                        'variant_description' => $description,
                    ]);
                }
            }
        });

        return redirect()
            ->route('backend.collectible-copies.index')
            ->with(
                'success',
                __('Verzamelobject succesvol toegevoegd aan je verzameling.')
            );
    }

    /**
     * Display the specified resource.
     */
    public function show(CollectibleCopy $collectibleCopy): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CollectibleCopy $collectibleCopy): View
    {
        abort_unless(
            $collectibleCopy->user_id === auth()->id(),
            403
        );

        $collectibleCopy->load([
            'collectible',
            'condition',
            'translations',
        ]);

        $collectibles = Collectible::query()
            ->with([
                'collectibleType.translations',
                'country.translations',
            ])
            ->orderBy('name')
            ->get();

        $collectibleTypes = CollectibleType::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        $countries = Country::query()
            ->with('translations')
            ->get();

        $conditions = Condition::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        $acquisitionMethods = AcquisitionMethod::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->get();

        $issueYears = Collectible::query()
            ->whereNotNull('issue_year')
            ->distinct()
            ->orderByDesc('issue_year')
            ->pluck('issue_year');

        return view('backend.collectible-copies.edit', [
            'collectibleCopy' => $collectibleCopy,
            'collectibles' => $collectibles,
            'collectibleTypes' => $collectibleTypes,
            'countries' => $countries,
            'conditions' => $conditions,
            'acquisitionMethods' => $acquisitionMethods,
            'issueYears' => $issueYears,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CollectibleCopy $collectibleCopy): RedirectResponse
    {
        abort_unless(
            $collectibleCopy->user_id === $request->user()->id,
            403
        );

        $validated = $request->validate([
            'collectible_id' => [
                'required',
                'integer',
                'exists:collectibles,id',
            ],

            'condition_id' => [
                'required',
                'integer',
                'exists:conditions,id',
            ],

            'quantity' => [
                'required',
                'integer',
                'min:1',
            ],

            'acquisition_date' => [
                'nullable',
                'date',
            ],

            'purchase_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'purchase_currency' => [
                'nullable',
                'string',
                Rule::in([
                    'EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD', 'JPY',
                    'NLG', 'DEM', 'BEF', 'FRF', 'ITL', 'ESP', 'ATS',
                ]),
            ],

            'acquisition_method' => [
                'nullable',
                'string',
                'exists:acquisition_methods,slug',
            ],

            'is_variant' => [
                'nullable',
                'boolean',
            ],

            'translations' => [
                'nullable',
                'array',
            ],

            'translations.*.variant_description' => [
                'nullable',
                'string',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        $validated['is_variant'] = $request->boolean('is_variant');

        $translations = $validated['translations'] ?? [];

        unset($validated['translations']);

        DB::transaction(function () use ($collectibleCopy, $validated, $translations) {

            $collectibleCopy->update($validated);

            if (! $collectibleCopy->is_variant) {
                $collectibleCopy->translations()->delete();

                return;
            }

            foreach (['nl', 'en', 'de', 'fr', 'es'] as $locale) {

                $description = trim(
                    $translations[$locale]['variant_description'] ?? ''
                );

                if ($description === '') {
                    $collectibleCopy->translations()
                        ->where('locale', $locale)
                        ->delete();

                    continue;
                }

                $collectibleCopy->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['variant_description' => $description]
                );
            }

        });

        return redirect()
            ->route('backend.collectible-copies.index')
            ->with(
                'success',
                __('Verzamelobject succesvol bijgewerkt in je verzameling.')
            );
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CollectibleCopy $collectibleCopy): RedirectResponse
    {
        abort_unless(
            $collectibleCopy->user_id === auth()->id(),
            404
        );

        $collectibleCopy->delete();

        return redirect()
            ->route('backend.collectible-copies.index')
            ->with('success', __('Verzamelexemplaar succesvol uit je verzameling verwijderd.'));
    }
}
