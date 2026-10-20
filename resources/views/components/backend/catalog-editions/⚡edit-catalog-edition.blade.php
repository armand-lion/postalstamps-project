<?php

use App\Models\Catalog;
use App\Models\CatalogEdition;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
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
        abort_unless(
            $this->edition->catalog_id === $this->catalog->id,
            404
        );

        $validated = $this->validate([
            'edition_name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('catalog_editions', 'edition_name')
                    ->where('catalog_id', $this->catalog->id)
                    ->where('edition_year', $this->edition_year)
                    ->ignore($this->edition->id),
            ],
            'edition_year' => [
                'required',
                'integer',
                'between:1,65535',
            ],
            'publication_date' => [
                'nullable',
                'date',
            ],
            'currency' => [
                'nullable',
                'string',
                'size:3',
            ],
            'notes' => [
                'nullable',
                'string',
            ],
        ]);

        // Werk de bestaande cataloguseditie bij.
        $this->edition->update($validated);

        session()->flash(
            'success',
            __('De cataloguseditie is succesvol bijgewerkt.')
        );

        // Ga terug naar de detailpagina.
        return $this->redirect(
            route('backend.catalogs.editions.show', [
                $this->catalog,
                $this->edition,
            ]),
            navigate: true
        );
    }
};

?>

<div>
    <form wire:submit="save" class="space-y-6">

        {{-- Editienaam --}}
        <flux:field>
            <flux:label>
                <span class="flex items-center gap-1">
                    {{ __('Editienaam') }}
                    <span class="text-red-600">*</span>
                </span>
            </flux:label>

            <flux:input
                wire:model="edition_name"
                type="text"
                maxlength="150"
                :placeholder="__('Bijvoorbeeld: NVPH 2026')"
                required
            />

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

            <flux:input
                wire:model="edition_year"
                type="number"
                min="1"
                max="65535"
                :placeholder="__('Bijvoorbeeld: 2026')"
                required
            />

            <flux:error name="edition_year" />
        </flux:field>

        {{-- Publicatiedatum --}}
        <flux:field>
            <flux:label>
                {{ __('Publicatiedatum') }}
            </flux:label>

            <flux:input
                wire:model="publication_date"
                type="date"
            />

            <flux:error name="publication_date" />
        </flux:field>

        {{-- Valuta --}}
        <flux:field>
            <flux:label>
                {{ __('Valuta') }}
            </flux:label>

            <flux:select wire:model="currency">
                <flux:select.option value="">
                    {{ __('Selecteer een valuta') }}
                </flux:select.option>

                <flux:select.option value="EUR">
                    EUR — Euro
                </flux:select.option>

                <flux:select.option value="USD">
                    USD — Amerikaanse dollar
                </flux:select.option>

                <flux:select.option value="GBP">
                    GBP — Britse pond
                </flux:select.option>

                <flux:select.option value="CHF">
                    CHF — Zwitserse frank
                </flux:select.option>
            </flux:select>

            <flux:error name="currency" />
        </flux:field>

        {{-- Opmerkingen --}}
        <flux:field>
            <flux:label>
                {{ __('Opmerkingen') }}
            </flux:label>

            <flux:textarea
                wire:model="notes"
                rows="4"
                :placeholder="__('Eventuele opmerkingen over deze cataloguseditie')"
            />

            <flux:error name="notes" />
        </flux:field>

        {{-- Actieknoppen --}}
        <div class="flex flex-wrap items-center justify-end gap-3">

            {{-- Annuleren --}}
            <flux:button
                :href="route('backend.catalogs.editions.show', [$catalog, $edition])"
                variant="ghost"
                icon="x-mark"
            >
                {{ __('Annuleren') }}
            </flux:button>

            {{-- Wijzigingen opslaan --}}
            <flux:button
                type="submit"
                variant="primary"
                icon="check"
                wire:loading.attr="disabled"
                wire:target="save"
            >
                {{ __('Wijzigingen opslaan') }}
            </flux:button>

        </div>

    </form>
</div>
