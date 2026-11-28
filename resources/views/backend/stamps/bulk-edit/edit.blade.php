<x-layouts::app :title="__('Geselecteerde postzegels bewerken')">

    <div class="flex flex-col gap-6 p-4 md:p-6" x-data="{
        commonCountryId: @js($commonCountryId),
        commonIssueYear: @js($commonIssueYear),
    
        countryOperation: @js(old('country_operation', 'unchanged')),
        issueYearOperation: @js(old('issue_year_operation', 'unchanged')),
    
        newCountryId: @js(old('country_id')),
        newIssueYear: @js(old('issue_year')),
    
        get effectiveCountryId() {
            return this.countryOperation === 'set' ?
                this.newCountryId :
                this.commonCountryId;
        },
    
        get effectiveIssueYear() {
            return this.issueYearOperation === 'set' ?
                this.newIssueYear :
                this.commonIssueYear;
        }
    }">

        {{-- Paginakop --}}
        <div
            class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Geselecteerde postzegels bewerken') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Wijzig algemene gegevens van meerdere postzegels tegelijkertijd.') }}
                </flux:text>

                <flux:text class="mt-2 font-medium text-zinc-900!">
                    {{ __('Geselecteerde postzegels') }}: {{ $selectedCount }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.stamps.bulk-edit.selected')" icon="arrow-left"
                class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">

                {{ __('Terug naar selectieoverzicht') }}

            </flux:button>

        </div>

        @include('partials.flash-messages')

        {{-- Toelichting --}}
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

            <flux:heading size="lg">
                {{ __('Werkwijze') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Geef per veld aan welke bewerking je wilt uitvoeren.') }}
            </flux:text>

            <flux:text class="mt-2">
                {{ __('Velden die op Ongewijzigd staan, behouden hun bestaande waarde.') }}
            </flux:text>

            <flux:text class="mt-2">
                {{ __('Voordat wijzigingen worden opgeslagen, krijg je eerst een overzicht van de voorgestelde wijzigingen.') }}
            </flux:text>

        </div>

        <form method="POST" action="{{ route('backend.stamps.bulk-edit.preview') }}" class="flex flex-col gap-6">

            @csrf

            {{-- Algemene postzegelgegevens --}}
            <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

                <flux:heading size="lg">
                    {{ __('Algemene postzegelgegevens') }}
                </flux:heading>

                {{-- Bewerkbare velden --}}
                <div class="mt-4 flex flex-col gap-4">

                    {{-- Land of gebied --}}
                    <div class="grid grid-cols-1 items-start gap-4 rounded-lg border border-theme-accent-3 p-4 md:grid-cols-3"
                        x-data="{
                            operation: @js(old('country_operation', 'unchanged'))
                        }"
                        x-on:bulk-operation-changed="
                        if ($event.detail.field === 'country_operation') {
                            operation = $event.detail.operation;
                            countryOperation = operation;
                        }
                    ">

                        <div>
                            <flux:heading>
                                {{ __('Land of gebied') }}
                            </flux:heading>
                        </div>

                        <flux:field>
                            <flux:label>{{ __('Bewerking') }}</flux:label>

                            @include('partials.bulk-operation-select', [
                                'fieldName' => 'country_operation',
                                'options' => $requiredOperationOptions,
                            ])
                        </flux:field>

                        {{-- Invoerveld voor land of gebied --}}
                        <div x-show="operation === 'set'" x-cloak>

                            @php
                                $selectedCountryId = old('country_id');
                                $selectedCountry = $countries->firstWhere('id', $selectedCountryId);
                            @endphp

                            <flux:field>

                                <flux:label>
                                    <span class="flex items-center gap-1">
                                        {{ __('Nieuw land of gebied') }}
                                        <span class="text-red-600">*</span>
                                    </span>
                                </flux:label>

                                <div class="w-full min-w-0 max-w-full" x-data="{
                                    selectedId: @js($selectedCountryId),
                                    selectedName: @js($selectedCountry?->translationWithFallback()?->name ?? ($selectedCountry?->name ?? __('Selecteer een land of gebied')))
                                }"
                                    x-effect="newCountryId = selectedId">

                                    <input type="hidden" name="country_id" x-model="selectedId"
                                        x-bind:disabled="operation !== 'set'">

                                    <flux:dropdown position="bottom" align="start"
                                        class="block w-full min-w-0 max-w-full">

                                        <flux:button type="button" icon-trailing="chevron-down"
                                            icon-trailing:class="shrink-0!"
                                            class="w-full! min-w-0! max-w-full! overflow-hidden justify-between! [&>span]:min-w-0 [&>span]:flex-1 border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                            <span x-text="selectedName" x-bind:title="selectedName"
                                                class="block w-full truncate text-left"></span>

                                        </flux:button>

                                        <flux:menu
                                            class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                            @foreach ($countries as $country)
                                                @php
                                                    $countryName =
                                                        $country->translationWithFallback()?->name ?? $country->name;
                                                @endphp

                                                <flux:menu.item
                                                    class="text-zinc-900! hover:bg-brown-5! data-focus:bg-brown-5!"
                                                    x-on:click="
                                                selectedId = @js($country->id);
                                                selectedName = @js($countryName);
                                            ">

                                                    {{ $countryName }}

                                                </flux:menu.item>
                                            @endforeach

                                        </flux:menu>

                                    </flux:dropdown>

                                </div>

                                <flux:error name="country_id" />

                            </flux:field>

                        </div>

                    </div>

                    {{-- Uitgiftejaar --}}
                    <div class="grid grid-cols-1 items-start gap-4 rounded-lg border border-theme-accent-3 p-4 md:grid-cols-3"
                        x-data="{
                            operation: @js(old('issue_year_operation', 'unchanged'))
                        }"
                        x-on:bulk-operation-changed="
                        if ($event.detail.field === 'issue_year_operation') {
                            operation = $event.detail.operation;
                            issueYearOperation = operation;
                        }
                    ">

                        <div>
                            <flux:heading>
                                {{ __('Uitgiftejaar') }}
                            </flux:heading>
                        </div>

                        <flux:field>

                            <flux:label>{{ __('Bewerking') }}</flux:label>

                            @include('partials.bulk-operation-select', [
                                'fieldName' => 'issue_year_operation',
                                'options' => $requiredOperationOptions,
                            ])

                        </flux:field>

                        {{-- Invoerveld voor uitgiftejaar --}}
                        <div x-show="operation === 'set'" x-cloak>

                            <flux:field>

                                <flux:label>
                                    <span class="flex items-center gap-1">
                                        {{ __('Nieuw uitgiftejaar') }}
                                        <span class="text-red-600">*</span>
                                    </span>
                                </flux:label>

                                <div x-data="{
                                    year: @js(old('issue_year') ?? ''),
                                    minYear: 1840,
                                    maxYear: 2200,
                                
                                    increase() {
                                        if (this.year === '') {
                                            this.year = this.minYear;
                                        } else {
                                            this.year = Math.min(this.maxYear, Math.max(this.minYear, Number(this.year) + 1));
                                        }
                                    },
                                
                                    decrease() {
                                        if (this.year === '') {
                                            this.year = this.minYear;
                                        } else {
                                            this.year = Math.max(this.minYear, Math.min(this.maxYear, Number(this.year) - 1));
                                        }
                                    }
                                }" class="relative">

                                    <input type="number" name="issue_year" min="1840" max="2200" step="1"
                                        x-model="year" x-effect="newIssueYear = year"
                                        x-bind:disabled="operation !== 'set'"
                                        class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 focus:border-theme-accent-3 focus:outline-none focus:ring-1 focus:ring-theme-accent-3 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]">

                                    <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                        <button type="button" x-on:click="increase()"
                                            aria-label="{{ __('Jaar verhogen') }}"
                                            class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">

                                            <flux:icon.chevron-up class="size-3" />

                                        </button>

                                        <button type="button" x-on:click="decrease()"
                                            aria-label="{{ __('Jaar verlagen') }}"
                                            class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">

                                            <flux:icon.chevron-down class="size-3" />

                                        </button>

                                    </div>

                                </div>

                                <div class="min-h-5">
                                    <flux:error name="issue_year" />
                                </div>

                            </flux:field>

                        </div>

                    </div>

                    {{-- Uitgiftedatum --}}
                    <div class="grid grid-cols-1 items-start gap-4 rounded-lg border border-theme-accent-3 p-4 md:grid-cols-3"
                        x-data="{
                            operation: @js(old('issue_date_operation', 'unchanged'))
                        }"
                        x-on:bulk-operation-changed="
                        if ($event.detail.field === 'issue_date_operation') {
                            operation = $event.detail.operation;
                        }
                    ">

                        <div>
                            <flux:heading>
                                {{ __('Uitgiftedatum') }}
                            </flux:heading>
                        </div>

                        <flux:field>

                            <flux:label>{{ __('Bewerking') }}</flux:label>

                            @include('partials.bulk-operation-select', [
                                'fieldName' => 'issue_date_operation',
                                'options' => $operationOptions,
                            ])

                        </flux:field>

                        {{-- Invoerveld voor uitgiftedatum --}}
                        <div x-show="operation === 'set' || operation === 'fill'" x-cloak>

                            <fieldset x-bind:disabled="operation !== 'set' && operation !== 'fill'">

                                <x-forms.date-picker name="issue_date" :label="__('Nieuwe uitgiftedatum')" :value="old('issue_date')" />

                            </fieldset>

                        </div>

                    </div>

                    {{-- Postzegelserie --}}
                    <div class="grid grid-cols-1 items-start gap-4 rounded-lg border border-theme-accent-3 p-4 md:grid-cols-3"
                        x-data="{
                            operation: @js(old('stamp_series_operation', 'unchanged')),
                            selectedId: @js(old('stamp_series_id')),
                            selectedName: '',
                            series: [],
                            search: '',
                            loading: false,
                            loadError: false,
                            requestNumber: 0,
                            lookupUrl: @js(route('backend.stamp-series.lookup')),
                        
                            get canLoadSeries() {
                                return Number(this.effectiveCountryId) > 0 &&
                                    Number.isInteger(Number(this.effectiveIssueYear)) &&
                                    Number(this.effectiveIssueYear) >= 1840 &&
                                    Number(this.effectiveIssueYear) <= 2200;
                            },
                        
                            get filteredSeries() {
                                const searchTerm = this.search.trim().toLocaleLowerCase();
                        
                                return this.series.filter(item =>
                                    item.name.toLocaleLowerCase().includes(searchTerm)
                                );
                            },
                        
                            init() {
                                this.$watch('effectiveCountryId', () => this.loadSeries());
                                this.$watch('effectiveIssueYear', () => this.loadSeries());
                        
                                if (this.operation === 'set' || this.operation === 'fill') {
                                    this.loadSeries(true);
                                }
                            },
                        
                            async loadSeries(preserveSelection = false) {
                                const requestNumber = ++this.requestNumber;
                                const previousId = preserveSelection ? this.selectedId : null;
                        
                                this.series = [];
                                this.search = '';
                                this.selectedId = null;
                                this.selectedName = '';
                                this.loadError = false;
                                this.loading = false;
                        
                                if (!this.canLoadSeries ||
                                    !['set', 'fill'].includes(this.operation)) {
                                    return;
                                }
                        
                                this.loading = true;
                        
                                try {
                                    const url = new URL(this.lookupUrl);
                        
                                    url.searchParams.set('country_id', this.effectiveCountryId);
                                    url.searchParams.set('issue_year', this.effectiveIssueYear);
                        
                                    const response = await fetch(url, {
                                        headers: {
                                            'Accept': 'application/json'
                                        }
                                    });
                        
                                    if (!response.ok) {
                                        throw new Error('Series lookup failed');
                                    }
                        
                                    const results = await response.json();
                        
                                    if (requestNumber !== this.requestNumber) {
                                        return;
                                    }
                        
                                    this.series = results;
                        
                                    const previousSeries = this.series.find(item =>
                                        String(item.id) === String(previousId)
                                    );
                        
                                    if (previousSeries) {
                                        this.selectedId = previousSeries.id;
                                        this.selectedName = previousSeries.name;
                                    }
                                } catch (error) {
                                    if (requestNumber === this.requestNumber) {
                                        this.loadError = true;
                                    }
                                } finally {
                                    if (requestNumber === this.requestNumber) {
                                        this.loading = false;
                                    }
                                }
                            }
                        }"
                        x-on:bulk-operation-changed="
        if ($event.detail.field === 'stamp_series_operation') {
            operation = $event.detail.operation;
            loadSeries(true);
        }
    ">

                        {{-- Veldnaam --}}
                        <div>
                            <flux:heading>
                                {{ __('Postzegelserie') }}
                            </flux:heading>
                        </div>

                        {{-- Bewerking --}}
                        <flux:field>
                            <flux:label>
                                {{ __('Bewerking') }}
                            </flux:label>

                            @include('partials.bulk-operation-select', [
                                'fieldName' => 'stamp_series_operation',
                                'options' => $operationOptions,
                            ])
                        </flux:field>

                        {{-- Dynamische serieselectie --}}
                        <div x-show="operation === 'set' || operation === 'fill'" x-cloak>

                            <flux:field>

                                <flux:label>
                                    <span class="flex items-center gap-1">
                                        {{ __('Nieuwe postzegelserie') }}
                                        <span class="text-red-600">*</span>
                                    </span>
                                </flux:label>

                                <input type="hidden" name="stamp_series_id" x-model="selectedId"
                                    x-bind:disabled="operation !== 'set' && operation !== 'fill'">

                                {{-- Geen gemeenschappelijk land of uitgiftejaar --}}
                                <div x-show="!canLoadSeries" x-cloak
                                    class="rounded-lg border border-theme-accent-3 bg-brown-5 p-3 text-sm text-zinc-900">

                                    {{ __('Selecteer eerst een gemeenschappelijk land en uitgiftejaar.') }}

                                </div>

                                {{-- Series worden opgehaald --}}
                                <div x-show="loading" x-cloak class="text-sm text-zinc-600">

                                    {{ __('Postzegelseries laden...') }}

                                </div>

                                {{-- Fout bij ophalen --}}
                                <div x-show="loadError" x-cloak class="text-sm text-red-600">

                                    {{ __('De postzegelseries konden niet worden opgehaald.') }}

                                    <button type="button" x-on:click="loadSeries()" class="underline">

                                        {{ __('Opnieuw proberen') }}

                                    </button>

                                </div>

                                {{-- Geen passende series --}}
                                <div x-show="canLoadSeries && !loading && !loadError && series.length === 0" x-cloak
                                    class="rounded-lg border border-theme-accent-3 bg-brown-5 p-3 text-sm text-zinc-900">

                                    {{ __('Geen postzegelseries beschikbaar voor dit land en uitgiftejaar.') }}

                                </div>

                                {{-- Dropdown --}}
                                <div x-show="canLoadSeries && !loading && !loadError && series.length > 0" x-cloak
                                    class="w-full min-w-0 max-w-full">

                                    <flux:dropdown position="bottom" align="start"
                                        class="block w-full min-w-0 max-w-full">

                                        <flux:button type="button" icon-trailing="chevron-down"
                                            icon-trailing:class="shrink-0!"
                                            class="w-full! min-w-0! max-w-full! overflow-hidden justify-between! [&>span]:min-w-0 [&>span]:flex-1 border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                            <span x-text="selectedName || @js(__('Selecteer een postzegelserie'))"
                                                x-bind:title="selectedName" class="block w-full truncate text-left">
                                            </span>

                                        </flux:button>

                                        <flux:menu
                                            class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                            {{-- Zoekveld --}}
                                            <div class="sticky top-0 z-10 bg-brown-6 p-2">

                                                <input type="search" x-model="search" x-on:keydown.stop
                                                    placeholder="{{ __('Zoek een postzegelserie') }}"
                                                    aria-label="{{ __('Zoek een postzegelserie') }}"
                                                    class="block h-9 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 outline-none focus:border-theme-accent-3 focus:ring-1 focus:ring-theme-accent-3">

                                            </div>

                                            {{-- Gefilterde postzegelseries --}}
                                            <template x-for="item in filteredSeries" :key="item.id">

                                                <flux:menu.item
                                                    class="text-zinc-900! hover:bg-brown-4! data-focus:bg-brown-4!"
                                                    x-on:click="
                                                    selectedId = item.id;
                                                    selectedName = item.name;
                                                ">

                                                    <span x-text="item.name"></span>

                                                </flux:menu.item>

                                            </template>

                                            {{-- Geen zoekresultaten --}}
                                            <div x-show="filteredSeries.length === 0"
                                                class="px-3 py-2 text-sm text-zinc-600">

                                                {{ __('Geen postzegelseries gevonden.') }}

                                            </div>

                                        </flux:menu>

                                    </flux:dropdown>

                                </div>

                                <flux:error name="stamp_series_id" />

                            </flux:field>

                        </div>

                    </div>

                    {{-- Nominale waarde --}}
                    <div class="grid grid-cols-1 items-start gap-4 rounded-lg border border-theme-accent-3 p-4 md:grid-cols-3"
                        x-data="{
                            operation: @js(old('face_value_operation', 'unchanged'))
                        }"
                        x-on:bulk-operation-changed="
                            if ($event.detail.field === 'face_value_operation') {
                                operation = $event.detail.operation;
                            }
                        ">

                        {{-- Veldnaam --}}
                        <div>
                            <flux:heading>
                                {{ __('Nominale waarde') }}
                            </flux:heading>
                        </div>

                        {{-- Bewerking --}}
                        <flux:field>
                            <flux:label>
                                {{ __('Bewerking') }}
                            </flux:label>

                            @include('partials.bulk-operation-select', [
                                'fieldName' => 'face_value_operation',
                                'options' => $operationOptions,
                            ])
                        </flux:field>

                        {{-- Invoerveld voor nominale waarde --}}
                        <div x-show="operation === 'set' || operation === 'fill'" x-cloak>

                            <flux:field>

                                <flux:label>
                                    <span class="flex items-center gap-1">
                                        {{ __('Nieuwe nominale waarde') }}
                                        <span class="text-red-600">*</span>
                                    </span>
                                </flux:label>

                                <input type="text" name="face_value" maxlength="50"
                                    value="{{ old('face_value') }}"
                                    placeholder="{{ __('Bijv. 5, 7½, 12½, 1½ of A') }}"
                                    x-bind:disabled="operation !== 'set' && operation !== 'fill'"
                                    class="block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />

                                <flux:error name="face_value" />

                            </flux:field>

                        </div>
                    </div>

                    {{-- Valuta --}}
                    <div class="grid grid-cols-1 items-start gap-4 rounded-lg border border-theme-accent-3 p-4 md:grid-cols-3"
                        x-data="{
                            operation: @js(old('currency_operation', 'unchanged'))
                        }"
                        x-on:bulk-operation-changed="
                        if ($event.detail.field === 'currency_operation') {
                            operation = $event.detail.operation;
                        }
                    ">

                        {{-- Veldnaam --}}
                        <div>
                            <flux:heading>
                                {{ __('Valuta') }}
                            </flux:heading>
                        </div>

                        {{-- Bewerking --}}
                        <flux:field>
                            <flux:label>
                                {{ __('Bewerking') }}
                            </flux:label>

                            @include('partials.bulk-operation-select', [
                                'fieldName' => 'currency_operation',
                                'options' => $operationOptions,
                            ])
                        </flux:field>

                        {{-- Invoerveld voor valuta --}}
                        <div x-show="operation === 'set' || operation === 'fill'" x-cloak>

                            <flux:field>

                                <flux:label>
                                    <span class="flex items-center gap-1">
                                        {{ __('Nieuwe valuta') }}
                                        <span class="text-red-600">*</span>
                                    </span>
                                </flux:label>

                                <input type="text" name="currency" maxlength="3" value="{{ old('currency') }}"
                                    placeholder="{{ __('Bijv. EUR, NLG of USD') }}"
                                    x-bind:disabled="operation !== 'set' && operation !== 'fill'"
                                    class="block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />

                                <flux:error name="currency" />

                            </flux:field>

                        </div>
                    </div>

                    {{-- Ontwerper --}}
                    <div class="grid grid-cols-1 items-start gap-4 rounded-lg border border-theme-accent-3 p-4 md:grid-cols-3"
                        x-data="{
                            operation: @js(old('designer_operation', 'unchanged'))
                        }"
                        x-on:bulk-operation-changed="
                        if ($event.detail.field === 'designer_operation') {
                            operation = $event.detail.operation;
                        }
                    ">

                        {{-- Veldnaam --}}
                        <div>
                            <flux:heading>
                                {{ __('Ontwerper') }}
                            </flux:heading>
                        </div>

                        {{-- Bewerking --}}
                        <flux:field>
                            <flux:label>
                                {{ __('Bewerking') }}
                            </flux:label>

                            @include('partials.bulk-operation-select', [
                                'fieldName' => 'designer_operation',
                                'options' => $operationOptions,
                            ])
                        </flux:field>

                        {{-- Invoerveld voor ontwerper --}}
                        <div x-show="operation === 'set' || operation === 'fill'" x-cloak>

                            <flux:field>

                                <flux:label>
                                    <span class="flex items-center gap-1">
                                        {{ __('Nieuwe ontwerper') }}
                                        <span class="text-red-600">*</span>
                                    </span>
                                </flux:label>

                                <input type="text" name="designer" maxlength="255" value="{{ old('designer') }}"
                                    x-bind:disabled="operation !== 'set' && operation !== 'fill'"
                                    class="block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />

                                <flux:error name="designer" />

                            </flux:field>

                        </div>
                    </div>

                    {{-- Druktechniek --}}
                    <div class="grid grid-cols-1 items-start gap-4 rounded-lg border border-theme-accent-3 p-4 md:grid-cols-3"
                        x-data="{
                            operation: @js(old('printing_method_operation', 'unchanged'))
                        }"
                        x-on:bulk-operation-changed="
                        if ($event.detail.field === 'printing_method_operation') {
                            operation = $event.detail.operation;
                        }
                    ">

                        {{-- Veldnaam --}}
                        <div>
                            <flux:heading>
                                {{ __('Druktechniek') }}
                            </flux:heading>
                        </div>

                        {{-- Bewerking --}}
                        <flux:field>
                            <flux:label>
                                {{ __('Bewerking') }}
                            </flux:label>

                            @include('partials.bulk-operation-select', [
                                'fieldName' => 'printing_method_operation',
                                'options' => $operationOptions,
                            ])
                        </flux:field>

                        {{-- Invoerveld voor druktechniek --}}
                        <div x-show="operation === 'set' || operation === 'fill'" x-cloak>

                            <flux:field>

                                <flux:label>
                                    <span class="flex items-center gap-1">
                                        {{ __('Nieuwe druktechniek') }}
                                        <span class="text-red-600">*</span>
                                    </span>
                                </flux:label>

                                <input type="text" name="printing_method" maxlength="255"
                                    value="{{ old('printing_method') }}"
                                    x-bind:disabled="operation !== 'set' && operation !== 'fill'"
                                    class="block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />

                                <flux:error name="printing_method" />

                            </flux:field>

                        </div>
                    </div>

                    {{-- Notities --}}
                    <div class="grid grid-cols-1 items-start gap-4 rounded-lg border border-theme-accent-3 p-4 md:grid-cols-3"
                        x-data="{
                            operation: @js(old('notes_operation', 'unchanged'))
                        }"
                        x-on:bulk-operation-changed="
                        if ($event.detail.field === 'notes_operation') {
                            operation = $event.detail.operation;
                        }
                    ">

                        {{-- Veldnaam --}}
                        <div>
                            <flux:heading>
                                {{ __('Notities') }}
                            </flux:heading>
                        </div>

                        {{-- Bewerking --}}
                        <flux:field>
                            <flux:label>
                                {{ __('Bewerking') }}
                            </flux:label>

                            @include('partials.bulk-operation-select', [
                                'fieldName' => 'notes_operation',
                                'options' => $operationOptions,
                            ])
                        </flux:field>

                        {{-- Invoerveld voor notities --}}
                        <div x-show="operation === 'set' || operation === 'fill'" x-cloak>

                            <flux:field>

                                <flux:label>
                                    <span class="flex items-center gap-1">
                                        {{ __('Nieuwe notities') }}
                                        <span class="text-red-600">*</span>
                                    </span>
                                </flux:label>

                                <textarea name="notes" rows="4" x-bind:disabled="operation !== 'set' && operation !== 'fill'"
                                    class="block w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 py-2 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30">{{ old('notes') }}</textarea>

                                <flux:error name="notes" />

                            </flux:field>

                        </div>

                    </div>

                </div>

            </div>

            {{-- Thema's --}}
            <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

                <flux:heading size="lg">
                    {{ __("Thema's") }}
                </flux:heading>

                {{-- Themabewerking --}}
                <div class="mt-4 flex flex-col gap-4" x-data="{
                    operation: @js(old('theme_operation', 'unchanged'))
                }"
                    x-on:bulk-operation-changed="
        if ($event.detail.field === 'theme_operation') {
            operation = $event.detail.operation;
        }
    ">

                    <div
                        class="grid grid-cols-1 items-start gap-4 rounded-lg border border-theme-accent-3 p-4 md:grid-cols-3">

                        {{-- Veldnaam --}}
                        <div>
                            <flux:heading>
                                {{ __("Thema's") }}
                            </flux:heading>
                        </div>

                        {{-- Bewerking --}}
                        <flux:field>
                            <flux:label>
                                {{ __('Bewerking') }}
                            </flux:label>

                            @include('partials.bulk-operation-select', [
                                'fieldName' => 'theme_operation',
                                'options' => $themeOperationOptions,
                            ])
                        </flux:field>

                    </div>

                    {{-- Toelichting bij de gekozen themabewerking --}}
                    <div x-show="operation !== 'unchanged'" x-cloak
                        class="rounded-lg border border-theme-accent-3 bg-brown-5 p-3 text-sm text-zinc-900">

                        <p x-show="operation === 'add'">
                            {{ __("De geselecteerde thema's worden toegevoegd. Bestaande thema's blijven behouden.") }}
                        </p>

                        <p x-show="operation === 'remove'">
                            {{ __("Alleen de geselecteerde thema's worden ontkoppeld. Overige thema's blijven behouden.") }}
                        </p>

                        <p x-show="operation === 'replace'">
                            {{ __("Alle bestaande themakoppelingen worden vervangen door de geselecteerde thema's.") }}
                        </p>

                        <p x-show="operation === 'clear'">
                            {{ __('Alle bestaande themakoppelingen worden verwijderd.') }}
                        </p>

                        <p class="mt-2 font-medium">
                            {{ __("De thema's zelf worden niet uit de database verwijderd.") }}
                        </p>

                    </div>

                    {{-- Themaselectie --}}
                    <div x-show="['add', 'remove', 'replace'].includes(operation)" x-cloak x-data="{ search: '' }">

                        <flux:field>

                            <flux:label>
                                <span class="flex items-center gap-1">
                                    {{ __("Selecteer thema's") }}
                                    <span class="text-red-600">*</span>
                                </span>
                            </flux:label>

                            {{-- Zoekveld --}}
                            <div class="mb-3">

                                <input type="search" x-model="search" placeholder="{{ __('Zoek op themanaam') }}"
                                    aria-label="{{ __('Zoek op themanaam') }}"
                                    class="block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />

                            </div>

                            @php
                                $selectedThemeIds = array_map('strval', (array) old('theme_ids', []));
                            @endphp

                            {{-- Beschikbare thema's --}}
                            <fieldset x-bind:disabled="!['add', 'remove', 'replace'].includes(operation)"
                                class="grid max-h-80 grid-cols-1 gap-3 overflow-y-auto rounded-lg border border-theme-accent-3 bg-brown-5 p-4 sm:grid-cols-2 lg:grid-cols-3">

                                @forelse ($themes as $theme)
                                    @php
                                        $translation =
                                            $theme->translations->firstWhere('locale', app()->getLocale()) ??
                                            $theme->translations->firstWhere(
                                                'locale',
                                                config('app.fallback_locale', 'nl'),
                                            );

                                        $themeName = $translation?->name ?? $theme->name;
                                    @endphp

                                    <div
                                        x-show="@js($themeName).toLocaleLowerCase().includes(search.trim().toLocaleLowerCase())">

                                        <flux:checkbox name="theme_ids[]" value="{{ $theme->id }}"
                                            :checked="in_array((string) $theme->id, $selectedThemeIds, true)"
                                            :label="$themeName"
                                            class="[&_[data-flux-checkbox-indicator]]:bg-brown-5! [&_[data-flux-checkbox-indicator]]:border-theme-accent-3!" />

                                    </div>

                                @empty

                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('Geen thema’s gevonden') }}
                                    </p>
                                @endforelse

                            </fieldset>

                            <div class="min-h-5">
                                <flux:error name="theme_ids" />
                                <flux:error name="theme_ids.*" />
                            </div>

                        </flux:field>

                    </div>

                </div>

            </div>

            {{-- Acties --}}
            <div class="flex flex-wrap justify-end gap-3">

                <flux:button type="submit" icon="eye"
                    class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">

                    {{ __('Wijzigingsoverzicht bekijken') }}

                </flux:button>

            </div>

        </form>

    </div>

</x-layouts::app>
