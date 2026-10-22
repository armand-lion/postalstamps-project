<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Stamp;
use App\Models\StampSeries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StampController extends Controller
{
    private const LOCALES = [
        'nl',
        'en',
        'de',
        'fr',
        'es',
    ];

    /**
     * Validation rules for stamp translations.
     *
     * @return array<string, array<int, string>>
     */
    private function translationValidationRules(): array
    {
        $rules = [
            'translations' => ['required', 'array'],
        ];

        foreach (self::LOCALES as $locale) {
            $rules["translations.{$locale}"] = [
                'sometimes',
                'array',
            ];

            $rules["translations.{$locale}.name"] = [
                $locale === app()->getLocale() ? 'required' : 'nullable',
                'string',
                'max:255',
            ];

            $rules["translations.{$locale}.description"] = [
                'nullable',
                'string',
            ];
        }

        return $rules;
    }

    /**
     * Display a listing of the stamps.
     */
    public function index(Request $request): View
    {
        $query = Stamp::with(['country', 'series']);

        if ($request->filled('country_id')) {
            $query->where('country_id', $request->integer('country_id'));
        }

        if ($request->filled('issue_year')) {
            $query->where('issue_year', $request->integer('issue_year'));
        }

        $stamps = $query
            ->orderByDesc('issue_year')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $countries = Country::orderBy('name')->get();

        return view('backend.stamps.index', compact(
            'stamps',
            'countries'
        ));
    }

    /**
     * Show the form for creating a stamp.
     */
    public function create(): View
    {
        $countries = Country::orderBy('name')->get();

        $series = StampSeries::with('country')
            ->orderBy('name')
            ->get();

        return view('backend.stamps.create', [
            'countries' => $countries,
            'series' => $series,
            'locales' => self::LOCALES,
        ]);
    }

    /**
     * Store a newly created stamp.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(array_merge([
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
            'face_value' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
            ],
            'designer' => [
                'nullable',
                'string',
                'max:255',
            ],
            'printing_method' => [
                'nullable',
                'string',
                'max:255',
            ],
            'catalog_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ], $this->translationValidationRules()));

        // Haal de vertalingen uit de gevalideerde gegevens.
        $translations = $validated['translations'];

        unset($validated['translations']);

        // Gebruik de actieve applicatietaal voor de algemene gegevens.
        $locale = app()->getLocale();

        $validated['name'] = trim(
            $translations[$locale]['name']
        );

        $validated['description'] = $translations[$locale]['description'] ?? null;

        $stamp = DB::transaction(function () use ($validated, $translations) {

            // Sla de algemene postzegelgegevens op.
            $stamp = Stamp::create($validated);

            // Sla de vertalingen op.
            foreach (self::LOCALES as $locale) {

                $translation = $translations[$locale] ?? [];

                $name = trim((string) ($translation['name'] ?? ''));

                // Maak geen vertaling aan zonder naam.
                if ($name === '') {
                    continue;
                }

                $stamp->translations()->create([
                    'locale' => $locale,
                    'name' => $name,
                    'description' => $translation['description'] ?? null,
                ]);
            }

            return $stamp;
        });

        return redirect()
            ->route('backend.stamps.index')
            ->with(
                'success',
                "Postzegel '{$stamp->name}' is toegevoegd."
            );
    }

    /**
     * Display a stamp.
     */
    public function show(Stamp $stamp): View
    {
        $stamp->load([
            'country',
            'series',
            'themes',
            'copies.condition.translation',
            'images',
        ]);

        return view('backend.stamps.show', compact('stamp'));
    }

    /**
     * Show the form for editing a stamp.
     */
    public function edit(Stamp $stamp): View
    {
        // Laad de bestaande vertalingen.
        $stamp->load('translations');

        $countries = Country::orderBy('name')->get();

        $series = StampSeries::with('country')
            ->orderBy('name')
            ->get();

        return view('backend.stamps.edit', [
            'stamp' => $stamp,
            'countries' => $countries,
            'series' => $series,
            'locales' => self::LOCALES,
        ]);
    }

    /**
     * Update a stamp.
     */
    public function update(
        Request $request,
        Stamp $stamp
    ): RedirectResponse {
        $validated = $request->validate(array_merge([
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
            'face_value' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
            ],
            'designer' => [
                'nullable',
                'string',
                'max:255',
            ],
            'printing_method' => [
                'nullable',
                'string',
                'max:255',
            ],
            'catalog_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ], $this->translationValidationRules()));

        // Haal de vertalingen uit de gevalideerde gegevens.
        $translations = $validated['translations'];

        unset($validated['translations']);

        // Gebruik de actieve applicatietaal voor de algemene gegevens.
        $locale = app()->getLocale();

        $validated['name'] = trim(
            $translations[$locale]['name']
        );

        $validated['description'] = $translations[$locale]['description'] ?? null;

        DB::transaction(function () use ($stamp, $validated, $translations) {

            // Werk de algemene postzegelgegevens bij.
            $stamp->update($validated);

            // Werk bestaande vertalingen bij of voeg nieuwe toe.
            foreach (self::LOCALES as $locale) {

                $translation = $translations[$locale] ?? [];

                $name = trim((string) ($translation['name'] ?? ''));

                // Behoud bestaande vertalingen als de naam leeg is.
                if ($name === '') {
                    continue;
                }

                $stamp->translations()->updateOrCreate(
                    ['locale' => $locale],
                    [
                        'name' => $name,
                        'description' => $translation['description'] ?? null,
                    ]
                );
            }
        });

        return redirect()
            ->route('backend.stamps.index')
            ->with(
                'success',
                "Postzegel '{$stamp->name}' is bijgewerkt."
            );
    }

    /**
     * Delete a stamp.
     */
    public function destroy(Stamp $stamp): RedirectResponse
    {
        $name = $stamp->name;

        // Controleer of er gekoppelde gegevens bestaan.
        $hasRelatedData =
            $stamp->copies()->exists()
            || $stamp->images()->exists()
            || $stamp->catalogEntries()->exists()
            || $stamp->catalogValues()->exists()
            || $stamp->valuations()->exists()
            || $stamp->variants()->exists();

        // Verwijder de postzegel niet als er gegevens gekoppeld zijn.
        if ($hasRelatedData) {
            return redirect()
                ->route('backend.stamps.index')
                ->with(
                    'error',
                    "Postzegel '{$name}' kan niet worden verwijderd, omdat er nog gekoppelde gegevens bestaan."
                );
        }

        // Verwijder de koppelingen met thema's en daarna de postzegel.
        DB::transaction(function () use ($stamp) {
            $stamp->themes()->detach();
            $stamp->delete();
        });

        return redirect()
            ->route('backend.stamps.index')
            ->with(
                'success',
                "Postzegel '{$name}' is verwijderd."
            );
    }
}
