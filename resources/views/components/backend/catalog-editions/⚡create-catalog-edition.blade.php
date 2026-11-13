<?php

use App\Models\Catalog;
use App\Services\CatalogEditionDuplicationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public Catalog $catalog;

    public string $edition_name = '';

    public ?int $edition_year = null;

    public ?string $publication_date = null;

    public ?string $currency = null;

    public string $notes = '';

    public ?int $source_edition_id = null;

    public bool $copy_catalog_values = false;

    public function mount(Catalog $catalog): void
    {
        $this->catalog = $catalog;
    }

    public function getSourceEditionsProperty()
    {
        return $this->catalog->editions()->orderByDesc('edition_year')->orderBy('edition_name')->get();
    }

    public function save()
    {
        // Verwijder ongewenste witruimte vóór de validatie.
        $this->edition_name = trim($this->edition_name);
        $this->notes = trim($this->notes);

        $validated = $this->validate([
            'edition_name' => ['required', 'string', 'max:150', Rule::unique('catalog_editions', 'edition_name')->where('catalog_id', $this->catalog->id)->where('edition_year', $this->edition_year)],
            'edition_year' => ['required', 'integer', 'between:1,65535'],
            'publication_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'source_edition_id' => ['nullable', 'integer', Rule::exists('catalog_editions', 'id')->where('catalog_id', $this->catalog->id)],
            'copy_catalog_values' => ['boolean'],
        ]);

        $validated['edition_name'] = trim($validated['edition_name']);
        $validated['notes'] = trim($validated['notes'] ?? '');

        unset($validated['source_edition_id']);
        unset($validated['copy_catalog_values']);

        DB::transaction(function () use ($validated) {
            $targetEdition = $this->catalog->editions()->create($validated);

            if ($this->source_edition_id !== null) {
                $sourceEdition = $this->catalog->editions()->findOrFail($this->source_edition_id);

                app(CatalogEditionDuplicationService::class)->copyStampEntries($sourceEdition, $targetEdition, $this->copy_catalog_values);
            }
        });

        session()->flash('success', __('De cataloguseditie is succesvol toegevoegd.'));

        return $this->redirect(route('backend.catalogs.editions.index', $this->catalog), navigate: true);
    }
};
?>

<div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">
    <form wire:submit="save" class="space-y-6">

        {{-- Editienaam --}}
        <flux:field>
            <flux:label>
                <span class="flex items-center gap-1">
                    {{ __('Editienaam') }}
                    <span class="text-red-600">*</span>
                </span>
            </flux:label>

            <input type="text" wire:model="edition_name" maxlength="150"
                placeholder="{{ __('Bijvoorbeeld: NVPH 2026') }}"
                class="w-[320px]! max-w-full block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5">
            <flux:error name="edition_name" />
        </flux:field>

        {{-- Editiejaar --}}
        <flux:field>
            <flux:label>
                <span class="flex items-center gap-1">
                    {{ __('Editiejaar') }}
                    <span class="text-red-600">*</span>
                </span>
            </flux:label>

            <div x-data="{
                year: $wire.entangle('edition_year'),
                minYear: 1,
                maxYear: 65535,
            
                increase() {
                    this.year = Math.min(
                        this.maxYear,
                        Number(this.year || 0) + 1
                    );
                },
            
                decrease() {
                    this.year = Math.max(
                        this.minYear,
                        Number(this.year || 1) - 1
                    );
                }
            }" class="relative w-full max-w-[320px]">
                <input type="number" min="1" max="65535" x-model.number="year"
                    placeholder="{{ __('Bijvoorbeeld: 2026') }}"
                    class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]">

                <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                    <button type="button" x-on:click="increase()" aria-label="{{ __('Jaar verhogen') }}"
                        class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                        <flux:icon.chevron-up class="size-3" />
                    </button>

                    <button type="button" x-on:click="decrease()" aria-label="{{ __('Jaar verlagen') }}"
                        class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                        <flux:icon.chevron-down class="size-3" />
                    </button>

                </div>
            </div>
            <flux:error name="edition_year" />
        </flux:field>

        {{-- Publicatiedatum --}}
        <flux:field>
            <flux:label>
                <span class="flex items-center gap-1">
                    {{ __('Publicatiedatum') }}
                </span>
            </flux:label>
            <div class="w-full max-w-[320px]">
                <x-forms.date-picker name="publication_date" :value="$publication_date" livewire-model="publication_date" />
            </div>
            <flux:error name="publication_date" />
        </flux:field>

        {{-- Valuta --}}
        <flux:field>
            <flux:label>
                {{ __('Valuta') }}
            </flux:label>

            @php
                $currencies = [
                    'EUR' => __('Euro (€)'),
                    'USD' => __('Amerikaanse dollar ($)'),
                    'GBP' => __('Britse pond (£)'),
                    'CHF' => __('Zwitserse frank (CHF)'),
                    'CAD' => __('Canadese dollar (CAD)'),
                    'AUD' => __('Australische dollar (AUD)'),
                    'JPY' => __('Japanse yen (¥)'),
                    'NLG' => __('Nederlandse gulden (ƒ)'),
                    'DEM' => __('Duitse mark (DM)'),
                    'BEF' => __('Belgische frank (BEF)'),
                    'FRF' => __('Franse frank (FRF)'),
                    'ITL' => __('Italiaanse lire (ITL)'),
                    'ESP' => __('Spaanse peseta (ESP)'),
                    'ATS' => __('Oostenrijkse schilling (ATS)'),
                ];
            @endphp

            <div x-data="{
                selectedId: $wire.entangle('currency'),
                currencies: @js($currencies),
                placeholder: @js(__('Selecteer een valuta')),
            
                get selectedName() {
                    return this.currencies[this.selectedId] ?? this.placeholder;
                }
            }">

                <flux:dropdown position="bottom" align="start">

                    <flux:button type="button" icon-trailing="chevron-down"
                        class="w-[320px]! max-w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                        <span x-text="selectedName" class="truncate"></span>
                    </flux:button>

                    <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                        {{-- Huidige valuta --}}
                        <flux:menu.item disabled>
                            {{ __('Huidige valuta') }}
                        </flux:menu.item>

                        @foreach ([
        'EUR' => __('Euro (€)'),
        'USD' => __('Amerikaanse dollar ($)'),
        'GBP' => __('Britse pond (£)'),
        'CHF' => __('Zwitserse frank (CHF)'),
        'CAD' => __('Canadese dollar (CAD)'),
        'AUD' => __('Australische dollar (AUD)'),
        'JPY' => __('Japanse yen (¥)'),
    ] as $code => $name)
                            <flux:menu.item as="button" type="button"
                                x-on:click="
                                        selectedId = @js($code);
                                        selectedName=@js($name);
                                        "
                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                {{ $name }}
                            </flux:menu.item>
                        @endforeach

                        <flux:menu.separator />

                        {{-- Historische valuta --}}
                        <flux:menu.item disabled>
                            {{ __('Historische valuta') }}
                        </flux:menu.item>

                        @foreach ([
        'NLG' => __('Nederlandse gulden (ƒ)'),
        'DEM' => __('Duitse mark (DM)'),
        'BEF' => __('Belgische frank (BEF)'),
        'FRF' => __('Franse frank (FRF)'),
        'ITL' => __('Italiaanse lire (ITL)'),
        'ESP' => __('Spaanse peseta (ESP)'),
        'ATS' => __('Oostenrijkse schilling (ATS)'),
    ] as $code => $name)
                            <flux:menu.item as="button" type="button"
                                x-on:click="
                                            selectedId = @js($code);
                                            selectedName = @js($name);
                                        "
                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                {{ $name }}
                            </flux:menu.item>
                        @endforeach

                    </flux:menu>

                </flux:dropdown>

            </div>

            <div class="min-h-5">
                <flux:error name="currency" />
            </div>
        </flux:field>

        {{-- Opmerkingen --}}
        <flux:field>
            <flux:label>
                {{ __('Opmerkingen') }}
            </flux:label>

            <textarea wire:model="notes" rows="4" placeholder="{{ __('Eventuele opmerkingen over deze cataloguseditie') }}"
                class="block w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5"></textarea>

            <flux:error name="notes" />
        </flux:field>

        {{-- Gegevens overnemen uit bestaande cataloguseditie --}}
        <div class="space-y-4">

            <flux:heading size="lg">
                {{ __('Gegevens overnemen uit bestaande cataloguseditie') }}
            </flux:heading>

            <flux:text>
                {{ __('Selecteer eventueel een bestaande cataloguseditie waarvan je de postzegelvermeldingen wilt overnemen.') }}
            </flux:text>

            <flux:field>
                <flux:label>
                    {{ __('Broneditie') }}
                </flux:label>

                <div x-data="{
                    selectedId: $wire.entangle('source_edition_id'),
                    editions: @js(
    $this->sourceEditions
        ->map(
            fn($edition) => [
                'id' => $edition->id,
                'name' => $edition->edition_name . ' (' . $edition->edition_year . ')',
            ],
        )
        ->values(),
),
                    placeholder: @js(__('Geen broneditie geselecteerd')),
                
                    get selectedName() {
                        return this.editions.find(
                            edition => edition.id == this.selectedId
                        )?.name ?? this.placeholder;
                    }
                }">

                    <flux:dropdown position="bottom" align="start">

                        <flux:button type="button" icon-trailing="chevron-down"
                            class="w-[320px]! max-w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                            <span x-text="selectedName" class="truncate"></span>
                        </flux:button>

                        <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                            <flux:menu.item as="button" type="button" x-on:click="selectedId = null"
                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                {{ __('Geen broneditie geselecteerd') }}
                            </flux:menu.item>

                            <flux:menu.separator />

                            @foreach ($this->sourceEditions as $edition)
                                <flux:menu.item as="button" type="button"
                                    x-on:click="selectedId = {{ $edition->id }}"
                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                    {{ $edition->edition_name }} ({{ $edition->edition_year }})
                                </flux:menu.item>
                            @endforeach

                        </flux:menu>

                    </flux:dropdown>

                </div>

                <flux:error name="source_edition_id" />
            </flux:field>

            {{-- Cataloguswaarden optioneel overnemen --}}
            <flux:field>
                <flux:checkbox wire:model="copy_catalog_values" :label="__('Cataloguswaarden ook overnemen')"
                    class="[&_[data-flux-checkbox-indicator]]:bg-brown-5! [&_[data-flux-checkbox-indicator]]:border-theme-accent-3!" />

                <flux:description>
                    {{ __('Wanneer deze optie is geselecteerd, worden ook de toegestane cataloguswaarden uit de broneditie overgenomen.') }}
                </flux:description>

                <flux:error name="copy_catalog_values" />
            </flux:field>

        </div>

        {{-- Actieknoppen --}}
        <div class="flex flex-wrap items-center justify-end gap-3">

            {{-- Annuleren --}}
            <flux:button :href="route('backend.catalogs.editions.index', $catalog)" variant="ghost" icon="x-mark"
                class="border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-4!">
                {{ __('Annuleren') }}
            </flux:button>

            {{-- Cataloguseditie opslaan --}}
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="save"
                class="bg-brown-2!">
                {{ __('Cataloguseditie opslaan') }}
            </flux:button>

        </div>

    </form>
</div>
