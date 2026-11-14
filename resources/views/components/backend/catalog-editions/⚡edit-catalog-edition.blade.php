<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component {
    public Catalog $catalog;

    public CatalogEdition $edition;

    public string $edition_name = '';

    public ?int $edition_year = null;

    public ?string $publication_date = null;

    public ?string $currency = null;

    public string $notes = '';

    /**
     * Vul het formulier met de bestaande editiegegevens.
     */
    public function mount(Catalog $catalog, CatalogEdition $edition): void
    {
        abort_unless($edition->catalog_id === $catalog->id, 404);

        $this->catalog = $catalog;
        $this->edition = $edition;

        $this->edition_name = $edition->edition_name;
        $this->edition_year = $edition->edition_year;
        $this->publication_date = $edition->publication_date?->format('Y-m-d');
        $this->currency = $edition->currency;
        $this->notes = $edition->notes ?? '';
    }

    /**
     * Sla de wijzigingen op.
     */
    public function save()
    {
        abort_unless($this->edition->catalog_id === $this->catalog->id, 404);

        // Verwijder ongewenste witruimte vóór de validatie.
        $this->edition_name = trim($this->edition_name);
        $this->notes = trim($this->notes);

        $validated = $this->validate([
            'edition_name' => ['required', 'string', 'max:150', Rule::unique('catalog_editions', 'edition_name')->where('catalog_id', $this->catalog->id)->where('edition_year', $this->edition_year)->ignore($this->edition->id)],
            'edition_year' => ['required', 'integer', 'between:1,65535'],
            'publication_date' => ['nullable', 'date'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
        ]);

        // Verwijder ongewenste witruimte uit de gevalideerde gegevens.
        $validated['edition_name'] = trim($validated['edition_name']);
        $validated['notes'] = trim($validated['notes'] ?? '');

        // Werk de bestaande cataloguseditie bij.
        $this->edition->update($validated);

        session()->flash('success', __('De cataloguseditie is succesvol bijgewerkt.'));

        // Ga terug naar de detailpagina.
        return $this->redirect(route('backend.catalogs.editions.show', [$this->catalog, $this->edition]), navigate: true);
    }
};

?>

<div class=" rounded-xl border border-theme-accent-3 bg-brown-6 p-6">
    <form wire:submit="save" class="space-y-6">

        {{-- Editienaam --}}
        <flux:field>
            <flux:label class="text-zinc-900!">
                <span class="flex items-center gap-1">
                    {{ __('Editienaam') }}
                    <span class="text-red-600">*</span>
                </span>
            </flux:label>

            <input wire:model="edition_name" type="text" maxlength="150"
                placeholder="{{ __('Bijvoorbeeld: NVPH 2026') }}" required
                class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5">

            <flux:error name="edition_name" />
        </flux:field>

        {{-- Editiejaar --}}
        <flux:field>
            <flux:label class="text-zinc-900!">
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
                    if (this.year === '' || this.year === null) {
                        this.year = this.minYear;
                    } else {
                        this.year = Math.min(this.maxYear, Number(this.year) + 1);
                    }
                },

                decrease() {
                    if (this.year === '' || this.year === null) {
                        this.year = this.minYear;
                    } else {
                        this.year = Math.max(this.minYear, Number(this.year) - 1);
                    }
                }
            }" class="relative">
                <input type="number" min="1" max="65535" x-model.number="year"
                    placeholder="{{ __('Bijvoorbeeld: 2026') }}" required
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
        <x-forms.date-picker name="publication_date" :label="__('Publicatiedatum')" :value="$publication_date"
            livewire-model="publication_date" />

        {{-- Valuta --}}
        <flux:field>
            <flux:label class="text-zinc-900!">
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

                        {{-- Geen valuta --}}
                        <flux:menu.item as="button" type="button" x-on:click="selectedId = null"
                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                            {{ __('Selecteer een valuta') }}
                        </flux:menu.item>

                        <flux:menu.separator />

                        {{-- Huidige valuta --}}
                        <flux:menu.item disabled>
                            {{ __('Huidige valuta') }}
                        </flux:menu.item>

                        @foreach (['EUR', 'USD', 'GBP', 'CHF', 'CAD', 'AUD', 'JPY'] as $code)
                            <flux:menu.item as="button" type="button"
                                x-on:click="selectedId = @js($code)"
                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                {{ $currencies[$code] }}
                            </flux:menu.item>
                        @endforeach

                        <flux:menu.separator />

                        {{-- Historische valuta --}}
                        <flux:menu.item disabled>
                            {{ __('Historische valuta') }}
                        </flux:menu.item>

                        @foreach (['NLG', 'DEM', 'BEF', 'FRF', 'ITL', 'ESP', 'ATS'] as $code)
                            <flux:menu.item as="button" type="button"
                                x-on:click="selectedId = @js($code)"
                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                {{ $currencies[$code] }}
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
            <flux:label class="text-zinc-900!">
                {{ __('Opmerkingen') }}
            </flux:label>

            <textarea wire:model="notes" rows="4" placeholder="{{ __('Eventuele opmerkingen over deze cataloguseditie') }}"
                class="block w-full resize-y rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5"></textarea>

            <flux:error name="notes" />
        </flux:field>

        {{-- Actieknoppen --}}
        <div class="flex flex-wrap items-center justify-end gap-3">

            {{-- Annuleren --}}
            <flux:button :href="route('backend.catalogs.editions.show', [$catalog, $edition])" variant="ghost"
                icon="x-mark" class="text-zinc-900!">
                {{ __('Annuleren') }}
            </flux:button>

            {{-- Wijzigingen opslaan --}}
            <flux:button type="submit" variant="primary" icon="check" wire:loading.attr="disabled" wire:target="save"
                class="bg-brown-1 hover:bg-brown-2">
                {{ __('Wijzigingen opslaan') }}
            </flux:button>

        </div>

    </form>
</div>
