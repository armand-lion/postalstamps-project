<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\CollectibleType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollectibleTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $collectibleTypes = CollectibleType::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return view('backend.collectible-types.index', compact(
            'collectibleTypes'
        ));
    }

    /**
     * Show the form for creating a new collectible type.
     */
    public function create(): View
    {
        return view('backend.collectible-types.create');
    }

    /**
     * Store a newly created collectible type.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('collectible_types', 'slug'),
            ],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'translations' => ['required', 'array'],
            'translations.nl' => [
                app()->getLocale() === 'nl' ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'translations.en' => [
                app()->getLocale() === 'en' ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'translations.de' => [
                app()->getLocale() === 'de' ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'translations.fr' => [
                app()->getLocale() === 'fr' ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'translations.es' => [
                app()->getLocale() === 'es' ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
        ]);

        DB::transaction(function () use ($validated): void {
            $collectibleType = CollectibleType::create([
                'slug' => trim($validated['slug']),
                'sort_order' => $validated['sort_order'],
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);

            foreach ($validated['translations'] as $locale => $name) {
                $name = trim($name ?? '');

                if ($name === '') {
                    continue;
                }

                $collectibleType->translations()->create([
                    'locale' => $locale,
                    'name' => $name,
                ]);
            }
        });

        return redirect()
            ->route('backend.collectible-types.index')
            ->with('success', __('Verzamelobjecttype succesvol toegevoegd.'));
    }

    /**
     * Update the specified collectible type.
     */
    public function update(Request $request, CollectibleType $collectibleType): RedirectResponse
    {
        $validated = $request->validate([
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('collectible_types', 'slug')->ignore($collectibleType->id),
            ],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'translations' => ['required', 'array'],
            'translations.nl' => [
                app()->getLocale() === 'nl' ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'translations.en' => [
                app()->getLocale() === 'en' ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'translations.de' => [
                app()->getLocale() === 'de' ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'translations.fr' => [
                app()->getLocale() === 'fr' ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'translations.es' => [
                app()->getLocale() === 'es' ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
        ]);

        DB::transaction(function () use ($collectibleType, $validated): void {
            $collectibleType->update([
                'slug' => trim($validated['slug']),
                'sort_order' => $validated['sort_order'],
                'is_active' => (bool) ($validated['is_active'] ?? false),
            ]);

            foreach ($validated['translations'] as $locale => $name) {
                $name = trim($name ?? '');

                if ($name === '') {
                    $collectibleType->translations()
                        ->where('locale', $locale)
                        ->delete();

                    continue;
                }

                $collectibleType->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['name' => $name]
                );
            }
        });

        return redirect()
            ->route('backend.collectible-types.index')
            ->with('success', __('Verzamelobjecttype succesvol bijgewerkt.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(CollectibleType $collectibleType): void
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CollectibleType $collectibleType): View
    {
        $collectibleType->load('translations');

        return view('backend.collectible-types.edit', [
            'collectibleType' => $collectibleType,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CollectibleType $collectibleType): RedirectResponse
    {
        if ($collectibleType->collectibles()->exists()) {
            return redirect()
                ->route('backend.collectible-types.index')
                ->with('error', __('Dit verzamelobjecttype kan niet worden verwijderd omdat er verzamelobjecten aan gekoppeld zijn.'));
        }

        DB::transaction(function () use ($collectibleType): void {
            $collectibleType->translations()->delete();

            $collectibleType->delete();
        });

        return redirect()
            ->route('backend.collectible-types.index')
            ->with('success', __('Verzamelobjecttype succesvol verwijderd.'));
    }
}
