<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Theme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ThemeController extends Controller
{
    /**
     * Validation rules for creating and updating themes.
     *
     * @return array<string, mixed>
     */
    private function validationRules(?Theme $theme = null): array
    {
        $slugRule = Rule::unique('themes', 'slug');

        if ($theme !== null) {
            $slugRule->ignore($theme->id);
        }

        $rules = [
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash:ascii',
                $slugRule,
            ],
            'translations' => [
                'required',
                'array',
            ],
        ];

        foreach (['nl', 'en', 'de', 'fr', 'es'] as $locale) {
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
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $themes = Theme::query()
            ->with('translations')
            ->orderBy('name')
            ->paginate(15);

        return view('backend.themes.index', compact('themes'));
    }

    public function create(): View
    {
        $locales = ['nl', 'en', 'de', 'fr', 'es'];

        return view('backend.themes.create', compact('locales'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            $this->validationRules(),
            [],
            [
                'slug' => __('Slug'),
                'translations.nl.name' => __('Nederlandse naam'),
                'translations.en.name' => __('Engelse naam'),
                'translations.de.name' => __('Duitse naam'),
                'translations.fr.name' => __('Franse naam'),
                'translations.es.name' => __('Spaanse naam'),
            ]
        );

        $translations = $validated['translations'];

        $locale = app()->getLocale();

        $name = trim($translations[$locale]['name']);

        // Controleer of de algemene naam al bestaat.
        if (Theme::where('name', $name)->exists()) {
            return back()
                ->withErrors([
                    "translations.{$locale}.name" => __('Deze naam bestaat al.'),
                ])
                ->withInput();
        }

        $theme = DB::transaction(function () use ($validated, $translations, $locale, $name) {
            $theme = Theme::create([
                'name' => $name,
                'slug' => $validated['slug'],
                'description' => trim($translations[$locale]['description'] ?? ''),
            ]);

            foreach (['nl', 'en', 'de', 'fr', 'es'] as $translationLocale) {
                $translation = $translations[$translationLocale] ?? [];

                $translatedName = trim($translation['name'] ?? '');

                if ($translatedName === '') {
                    continue;
                }

                $theme->translations()->create([
                    'locale' => $translationLocale,
                    'name' => $translatedName,
                    'description' => trim($translation['description'] ?? ''),
                ]);
            }

            return $theme;
        });

        return redirect()
            ->route('backend.themes.index')
            ->with('success', __('Het thema is succesvol toegevoegd.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Theme $theme): View
    {
        $theme->load('translations');

        return view('backend.themes.show', compact('theme'));
    }

    /**
     * Show the form for editing the specified theme.
     */
    public function edit(Theme $theme): View
    {
        $theme->load('translations');

        $locales = ['nl', 'en', 'de', 'fr', 'es'];

        return view('backend.themes.edit', compact('theme', 'locales'));
    }

    /**
     * Update the specified theme.
     */
    public function update(Request $request, Theme $theme): RedirectResponse
    {
        $validated = $request->validate($this->validationRules($theme));

        $translations = $validated['translations'];
        $locale = app()->getLocale();

        $name = trim($translations[$locale]['name']);

        // Controleer of de naam al bij een ander thema bestaat.
        if (
            Theme::where('name', $name)
                ->where('id', '!=', $theme->id)
                ->exists()
        ) {
            return back()
                ->withErrors([
                    "translations.{$locale}.name" => __('Deze naam bestaat al.'),
                ])
                ->withInput();
        }

        DB::transaction(function () use ($theme, $validated, $translations, $locale, $name) {
            $theme->update([
                'name' => $name,
                'slug' => $validated['slug'],
                'description' => trim($translations[$locale]['description'] ?? ''),
            ]);

            foreach (['nl', 'en', 'de', 'fr', 'es'] as $translationLocale) {
                $translation = $translations[$translationLocale] ?? [];

                $translatedName = trim($translation['name'] ?? '');

                if ($translatedName === '') {
                    continue;
                }

                $theme->translations()->updateOrCreate(
                    ['locale' => $translationLocale],
                    [
                        'name' => $translatedName,
                        'description' => trim($translation['description'] ?? ''),
                    ]
                );
            }
        });

        return redirect()
            ->route('backend.themes.show', $theme)
            ->with('success', __('Het thema is succesvol bijgewerkt.'));
    }

    /**
     * Remove the specified theme.
     */
    public function destroy(Theme $theme): RedirectResponse
    {
        // Voorkom verwijderen als er postzegels gekoppeld zijn.
        if ($theme->stamps()->exists()) {
            return redirect()
                ->route('backend.themes.show', $theme)
                ->with('error', __('Dit thema kan niet worden verwijderd omdat er postzegels aan gekoppeld zijn.'));
        }

        $theme->delete();

        return redirect()
            ->route('backend.themes.index')
            ->with('success', __('Het thema is succesvol verwijderd.'));
    }
}
