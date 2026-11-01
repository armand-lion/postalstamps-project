<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Watermark;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class WatermarkController extends Controller
{
    public function index(Request $request): View
    {
        $query = Watermark::query()
            ->with([
                'country.translations',
                'translations',
                'images',
            ]);

        // Zoeken op slug of vertaalde naam.
        if ($request->filled('search')) {
            $search = trim($request->input('search'));

            $query->where(function ($query) use ($search) {
                $query->where('slug', 'like', "%{$search}%")
                    ->orWhereHas('translations', function ($translationQuery) use ($search) {
                        $translationQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filteren op land.
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->input('country_id'));
        }

        // Filteren op actieve status.
        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        // Sorteren en pagineren.
        $watermarks = $query
            ->orderBy('country_id')
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->paginate(15)
            ->withQueryString();

        return view('backend.watermarks.index', [
            'watermarks' => $watermarks,
            'countries' => Country::query()
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('backend.watermarks.create', [
            'countries' => Country::query()
                ->orderBy('name')
                ->get(),

            'locales' => ['nl', 'en', 'de', 'fr', 'es'],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $locale = app()->getLocale();
        $validated = $request->validate([
            'country_id' => [
                'required',
                'integer',
                'exists:countries,id',
            ],

            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                'unique:watermarks,slug,NULL,id,country_id,'.$request->input('country_id'),
            ],

            'sort_order' => [
                'required',
                'integer',
                'min:0',
                'max:65535',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'translations' => [
                'required',
                'array',
            ],

            "translations.{$locale}.name" => [
                'required',
                'string',
                'max:100',
            ],

            'translations.nl.name' => [
                $locale === 'nl' ? 'required' : 'nullable',
                'string',
                'max:100',
            ],

            'translations.en.name' => [
                $locale === 'en' ? 'required' : 'nullable',
                'string',
                'max:100',
            ],

            'translations.de.name' => [
                $locale === 'de' ? 'required' : 'nullable',
                'string',
                'max:100',
            ],

            'translations.fr.name' => [
                $locale === 'fr' ? 'required' : 'nullable',
                'string',
                'max:100',
            ],

            'translations.es.name' => [
                $locale === 'es' ? 'required' : 'nullable',
                'string',
                'max:100',
            ],
            'translations.*.description' => [
                'nullable',
                'string',
            ],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'image_alt_text' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $uploadedImagePath = null;

        DB::beginTransaction();

        try {

            $watermark = Watermark::create([
                'country_id' => $validated['country_id'],
                'slug' => $validated['slug'],
                'sort_order' => $validated['sort_order'],
                'is_active' => $request->boolean('is_active'),
            ]);

            foreach (['nl', 'en', 'de', 'fr', 'es'] as $translationLocale) {
                $translation = $validated['translations'][$translationLocale] ?? [];

                if (empty($translation['name'])) {
                    continue;
                }

                $watermark->translations()->create([
                    'locale' => $translationLocale,
                    'name' => $translation['name'],
                    'description' => $translation['description'] ?? null,
                ]);
            }

            if ($request->hasFile('image')) {
                $uploadedImagePath = $request->file('image')->store('watermarks/'.$watermark->country->slug, 'public');

                $watermark->images()->create([
                    'path' => $uploadedImagePath,
                    'alt_text' => $validated['image_alt_text'] ?? null,
                    'is_primary' => true,
                ]);
            }

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            if ($uploadedImagePath) {
                Storage::disk('public')
                    ->delete($uploadedImagePath);
            }

            throw $e;
        }

        return redirect()
            ->route('backend.watermarks.index')
            ->with('success', __('Watermerk succesvol toegevoegd.'));

    }

    /**
     * Display the specified resource.
     */
    public function show(Watermark $watermark): View
    {
        $watermark->load([
            'country.translations',
            'translations',
            'images',
        ]);

        return view('backend.watermarks.show', [
            'watermark' => $watermark,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Watermark $watermark): View
    {
        $watermark->load([
            'country.translations',
            'translations',
            'images',
        ]);

        return view('backend.watermarks.edit', [
            'watermark' => $watermark,
            'countries' => Country::query()->orderBy('name')->get(),
            'locales' => ['nl', 'en', 'de', 'fr', 'es'],
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Watermark $watermark): RedirectResponse
    {
        $locale = app()->getLocale();

        $validated = $request->validate([
            'country_id' => ['required', 'integer', 'exists:countries,id'],

            'slug' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('watermarks', 'slug')
                    ->where('country_id', $request->input('country_id'))
                    ->ignore($watermark->id),
            ],

            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['nullable', 'boolean'],

            'translations' => ['required', 'array'],

            'translations.nl.name' => [
                $locale === 'nl' ? 'required' : 'nullable',
                'string',
                'max:100',
            ],

            'translations.en.name' => [
                $locale === 'en' ? 'required' : 'nullable',
                'string',
                'max:100',
            ],

            'translations.de.name' => [
                $locale === 'de' ? 'required' : 'nullable',
                'string',
                'max:100',
            ],

            'translations.fr.name' => [
                $locale === 'fr' ? 'required' : 'nullable',
                'string',
                'max:100',
            ],

            'translations.es.name' => [
                $locale === 'es' ? 'required' : 'nullable',
                'string',
                'max:100',
            ],

            'translations.*.description' => ['nullable', 'string'],

            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'image_alt_text' => ['nullable', 'string', 'max:255'],
            'remove_existing_image' => ['nullable', 'boolean'],
        ]);

        // Voorkomen dat verwijderen en uploaden tegelijk plaatsvinden.
        if ($request->boolean('remove_existing_image') && $request->hasFile('image')) {
            throw ValidationException::withMessages([
                'image' => __('Je kunt niet tegelijk een bestaande afbeelding verwijderen en een nieuwe afbeelding uploaden.'),
            ]);
        }

        // Bestandspaden bewaren voor veilige verwerking.
        $newImagePath = null;
        $oldImagePath = null;

        // Database-transactie starten.
        DB::beginTransaction();

        try {

            // Basisgegevens van het watermerk bijwerken.
            $watermark->update([
                'country_id' => $validated['country_id'],
                'slug' => $validated['slug'],
                'sort_order' => $validated['sort_order'],
                'is_active' => $request->boolean('is_active'),
            ]);

            // Vertalingen bijwerken of toevoegen.
            foreach (['nl', 'en', 'de', 'fr', 'es'] as $translationLocale) {
                $translation = $validated['translations'][$translationLocale] ?? [];

                if (empty($translation['name'])) {
                    continue;
                }

                $watermark->translations()->updateOrCreate(
                    [
                        'locale' => $translationLocale,
                    ],
                    [
                        'name' => $translation['name'],
                        'description' => $translation['description'] ?? null,
                    ]
                );
            }

            // Alternatieve tekst van de bestaande afbeelding bijwerken.
            // Bestaande afbeelding verwijderen of alternatieve tekst bijwerken.
            if (! $request->hasFile('image')) {

                $primaryImage = $watermark->images()
                    ->where('is_primary', true)
                    ->first();

                if (! $primaryImage) {
                    $primaryImage = $watermark->images()->first();
                }

                if ($primaryImage) {

                    if ($request->boolean('remove_existing_image')) {

                        // Bestandspad bewaren voor verwijdering na de transactie.
                        $oldImagePath = $primaryImage->path;

                        // Afbeeldingsregistratie verwijderen.
                        $primaryImage->delete();

                    } else {

                        // Alleen de alternatieve tekst bijwerken.
                        $primaryImage->update([
                            'alt_text' => $validated['image_alt_text'] ?? null,
                        ]);
                    }
                }
            }

            // Nieuwe watermerkafbeelding verwerken.
            if ($request->hasFile('image')) {
                $country = Country::query()
                    ->whereKey($validated['country_id'])
                    ->firstOrFail();

                // Nieuwe afbeelding opslaan onder de slug van het land.
                $newImagePath = $request->file('image')->store(
                    'watermarks/'.$country->slug,
                    'public'
                );

                if ($newImagePath === false) {
                    throw new \RuntimeException(
                        'Het opslaan van de watermerkafbeelding is mislukt.'
                    );
                }

                // Bestaande primaire afbeelding ophalen.
                $primaryImage = $watermark->images()
                    ->where('is_primary', true)
                    ->first();

                if (! $primaryImage) {
                    $primaryImage = $watermark->images()->first();
                }

                if ($primaryImage) {
                    // Oude afbeelding bewaren voor verwijdering na de transactie.
                    $oldImagePath = $primaryImage->path;
                    // Bestaande afbeeldingsregistratie bijwerken.
                    $primaryImage->update([
                        'path' => $newImagePath,
                        'alt_text' => $validated['image_alt_text'] ?? null,
                        'is_primary' => true,
                    ]);
                } else {
                    // Eerste afbeelding voor dit watermerk toevoegen.
                    $watermark->images()->create([
                        'path' => $newImagePath,
                        'alt_text' => $validated['image_alt_text'] ?? null,
                        'is_primary' => true,
                    ]);
                }
            }

            // Databasewijzigingen bevestigen.
            DB::commit();

        } catch (Throwable $e) {
            // Databasewijzigingen terugdraaien.
            DB::rollBack();

            // Nieuw geüploade afbeelding opruimen bij een fout.
            if ($newImagePath) {
                Storage::disk('public')
                    ->delete($newImagePath);
            }

            throw $e;
        }

        // Oude afbeelding pas na succesvolle opslag verwijderen.
        if ($oldImagePath && $oldImagePath !== $newImagePath) {
            Storage::disk('public')
                ->delete($oldImagePath);
        }

        // Terugkeren naar het overzicht met een succesmelding.
        return redirect()
            ->route('backend.watermarks.index')
            ->with('success', __('Watermerk succesvol bijgewerkt.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Watermark $watermark): RedirectResponse
    {
        // Afbeeldingspaden bewaren voordat het watermerk wordt verwijderd.
        $imagePaths = $watermark->images()
            ->pluck('path')
            ->all();

        // Watermerk en gekoppelde databasegegevens verwijderen.
        DB::transaction(function () use ($watermark) {
            $watermark->delete();
        });

        // Afbeeldingsbestanden pas na succesvolle databaseverwijdering opruimen.
        foreach ($imagePaths as $imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        // Terugkeren naar het overzicht.
        return redirect()
            ->route('backend.watermarks.index')
            ->with('success', __('Watermerk succesvol verwijderd.'));
    }
}
