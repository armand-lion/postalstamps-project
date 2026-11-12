<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Catalogusvoorkeuren')] class extends Component {
    public string $catalogId = '';

    public string $catalogEditionId = '';

    public function mount(): void
    {
        $defaultEdition = Auth::user()->defaultCatalogEdition()->first();

        if ($defaultEdition) {
            $this->catalogId = (string) $defaultEdition->catalog_id;
            $this->catalogEditionId = (string) $defaultEdition->id;
        }
    }

    public function updatedCatalogId(): void
    {
        $this->catalogEditionId = '';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'catalogId' => ['required', 'integer', 'exists:catalogs,id'],

            'catalogEditionId' => ['required', 'integer', Rule::exists('catalog_editions', 'id')->where('catalog_id', $this->catalogId)],
        ]);

        $user = Auth::user();

        $user->default_catalog_edition_id = $validated['catalogEditionId'];

        $user->save();

        session()->flash('catalog_preferences_success', __('Catalogusvoorkeuren opgeslagen.'));
    }

    public function with(): array
    {
        return [
            'catalogs' => Catalog::query()->orderBy('name')->get(),

            'catalogEditions' => CatalogEdition::query()
                ->when($this->catalogId !== '', fn($query) => $query->where('catalog_id', $this->catalogId))
                ->when($this->catalogId === '', fn($query) => $query->whereRaw('1 = 0'))
                ->orderByDesc('edition_year')
                ->get(),
        ];
    }
}; ?>

<section class="w-full min-h-full rounded-xl border border-theme-accent-3 bg-brown-6 p-6 text-zinc-900">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">
        {{ __('Catalogusvoorkeuren') }}
    </flux:heading>

    <x-pages::settings.layout :heading="__('Catalogusvoorkeuren')" :subheading="__('Stel je standaardcatalogus en cataloguseditie in')">

        <div class="my-6 w-full space-y-6 rounded-lg border border-theme-accent-3 bg-brown-6 p-6">

            <flux:text class="text-zinc-900!">
                {{ __('Selecteer de catalogus en cataloguseditie die standaard gebruikt moeten worden voor de waardering van je persoonlijke postzegelverzameling.') }}
            </flux:text>

            {{-- Standaardcatalogus --}}
            <div class="w-full">

                <flux:field>
                    <flux:label>{{ __('Standaardcatalogus') }}</flux:label>

                    <div x-data="{
                        selectedName: @js($catalogs->firstWhere('id', $catalogId)?->name ?? __('Selecteer een catalogus'))
                    }">

                        <flux:dropdown position="bottom" align="start" class="block w-full">

                            <flux:button type="button" icon-trailing="chevron-down"
                                class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                <span x-text="selectedName" class="min-w-0 truncate text-left"></span>
                            </flux:button>

                            <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                <flux:menu.item as="button" type="button"
                                    x-on:click="selectedName = @js(__('Selecteer een catalogus')); $wire.set('catalogId', '')"
                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                    {{ __('Selecteer een catalogus') }}
                                </flux:menu.item>

                                @foreach ($catalogs as $catalog)
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="selectedName = @js($catalog->name); $wire.set('catalogId', @js((string) $catalog->id))"
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                        {{ $catalog->name }}
                                    </flux:menu.item>
                                @endforeach

                            </flux:menu>

                        </flux:dropdown>

                    </div>
                </flux:field>

                {{-- Standaardcataloguseditie --}}
                <div class="mt-6 w-full">

                    <flux:field>
                        <flux:label>{{ __('Standaardcataloguseditie') }}</flux:label>

                        <div wire:key="catalog-edition-{{ $catalogId ?: 'none' }}" x-data="{
                            selectedName: @js($catalogEditions->firstWhere('id', $catalogEditionId)?->edition_name ?? __('Selecteer een cataloguseditie'))
                        }">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    :disabled="$catalogId === ''"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                    <span x-text="selectedName" class="min-w-0 truncate text-left"></span>
                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    <flux:menu.item as="button" type="button"
                                        x-on:click="selectedName = @js(__('Selecteer een cataloguseditie')); $wire.set('catalogEditionId', '')"
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                        {{ __('Selecteer een cataloguseditie') }}
                                    </flux:menu.item>

                                    @foreach ($catalogEditions as $edition)
                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedName = @js($edition->edition_name); $wire.set('catalogEditionId', @js((string) $edition->id))"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                            {{ $edition->edition_name }}
                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>

                            </flux:dropdown>

                        </div>
                    </flux:field>

                </div>

                {{-- Voorkeuren opslaan --}}
                <div class="mt-6 flex justify-end">
                    <flux:button type="button" wire:click="save" variant="primary" wire:loading.attr="disabled"
                        wire:target="save" class="border-theme-accent-3! bg-brown-1! text-white hover:bg-brown-2!">
                        {{ __('Voorkeuren opslaan') }}
                    </flux:button>

                </div>

                {{-- Succesmelding catalogusvoorkeuren --}}
                @if (session()->has('catalog_preferences_success'))
                    <div role="status"
                        class="mt-6 rounded-lg border border-theme-accent-3 bg-brown-5 p-4 text-zinc-900">
                        {{ session('catalog_preferences_success') }}
                    </div>
                @endif

            </div>

        </div>

    </x-pages::settings.layout>
</section>
