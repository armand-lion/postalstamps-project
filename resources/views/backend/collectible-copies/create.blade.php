<x-layouts::app :title="__('Verzamelobject aan verzameling toevoegen')">

    <form method="POST" action="{{ route('backend.collectible-copies.store') }}">
        @csrf

        <div class="flex flex-col gap-6 p-4 md:p-6">

            <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

                <flux:heading size="xl">
                    {{ __('Verzamelobject aan verzameling toevoegen') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Voeg een verzamelobject toe aan je persoonlijke verzameling.') }}
                </flux:text>

            </div>

            {{-- Gegevens van het verzamelobject --}}
            <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

                <flux:heading size="lg">
                    {{ __('Gegevens van het verzamelobject') }}
                </flux:heading>

                <div x-data="{
                    collectibleTypeId: '',
                    countryId: '',
                    issueYear: '',

                    collectibles: @js(
    $collectibles
        ->map(
            fn($collectible) => [
                'id' => (string) $collectible->id,
                'collectible_type_id' => (string) $collectible->collectible_type_id,
                'country_id' => (string) ($collectible->country_id ?? ''),
                'issue_year' => (string) ($collectible->issue_year ?? ''),
            ],
        )
        ->values()
        ->all(),
),

                    get hasMatchingCollectibles() {
                        return this.collectibles.some(collectible =>
                            (this.collectibleTypeId === '' ||
                                this.collectibleTypeId === collectible.collectible_type_id) &&
                            (this.countryId === '' ||
                                this.countryId === collectible.country_id) &&
                            (this.issueYear === '' ||
                                this.issueYear === collectible.issue_year)
                        );
                    }
                }" class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                    {{-- Type verzamelobject --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Type verzamelobject') }}
                        </flux:label>

                        <div x-data="{
                            selectedName: @js(__('Alle typen'))
                        }">

                            <flux:dropdown position="bottom" align="start">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-[320px]! max-w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                    <span x-text="selectedName" class="truncate"></span>
                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">


                                    {{-- Alle typen --}}
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="
                                        collectibleTypeId = '';
                                        selectedName = @js(__('Alle typen'));
                                    "
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                        {{ __('Alle typen') }}
                                    </flux:menu.item>

                                    {{-- Type verzamelobject --}}
                                    {{-- Beschikbare verzamelobjecttypen --}}
                                    @foreach ($collectibleTypes as $collectibleType)
                                        @php
                                            $typeName =
                                                $collectibleType->translationWithFallback()?->name ??
                                                $collectibleType->slug;
                                        @endphp

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="
                                            collectibleTypeId = @js((string) $collectibleType->id);
                                            selectedName = @js($typeName);
                                        "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                            {{ $typeName }}
                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>

                            </flux:dropdown>

                        </div>
                    </flux:field>

                    {{-- Land --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Land') }}
                        </flux:label>

                        <div x-data="{
                            selectedName: @js(__('Alle landen'))
                        }">

                            <flux:dropdown position="bottom" align="start">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-[320px]! max-w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                    <span x-text="selectedName" class="truncate"></span>
                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    {{-- Alle landen --}}
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="
                                        countryId = '';
                                        selectedName = @js(__('Alle landen'));
                                    "
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                        {{ __('Alle landen') }}
                                    </flux:menu.item>

                                    {{-- Beschikbare landen --}}
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
                        <flux:label>
                            {{ __('Uitgiftejaar') }}
                        </flux:label>

                        <div x-data="{
                            selectedName: @js(__('Alle jaren'))
                        }">

                            <flux:dropdown position="bottom" align="start">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-[320px]! max-w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
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
                                            issueYear=@js((string) $year);
                                            selectedName=@js((string) $year);
                                        "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                            {{ $year }}
                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>

                            </flux:dropdown>

                        </div>
                    </flux:field>

                    {{-- Verzamelobject --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Verzamelobject') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <div x-data="{
                            selectedId: '',
                            selectedName: @js(__('Selecteer een verzamelobject')),

                            resetInvalidSelection() {
                                if (this.selectedId === '') {
                                    return;
                                }

                                const selectedCollectible = this.collectibles.find(
                                    collectible => collectible.id === this.selectedId
                                );

                                if (!selectedCollectible ||
                                    (this.collectibleTypeId !== '' &&
                                        selectedCollectible.collectible_type_id !== this.collectibleTypeId) ||
                                    (this.countryId !== '' &&
                                        selectedCollectible.country_id !== this.countryId) ||
                                    (this.issueYear !== '' &&
                                        selectedCollectible.issue_year !== this.issueYear)
                                ) {
                                    this.selectedId = '';
                                    this.selectedName = @js(__('Selecteer een verzamelobject'));
                                }
                            }
                        }"
                            x-effect="
                            collectibleTypeId;
                            countryId;
                            issueYear;
                            resetInvalidSelection();
                        ">

                            <input type="hidden" name="collectible_id" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-[320px]! max-w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                    <span x-text="selectedName" class="truncate"></span>
                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    <div x-show="!hasMatchingCollectibles" x-cloak
                                        class="px-4 py-2 text-sm text-zinc-600">
                                        {{ __('Geen verzamelobjecten gevonden voor de geselecteerde filters.') }}
                                    </div>

                                    {{-- Beschikbare verzamelobjecten --}}
                                    @foreach ($collectibles as $collectible)
                                        <div x-show="(collectibleTypeId === '' || collectibleTypeId === @js((string) $collectible->collectible_type_id)) && (countryId === '' || countryId === @js((string) ($collectible->country_id ?? ''))) && (issueYear === '' || issueYear === @js((string) ($collectible->issue_year ?? '')))"
                                            <flux:menu.item as="button" type="button"
                                            x-on:click="
                                                    selectedId = @js((string) $collectible->id);
                                                    selectedName = @js($collectible->name);
                                                "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                            {{ $collectible->name }}

                                            @if ($collectible->issue_year)
                                                ({{ $collectible->issue_year }})
                                            @endif
                                            </flux:menu.item>
                                        </div>
                                    @endforeach

                                </flux:menu>

                            </flux:dropdown>

                        </div>

                        <div class="min-h-5">
                            <flux:error name="collectible_id" />
                        </div>
                    </flux:field>

                </div>

            </div>

            {{-- Gegevens van het exemplaar --}}
            <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

                <flux:heading size="lg">
                    {{ __('Gegevens van het exemplaar') }}
                </flux:heading>

                <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                    {{-- Conditie --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Conditie') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <div x-data="{
                            selectedId: '',
                            selectedName: @js(__('Selecteer een conditie'))
                        }">

                            <input type="hidden" name="condition_id" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-[320px]! max-w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
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
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Aantal') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <div x-data="{
                            quantity: 1,
                            minQuantity: 1,
                            maxQuantity: 9999,

                            increase() {
                                this.quantity = Math.min(this.maxQuantity, Number(this.quantity || 0) + 1);
                            },

                            decrease() {
                                this.quantity = Math.max(this.minQuantity, Number(this.quantity || 1) - 1);
                            }
                        }" class="relative w-[320px] max-w-full">

                            <input type="number" name="quantity" min="1" max="9999" step="1"
                                x-model="quantity" required
                                class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-1 focus:ring-theme-accent-3 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]">

                            <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                <button type="button" x-on:click="increase()"
                                    aria-label="{{ __('Aantal verhogen') }}"
                                    class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                    <flux:icon.chevron-up class="size-3" />
                                </button>

                                <button type="button" x-on:click="decrease()"
                                    aria-label="{{ __('Aantal verlagen') }}"
                                    class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                    <flux:icon.chevron-down class="size-3" />
                                </button>

                            </div>

                        </div>

                        <div class="min-h-5">
                            <flux:error name="quantity" />
                        </div>
                    </flux:field>

                    {{-- Aankoopdatum --}}
                    <flux:field>
                        <div class="w-[320px] max-w-full">
                            <x-forms.date-picker name="acquisition_date" :label="__('Aankoopdatum')" :value="old('acquisition_date')" />
                        </div>
                    </flux:field>

                    {{-- Aankoopprijs --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Aankoopprijs') }}
                        </flux:label>

                        <div x-data="{
                            price: @js(old('purchase_price', '')),

                            increase() {
                                const cents = Math.round(Number(this.price || 0) * 100);
                                this.price = ((cents + 1) / 100).toFixed(2);
                            },

                            decrease() {
                                const cents = Math.round(Number(this.price || 0) * 100);
                                this.price = (Math.max(0, cents - 1) / 100).toFixed(2);
                            }
                        }" class="relative w-[320px] max-w-full">

                            <input type="number" name="purchase_price" min="0" step="0.01"
                                x-model="price" placeholder="0,00"
                                class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-1 focus:ring-theme-accent-3 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]">

                            <div class="absolute right-1 top-1 flex flex-col">

                                <button type="button" x-on:click="increase()"
                                    aria-label="{{ __('Aankoopprijs verhogen') }}"
                                    class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                    <flux:icon.chevron-up class="size-3" />
                                </button>

                                <button type="button" x-on:click="decrease()"
                                    aria-label="{{ __('Aankoopprijs verlagen') }}"
                                    class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                    <flux:icon.chevron-down class="size-3" />
                                </button>

                            </div>
                        </div>

                        <div class="min-h-5">
                            <flux:error name="purchase_price" />
                        </div>
                    </flux:field>

                    {{-- Valuta --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Valuta') }}
                        </flux:label>

                        <div x-data="{
                            selectedId: '',
                            selectedName: @js(__('Selecteer een valuta'))
                        }">

                            <input type="hidden" name="purchase_currency" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-[320px]! max-w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                    <span x-text="selectedName" class="truncate"></span>
                                </flux:button>

                                <flux:menu
                                    class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    {{-- Huidige valuta --}}
                                    <flux:menu.item disabled>
                                        {{ __('Huidige valuta') }}
                                    </flux:menu.item>

                                    @foreach ([
        'EUR' => 'Euro (€)',
        'USD' => 'Amerikaanse dollar ($)',
        'GBP' => 'Britse pond (£)',
        'CHF' => 'Zwitserse frank (CHF)',
        'CAD' => 'Canadese dollar (CAD)',
        'AUD' => 'Australische dollar (AUD)',
        'JPY' => 'Japanse yen (¥)',
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

                                    <flux:menu.separator />

                                    {{-- Historische valuta --}}
                                    <flux:menu.item disabled>
                                        {{ __('Historische valuta') }}
                                    </flux:menu.item>

                                    @foreach ([
        'NLG' => 'Nederlandse gulden (ƒ)',
        'DEM' => 'Duitse mark (DM)',
        'BEF' => 'Belgische frank (BEF)',
        'FRF' => 'Franse frank (FRF)',
        'ITL' => 'Italiaanse lire (ITL)',
        'ESP' => 'Spaanse peseta (ESP)',
        'ATS' => 'Oostenrijkse schilling (ATS)',
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
                            <flux:error name="purchase_currency" />
                        </div>
                    </flux:field>

                    {{-- Wijze van verkrijgen --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Wijze van verkrijgen') }}
                        </flux:label>

                        @php
                            $selectedAcquisitionMethodSlug = old('acquisition_method', '');

                            $selectedAcquisitionMethod = $acquisitionMethods->firstWhere(
                                'slug',
                                $selectedAcquisitionMethodSlug,
                            );

                            $selectedAcquisitionMethodName = $selectedAcquisitionMethod
                                ? $selectedAcquisitionMethod->translationWithFallback()?->name ??
                                    $selectedAcquisitionMethod->slug
                                : __('Selecteer een verkrijgingswijze');
                        @endphp

                        <div x-data="{
                            selectedSlug: @js($selectedAcquisitionMethodSlug),
                            selectedName: @js($selectedAcquisitionMethodName)
                        }">

                            <input type="hidden" name="acquisition_method" x-model="selectedSlug">

                            <flux:dropdown position="bottom" align="start">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-[320px]! max-w-full justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span x-text="selectedName" class="truncate"></span>

                                </flux:button>

                                <flux:menu
                                    class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    {{-- Geen verkrijgingswijze geselecteerd --}}
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="
                        selectedSlug = '';
                        selectedName = @js(__('Selecteer een verkrijgingswijze'));
                    "
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                        {{ __('Selecteer een verkrijgingswijze') }}

                                    </flux:menu.item>

                                    {{-- Beschikbare verkrijgingswijzen --}}
                                    @foreach ($acquisitionMethods as $acquisitionMethod)
                                        @php
                                            $acquisitionMethodName =
                                                $acquisitionMethod->translationWithFallback()?->name ??
                                                $acquisitionMethod->slug;
                                        @endphp

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="
                            selectedSlug = @js($acquisitionMethod->slug);
                            selectedName = @js($acquisitionMethodName);
                        "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ $acquisitionMethodName }}

                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>

                            </flux:dropdown>

                        </div>

                        <div class="min-h-5">
                            <flux:error name="acquisition_method" />
                        </div>
                    </flux:field>

                </div>

                <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                    {{-- Bijzondere variant / afwijking --}}
                    <div class="mt-4" x-data="{
                        isVariant: @js((bool) old('is_variant', false))
                    }">

                        <flux:field>

                            <div x-on:change="isVariant = $event.target.checked">

                                <flux:checkbox name="is_variant" value="1"
                                    :checked="(bool) old('is_variant', false)"
                                    :label="__('Bijzondere variant / afwijking')"
                                    class="[&_[data-flux-checkbox-indicator]]:bg-brown-5! [&_[data-flux-checkbox-indicator]]:border-theme-accent-3!" />

                            </div>

                            <div class="min-h-5">
                                <flux:error name="is_variant" />
                            </div>

                        </flux:field>

                        {{-- Beschrijving van de afwijking --}}
                        <div x-show="isVariant" x-cloak class="mt-3">

                            @foreach ([
        'nl' => 'Nederlands',
        'en' => 'Engels',
        'de' => 'Duits',
        'fr' => 'Frans',
        'es' => 'Spaans',
    ] as $locale => $language)
                                <flux:field>

                                    <flux:label>
                                        {{ __('Beschrijving van de afwijking') }}
                                        — {{ __($language) }}
                                    </flux:label>

                                    <textarea name="translations[{{ $locale }}][variant_description]" rows="3"
                                        class="block w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5">{{ old("translations.$locale.variant_description", '') }}</textarea>

                                    <div class="min-h-5">
                                        <flux:error name="translations.{{ $locale }}.variant_description" />
                                    </div>

                                </flux:field>
                            @endforeach

                        </div>

                    </div>

                </div>

                {{-- Opmerkingen --}}
                <div class="mt-4">
                    <flux:field>
                        <flux:label>
                            {{ __('Opmerkingen') }}
                        </flux:label>

                        <textarea name="notes" rows="4"
                            class="block w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 py-2 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5">{{ old('notes', '') }}</textarea>

                        <div class="min-h-5">
                            <flux:error name="notes" />
                        </div>
                    </flux:field>
                </div>
            </div>

            {{-- Acties --}}
            <div class="flex flex-wrap justify-end gap-3 rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

                {{-- Annuleren --}}
                <flux:button :href="route('backend.collectibles.index')" variant="ghost" icon="x-mark"
                    class="text-zinc-900!">
                    {{ __('Annuleren') }}
                </flux:button>

                {{-- Opslaan --}}
                <flux:button type="submit" variant="primary" icon="check" class="bg-brown-1! hover:bg-brown-2!">
                    {{ __('Verzamelobject opslaan') }}
                </flux:button>
            </div>

        </div>
    </form>

</x-layouts::app>
