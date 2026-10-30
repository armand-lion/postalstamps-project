<x-layouts::app :title="__('Postzegel aan verzameling toevoegen')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div
            class="flex flex-col justify-between gap-4 rounded-lg border border-theme-accent-1 bg-brown-6 p-5 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ __('Postzegel aan verzameling toevoegen') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Voeg een postzegel uit de catalogus toe aan je persoonlijke verzameling.') }}
                </flux:text>
            </div>
            {{-- Actieknoppen --}}
            <div class="flex justify-end gap-3">

                <flux:button :href="route('backend.stamp-copies.index')" variant="ghost">
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button type="submit" variant="primary" icon="check" class="bg-brown-2!">
                    {{ __('Opslaan') }}
                </flux:button>

            </div>

        </div>

        {{-- Validatiefouten --}}
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Formulier --}}
        <form method="POST" action="{{ route('backend.stamp-copies.store') }}" class="flex flex-col gap-6" novalidate>

            @csrf

            <div class="rounded-xl border  border-theme-accent-1 bg-brown-6 p-4 md:p-6">

                <flux:heading size="lg">
                    {{ __('Gegevens van de postzegel') }}
                </flux:heading>

                <div x-data="{
                    countryId: '',
                    issueYear: '',
                    catalogId: '',
                    catalogEditionId: '',
                    stampId: @js((string) old('stamp_id', '')),

                    stamps: @js(
    $stamps
        ->map(
            fn($stamp) => [
                'id' => (string) $stamp->id,
                'country_id' => (string) $stamp->country_id,
                'issue_year' => (string) $stamp->issue_year,
                'catalog_edition_ids' => $stamp->catalogEntries->pluck('catalog_edition_id')->map(fn($id) => (string) $id)->values()->all(),
            ],
        )
        ->values()
        ->all(),
),

                    editions: @js(
    $catalogEditions
        ->map(
            fn($edition) => [
                'id' => (string) $edition->id,
                'catalog_id' => (string) $edition->catalog_id,
            ],
        )
        ->values()
        ->all(),
)
                }" class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                    {{-- Land --}}
                    <flux:field>
                        <flux:label>{{ __('Land') }}</flux:label>

                        <div x-data="{
                            selectedName: @js(__('Alle landen'))
                        }">

                            <flux:dropdown position="bottom" align="start">
                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-[320px]! max-w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                    <span x-text="selectedName" class="truncate"></span>
                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    <flux:menu.item as="button" type="button"
                                        x-on:click="
                                            countryId = '';
                                            selectedName = @js(__('Alle landen'));
                                        "
                                        class="max-h-80 overflow-y-auto border-theme-accent-3! bg-brown-6!">
                                        {{ __('Alle landen') }}
                                    </flux:menu.item>

                                    @foreach ($countries as $country)
                                        @php
                                            $countryName = $country->translationWithFallback()?->name ?? $country->name;
                                        @endphp

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="
                                                countryId = @js((string) $country->id);
                                                selectedName = @js($countryName);
                                            "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                            {{ $countryName }}
                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </flux:field>

                    {{-- Uitgiftejaar --}}
                    <flux:field>
                        <flux:label>{{ __('Uitgiftejaar') }}</flux:label>

                        <div x-data="{
                            selectedName: @js(__('Alle jaren'))
                        }">

                            <flux:dropdown position="bottom" align="start">
                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-[320px]! w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                    <span x-text="selectedName" class="truncate"></span>
                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    {{-- Alle jaren --}}
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="
                                            issueYear = '';
                                            selectedName = @js(__('Alle jaren'));
                                        "
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                        {{ __('Alle jaren') }}
                                    </flux:menu.item>

                                    {{-- Beschikbare uitgiftejaren --}}
                                    @foreach ($issueYears as $year)
                                        <flux:menu.item as="button" type="button"
                                            x-on:click="
                                                issueYear = @js((string) $year);
                                                selectedName = @js((string) $year);
                                            "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                            {{ $year }}
                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </flux:field>

                    {{-- Catalogus --}}
                    <flux:field>
                        <flux:label>{{ __('Catalogus') }}</flux:label>

                        <div x-data="{
                            selectedName: @js(__('Alle catalogi'))
                        }">

                            <flux:dropdown position="bottom" align="start">
                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-[320px]! w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                    <span x-text="selectedName" class="truncate"></span>
                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    {{-- Alle catalogi --}}
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="
                        catalogId = '';
                        selectedName = @js(__('Alle catalogi'));
                    "
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                        {{ __('Alle catalogi') }}
                                    </flux:menu.item>

                                    {{-- Beschikbare catalogi --}}
                                    @foreach ($catalogs as $catalog)
                                        @php
                                            $catalogName = $catalog->abbreviation ?: $catalog->name;
                                        @endphp

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="
                            catalogId = @js((string) $catalog->id);
                            selectedName = @js($catalogName);
                        "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                            {{ $catalogName }}
                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </flux:field>

                    {{-- Cataloguseditie --}}
                    <flux:field>
                        <flux:label>{{ __('Cataloguseditie') }}</flux:label>

                        <div x-data="{
                            selectedName: @js(__('Alle catalogusedities')),
                            previousCatalogId: ''
                        }"
                            x-effect="
        if (catalogId !== previousCatalogId) {
            catalogEditionId = '';
            selectedName = @js(__('Alle catalogusedities'));
            previousCatalogId = catalogId;
        }
    ">

                            <flux:dropdown position="bottom" align="start">
                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-[320px]! w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                    <span x-text="selectedName" class="truncate"></span>
                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    {{-- Alle catalogusedities --}}
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="
                        catalogEditionId
                        selectedName = @js(__('Alle catalogusedities'));
                    "
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                        {{ __('Alle catalogusedities') }}
                                    </flux:menu.item>

                                    {{-- Beschikbare catalogusedities --}}
                                    @foreach ($catalogEditions as $edition)
                                        @php
                                            $editionName =
                                                ($edition->catalog?->abbreviation ?: $edition->catalog?->name) .
                                                ' — ' .
                                                $edition->edition_name .
                                                ' (' .
                                                $edition->edition_year .
                                                ')';
                                        @endphp

                                        <flux:menu.item as="button" type="button"
                                            x-show="catalogId === '' || catalogId === @js((string) $edition->catalog_id)"
                                            x-on:click="
        catalogEditionId = @js((string) $edition->id);
        selectedName = @js($editionName);
    "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                            {{ $editionName }}
                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>
                            </flux:dropdown>
                        </div>
                    </flux:field>

                    {{-- Postzegel --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Postzegel') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        @php
                            $selectedStampId = old('stamp_id', request()->query('stamp_id'));

                            $selectedStamp = $stamps->firstWhere('id', $selectedStampId);
                        @endphp

                        <div x-data="{
                            selectedId: @js($selectedStamp ? (string) $selectedStamp->id : ''),
                            selectedName: @js($selectedStamp ? $selectedStamp->name . ' (' . $selectedStamp->issue_year . ')' : __('Selecteer een postzegel')),

                            resetInvalidSelection() {
                                if (this.selectedId === '') return;

                                const stamp = this.stamps.find(
                                    item => item.id === this.selectedId
                                );

                                if (!stamp) {
                                    this.clearSelection();
                                    return;
                                }

                                if (this.countryId !== '' && stamp.country_id !== this.countryId) {
                                    this.clearSelection();
                                    return;
                                }

                                if (this.issueYear !== '' && stamp.issue_year !== this.issueYear) {
                                    this.clearSelection();
                                    return;
                                }

                                if (
                                    this.catalogEditionId !== '' &&
                                    !stamp.catalog_edition_ids.includes(this.catalogEditionId)
                                ) {
                                    this.clearSelection();
                                    return;
                                }

                                if (this.catalogEditionId === '' && this.catalogId !== '') {
                                    const belongsToCatalog = stamp.catalog_edition_ids.some(editionId =>
                                        this.editions.some(edition =>
                                            edition.id === editionId &&
                                            edition.catalog_id === this.catalogId
                                        )
                                    );

                                    if (!belongsToCatalog) {
                                        this.clearSelection();
                                    }
                                }
                            },

                            clearSelection() {
                                this.selectedId = '';
                                this.selectedName = @js(__('Selecteer een postzegel'));
                            }
                        }"
                            x-effect="
                                countryId;
                                issueYear;
                                catalogId;
                                catalogEditionId;
                                resetInvalidSelection();
                            ">
                            <input type="hidden" name="stamp_id" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start">
                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-[320px]! w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span x-text="selectedName" class="truncate"></span>

                                </flux:button>

                                <flux:menu
                                    class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    @foreach ($stamps as $stamp)
                                        <flux:menu.item as="button" type="button"
                                            x-show="
                                        (() => {
                                            const stamp = stamps.find(
                                                item => item.id === @js((string) $stamp->id)
                                            );

                                            if (!stamp) return false;

                                            if (countryId !== '' && stamp.country_id !== countryId) {
                                                return false;
                                            }

                                            if (issueYear !== '' && stamp.issue_year !== issueYear) {
                                                return false;
                                            }

                                            if (catalogEditionId !== '') {
                                                return stamp.catalog_edition_ids.includes(catalogEditionId);
                                            }

                                                if (catalogId !== '') {
                                                    return stamp.catalog_edition_ids.some(editionId =>
                                                        editions.some(edition =>
                                                            edition.id === editionId &&
                                                            edition.catalog_id === catalogId
                                                        )
                                                    );
                                                }

                                            return true;
                                        })
()
                                            "
                                            x-on:click="
                                                    selectedId = @js((string) $stamp->id);
                                                    selectedName = @js($stamp->name . ' (' . $stamp->issue_year . ')');
                                                "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ $stamp->name }} ({{ $stamp->issue_year }})

                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>
                            </flux:dropdown>
                        </div>

                        <div class="min-h-5">
                            <flux:error name="stamp_id" />
                        </div>
                    </flux:field>

                    {{-- Conditie --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Conditie') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        @php
                            $selectedCondition = $conditions->firstWhere('id', old('condition_id'));

                            $selectedConditionName = $selectedCondition
                                ? $selectedCondition->translationWithFallback()?->name ?? $selectedCondition->slug
                                : __('Selecteer een conditie');
                        @endphp

                        <div x-data="{
                            selectedId: @js(old('condition_id', '')),
                            selectedName: @js($selectedConditionName)
                        }">
                            <input type="hidden" name="condition_id" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start">
                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-[320px]! w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span x-text="selectedName" class="truncate"></span>

                                </flux:button>

                                <flux:menu
                                    class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    @foreach ($conditions as $condition)
                                        @php
                                            $conditionName =
                                                $condition->translationWithFallback()?->name ?? $condition->slug;
                                        @endphp

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="
                        selectedId = @js((string) $condition->id);
                        selectedName = @js($conditionName);
                    "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ $conditionName }}

                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>
                            </flux:dropdown>
                        </div>

                        <div class="min-h-5">
                            <flux:error name="condition_id" />
                        </div>
                    </flux:field>

                    {{-- Aantal --}}
                    <div class="w-full sm:w-40">
                        <flux:field>
                            <flux:label>
                                <span class="flex items-center gap-1">
                                    {{ __('Aantal') }}
                                    <span class="text-red-600">*</span>
                                </span>
                            </flux:label>

                            <div x-data="{
                                quantity: @js((int) old('quantity', 1)),
                                increase() {
                                    this.quantity = (parseInt(this.quantity) || 0) + 1;
                                },
                                decrease() {
                                    this.quantity = Math.max(1, (parseInt(this.quantity) || 1) - 1);
                                }
                            }" class="relative">
                                <input type="number" name="quantity" id="quantity" min="1" step="1"
                                    x-model.number="quantity" required
                                    class="quantity-input block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 focus:border-theme-accent-3 focus:outline-none focus:ring-1 focus:ring-theme-accent-3 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]">

                                {{-- Pijltjes omhoog en omlaag --}}
                                <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                    {{-- Aantal verhogen --}}
                                    <button type="button" x-on:click="increase()"
                                        aria-label="{{ __('Aantal verhogen') }}"
                                        class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                        <flux:icon.chevron-up class="size-3" />
                                    </button>

                                    {{-- Aantal verlagen --}}
                                    <button type="button" x-on:click="decrease()" :disabled="quantity <= 1"
                                        aria-label="{{ __('Aantal verlagen') }}"
                                        class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5 disabled:cursor-not-allowed disabled:opacity-50">
                                        <flux:icon.chevron-down class="size-3" />
                                    </button>

                                </div>
                            </div>

                            <div class="min-h-5">
                                <flux:error name="quantity" />
                            </div>
                        </flux:field>
                    </div>

                </div>
            </div>

            {{-- Actieknoppen --}}
            <div
                class="flex justify-end gap-4 rounded-lg border border-theme-accent-1 bg-brown-6 p-5 sm:flex-row sm:items-center">

                <flux:button :href="route('backend.stamp-copies.index')" variant="ghost">
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button type="submit" variant="primary" icon="check" class="bg-brown-2!">
                    {{ __('Opslaan') }}
                </flux:button>

            </div>

        </form>

    </div>
</x-layouts::app>
