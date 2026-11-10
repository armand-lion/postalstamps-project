<x-layouts::app :title="__('Verzamelde postzegel bewerken')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div
            class="flex flex-col justify-between gap-4 rounded-lg border border-theme-accent-1 bg-brown-6 p-5 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Verzamelde postzegel bewerken') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ $stampCopy->stamp->translationWithFallback()?->name ?? $stampCopy->stamp->name }}
                </flux:text>
            </div>

        </div>

        {{-- Succesmelding --}}
        @if (session('success'))
            <div role="status" class="rounded-lg border border-green-300 bg-green-50 p-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

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

        {{-- Bewerkformulier --}}
        <form method="POST" action="{{ route('backend.stamp-copies.update', $stampCopy) }}" class="flex flex-col gap-6"
            novalidate>

            @csrf
            @method('PUT')

            {{-- Formuliergegevens --}}
            <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

                <flux:heading size="lg">
                    {{ __('Gegevens van de postzegel') }}
                </flux:heading>

                <div
                    class="mt-6 grid grid-cols-1 items-start gap-4 md:grid-cols-[minmax(300px,300px)_minmax(0,180px)_minmax(0,220px)]">

                    {{-- Conditie --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Conditie') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        @php
                            $selectedConditionId = old('condition_id', $stampCopy->condition_id);

                            $selectedCondition = $conditions->firstWhere('id', $selectedConditionId);

                            $selectedConditionName = $selectedCondition
                                ? $selectedCondition->translationWithFallback()?->name ?? $selectedCondition->slug
                                : $stampCopy->condition?->translationWithFallback()?->name ??
                                    ($stampCopy->condition?->slug ?? __('Selecteer een conditie'));
                        @endphp

                        <div x-data="{
                            selectedId: @js((string) $selectedConditionId),
                            selectedName: @js($selectedConditionName)
                        }">

                            <input type="hidden" name="condition_id" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

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
                            quantity: @js((int) old('quantity', $stampCopy->quantity)),

                            increase() {
                                this.quantity = (parseInt(this.quantity) || 0) + 1;
                            },

                            decrease() {
                                this.quantity = Math.max(1, (parseInt(this.quantity) || 1) - 1);
                            }
                        }" class="relative w-full sm:w-40">
                            <input type="number" name="quantity" id="quantity" min="1" step="1"
                                x-model.number="quantity" required
                                class="quantity-input block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 pr-10 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />

                            {{-- Pijltjes binnen het invoerveld --}}
                            <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                {{-- Aantal verhogen --}}
                                <button type="button" x-on:click="increase()"
                                    class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5"
                                    aria-label="{{ __('Aantal verhogen') }}">
                                    <flux:icon.chevron-up class="size-3" />
                                </button>

                                {{-- Aantal verlagen --}}
                                <button type="button" x-on:click="decrease()" :disabled="quantity <= 1"
                                    class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5 disabled:cursor-not-allowed disabled:opacity-40"
                                    aria-label="{{ __('Aantal verlagen') }}">
                                    <flux:icon.chevron-down class="size-3" />
                                </button>

                            </div>
                        </div>

                        <div class="min-h-5">
                            <flux:error name="quantity" />
                        </div>
                    </flux:field>

                    {{-- Aankoopdatum --}}
                    <x-forms.date-picker name="acquisition_date" :label="__('Aankoopdatum')" :value="$stampCopy->acquisition_date?->format('Y-m-d')" />

                </div>

                {{-- Aankoopprijs --}}
                <div class="mt-4 w-[300px]">
                    <flux:field>
                        <flux:label>{{ __('Aankoopprijs') }}</flux:label>

                        <div x-data="{
                            price: @js(old('purchase_price', $stampCopy->purchase_price) !== null && old('purchase_price', $stampCopy->purchase_price) !== '' ? number_format((float) old('purchase_price', $stampCopy->purchase_price), 2, '.', '') : ''),

                            adjust(amount) {
                                const current = parseFloat(this.price) || 0;

                                this.price = Math.max(
                                    0,
                                    Math.round((current + amount) * 100) / 100
                                ).toFixed(2);
                            }
                        }" class="relative w-full sm:w-40">
                            {{-- Aankoopprijs --}}
                            <input type="text" name="purchase_price" id="purchase_price" inputmode="decimal"
                                x-model="price"
                                x-on:blur="
                    if (price !== '' && price !== null) {
                        const value = Number(price);

                        if (Number.isFinite(value) && value >= 0) {
                            price = value.toFixed(2);
                        }
                    }
                "
                                placeholder="0.00"
                                class="quantity-input block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 pr-10 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />

                            {{-- Pijltjes binnen het invoerveld --}}
                            <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                {{-- Aankoopprijs verhogen --}}
                                <button type="button" x-on:click="adjust(0.01)"
                                    class="flex h-4 w-7 items-center justify-center rounded-t text-zinc-700 hover:bg-brown-6!"
                                    aria-label="{{ __('Aankoopprijs verhogen') }}">
                                    <flux:icon.chevron-up class="size-3" />
                                </button>

                                {{-- Aankoopprijs verlagen --}}
                                <button type="button" x-on:click="adjust(-0.01)"
                                    :disabled="price === '' || price === null || (parseFloat(price) || 0) <= 0"
                                    class="flex h-4 w-7 items-center justify-center rounded-b text-zinc-700 hover:bg-brown-6! disabled:cursor-not-allowed disabled:opacity-40"
                                    aria-label="{{ __('Aankoopprijs verlagen') }}">
                                    <flux:icon.chevron-down class="size-3" />
                                </button>

                            </div>
                        </div>

                        <div class="min-h-5">
                            <flux:error name="purchase_price" />
                        </div>
                    </flux:field>
                </div>
                {{-- Aankoopvaluta --}}
                <div class="mt-4 w-[300px]">

                    <flux:field>

                        <flux:label>
                            {{ __('Valuta') }}
                        </flux:label>

                        @php
                            $selectedCurrencyCode = old('purchase_currency', $stampCopy->purchase_currency ?? '');

                            $selectedCurrency = $currencies->firstWhere('code', $selectedCurrencyCode);

                            $selectedCurrencyName = $selectedCurrency
                                ? $selectedCurrency->code .
                                    ' — ' .
                                    ($selectedCurrency->translationWithFallback()?->name ?? $selectedCurrency->code)
                                : __('Selecteer een valuta');
                        @endphp

                        <div x-data="{
                            selectedCode: @js($selectedCurrencyCode),
                            selectedName: @js($selectedCurrencyName)
                        }">

                            <input type="hidden" name="purchase_currency" x-model="selectedCode">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    {{-- Geen valuta --}}
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="
                            selectedCode = '';
                            selectedName = @js(__('Selecteer een valuta'));
                        "
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                        {{ __('Selecteer een valuta') }}

                                    </flux:menu.item>

                                    {{-- Beschikbare valuta --}}
                                    @foreach ($currencies as $currency)
                                        @php
                                            $currencyName =
                                                $currency->code .
                                                ' — ' .
                                                ($currency->translationWithFallback()?->name ?? $currency->code);
                                        @endphp

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="
                                selectedCode = @js($currency->code);
                                selectedName = @js($currencyName);
                            "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ $currencyName }}

                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>

                            </flux:dropdown>

                        </div>

                        <div class="min-h-5">
                            <flux:error name="purchase_currency" />
                        </div>

                    </flux:field>

                </div>

                {{-- Wijze van verkrijgen --}}
                <div class="mt-4 w-[300px]">

                    <flux:field>

                        <flux:label>
                            {{ __('Wijze van verkrijgen') }}
                        </flux:label>

                        @php
                            $selectedAcquisitionMethodSlug = old(
                                'acquisition_method',
                                $stampCopy->acquisition_method ?? '',
                            );

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

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

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

                {{-- Gomconditie --}}
                <div class="mt-4 w-[300px]">

                    <flux:field>

                        <flux:label>
                            {{ __('Gomconditie') }}
                        </flux:label>

                        @php
                            $selectedGumConditionSlug = old('gum_condition', $stampCopy->gum_condition ?? '');

                            $selectedGumCondition = $gumConditions->firstWhere('slug', $selectedGumConditionSlug);

                            $selectedGumConditionName = $selectedGumCondition
                                ? $selectedGumCondition->translationWithFallback()?->name ?? $selectedGumCondition->slug
                                : __('Selecteer een gomconditie');
                        @endphp

                        <div x-data="{
                            selectedSlug: @js($selectedGumConditionSlug),
                            selectedName: @js($selectedGumConditionName)
                        }">

                            <input type="hidden" name="gum_condition" x-model="selectedSlug">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                    <span x-text="selectedName" class="min-w-0 truncate text-left"></span>
                                </flux:button>

                                <flux:menu
                                    class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    {{-- Geen gomconditie --}}
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="
                            selectedSlug = '';
                            selectedName = @js(__('Selecteer een gomconditie'));
                        "
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                        {{ __('Selecteer een gomconditie') }}
                                    </flux:menu.item>

                                    {{-- Beschikbare gomcondities --}}
                                    @foreach ($gumConditions as $gumCondition)
                                        @php
                                            $gumConditionName =
                                                $gumCondition->translationWithFallback()?->name ?? $gumCondition->slug;
                                        @endphp

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="
                                selectedSlug = @js($gumCondition->slug);
                                selectedName = @js($gumConditionName);
                            "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                            {{ $gumConditionName }}
                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>

                            </flux:dropdown>

                        </div>

                        <div class="min-h-5">
                            <flux:error name="gum_condition" />
                        </div>

                    </flux:field>

                </div>

                {{-- Watermerk --}}
                <div class="mt-4 w-[300px]">

                    <flux:field>

                        <flux:label>
                            {{ __('Watermerk') }}
                        </flux:label>

                        @php
                            $selectedWatermarkSlug = old('watermark', $stampCopy->watermark ?? '');

                            $selectedWatermark = $watermarks->firstWhere('slug', $selectedWatermarkSlug);

                            $selectedWatermarkName = $selectedWatermark
                                ? $selectedWatermark->translationWithFallback()?->name ?? $selectedWatermark->slug
                                : __('Selecteer een watermerk');
                        @endphp

                        <div x-data="{
                            selectedSlug: @js($selectedWatermarkSlug),
                            selectedName: @js($selectedWatermarkName)
                        }">

                            <input type="hidden" name="watermark" x-model="selectedSlug">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                                    <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                </flux:button>

                                <flux:menu
                                    class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                    {{-- Geen watermerk geselecteerd --}}
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="
                            selectedSlug = '';
                            selectedName = @js(__('Selecteer een watermerk'));
                        "
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                        {{ __('Selecteer een watermerk') }}

                                    </flux:menu.item>

                                    {{-- Beschikbare watermerken --}}
                                    @foreach ($watermarks as $watermark)
                                        @php
                                            $watermarkName =
                                                $watermark->translationWithFallback()?->name ?? $watermark->slug;
                                        @endphp

                                        <flux:menu.item as="button" type="button"
                                            x-on:click="
                                selectedSlug = @js($watermark->slug);
                                selectedName = @js($watermarkName);
                            "
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                            {{ $watermarkName }}

                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>

                            </flux:dropdown>

                        </div>

                        <div class="min-h-5">
                            <flux:error name="watermark" />
                        </div>

                    </flux:field>

                </div>

                {{-- Tanding --}}
                <div class="mt-4 w-[300px]" x-data="{
                    insertText(text) {
                        const input = this.$refs.perforationInput;
                        const start = input.selectionStart;
                        const end = input.selectionEnd;

                        input.setRangeText(text, start, end, 'end');
                        input.focus();
                    }
                }">

                    <flux:field>

                        <flux:label>
                            {{ __('Tanding') }}
                        </flux:label>

                        <input type="text" name="perforation" id="perforation" x-ref="perforationInput"
                            value="{{ old('perforation', $stampCopy->perforation) }}" placeholder="11½ × 12¼"
                            maxlength="255"
                            class="block h-10 w-full min-w-0 rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />

                        {{-- Invoerknoppen voor tanding --}}
                        <div class="mt-2 flex flex-wrap gap-2">

                            @foreach (['½', '¼', '¾', '×', 'Ongetand'] as $symbol)
                                <button type="button" x-on:click="insertText(@js($symbol))"
                                    class="rounded-lg border border-theme-accent-3! bg-brown-5! px-3 py-1.5 text-sm text-zinc-900! hover:bg-brown-5!">
                                    {{ $symbol === 'Ongetand' ? __('Ongetand') : $symbol }}
                                </button>
                            @endforeach

                        </div>

                        <div class="min-h-5">
                            <flux:error name="perforation" />
                        </div>

                    </flux:field>

                </div>

                {{-- Bijzondere variant / afwijking --}}
                <div class="mt-4" x-data="{
                    isVariant: @js((bool) old('is_variant', $stampCopy->is_variant))
                }">

                    <flux:field>

                        <div x-on:change="isVariant = $event.target.checked">

                            <flux:checkbox name="is_variant" value="1"
                                :checked="(bool) old('is_variant', $stampCopy->is_variant)"
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

                                <flux:textarea name="translations[{{ $locale }}][variant_description]"
                                    rows="3" class="border-theme-accent-3! bg-brown-5!">
                                    {{ old(
                                        "translations.$locale.variant_description",
                                        $stampCopy->translations->firstWhere('locale', $locale)?->variant_description ??
                                            ($locale === 'nl' ? $stampCopy->variant_description : ''),
                                    ) }}
                                </flux:textarea>

                                <div class="min-h-5">
                                    <flux:error name="translations.{{ $locale }}.variant_description" />
                                </div>

                            </flux:field>
                        @endforeach

                    </div>

                </div>

            </div>

            {{-- Actieknoppen --}}
            <div class="flex justify-end gap-3">

                <flux:button :href="route('backend.stamp-copies.show', $stampCopy)" variant="ghost">
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button type="submit" variant="primary" icon="check" class="bg-brown-2!">
                    {{ __('Wijzigingen opslaan') }}
                </flux:button>

            </div>

        </form>

        {{-- Persoonlijke waarderingen --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <flux:heading size="lg" class="text-zinc-900!">
                {{ __('Waarderingen') }}
            </flux:heading>

            <flux:text class="mt-2 text-zinc-700!">
                {{ __('Voeg een persoonlijke waardering toe aan dit postzegelexemplaar.') }}
            </flux:text>

            {{-- Bestaande persoonlijke waarderingen --}}
            @if ($stampCopy->valuations->isNotEmpty())

                <div class="mt-6">

                    <flux:heading size="md" class="text-zinc-900!">
                        {{ __('Bestaande waarderingen') }}
                    </flux:heading>

                    <div class="mt-4 overflow-x-auto rounded-lg border border-theme-accent-3">

                        <table class="w-full text-left text-sm text-zinc-900">

                            <thead class="bg-brown-5">
                                <tr>
                                    <th class="px-4 py-3 font-semibold">
                                        {{ __('Datum') }}
                                    </th>

                                    <th class="px-4 py-3 font-semibold">
                                        {{ __('Waarde') }}
                                    </th>

                                    <th class="px-4 py-3 font-semibold">
                                        {{ __('Bron') }}
                                    </th>

                                    <th class="px-4 py-3 font-semibold">
                                        {{ __('Opmerkingen') }}
                                    </th>

                                    <th class="px-4 py-3 text-right font-semibold">
                                        {{ __('Acties') }}
                                    </th>

                                </tr>
                            </thead>

                            <tbody class="divide-y divide-theme-accent-3">

                                @foreach ($stampCopy->valuations->sortByDesc('valuation_date') as $valuation)
                                    <tr class="bg-brown-6">

                                        <td class="px-4 py-3">
                                            {{ $valuation->valuation_date?->format('d-m-Y') }}
                                        </td>

                                        <td class="px-4 py-3">
                                            {{ number_format((float) $valuation->value, 2, ',', '.') }}
                                            {{ $valuation->currency }}
                                        </td>

                                        <td class="px-4 py-3">
                                            {{ $valuation->source ?: '—' }}
                                        </td>

                                        <td class="px-4 py-3">
                                            {{ $valuation->notes ?: '—' }}
                                        </td>

                                        {{-- Acties --}}
                                        <td class="px-4 py-3 text-right">

                                            <div class="flex items-center justify-end gap-2">

                                                {{-- Bewerken --}}
                                                <flux:modal.trigger :name="'edit-valuation-' . $valuation->id">

                                                    <flux:button type="button" size="sm" icon="pencil"
                                                        class="border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-4!">

                                                        {{ __('Bewerken') }}

                                                    </flux:button>

                                                </flux:modal.trigger>

                                                {{-- Verwijderen --}}
                                                <flux:modal.trigger :name="'delete-valuation-' . $valuation->id">

                                                    <flux:button type="button" size="sm" icon="trash"
                                                        class="border-theme-accent-3! bg-brown-5! text-red-700! hover:bg-brown-4!">

                                                        {{ __('Verwijderen') }}

                                                    </flux:button>

                                                </flux:modal.trigger>

                                            </div>

                                        </td>

                                    </tr>

                                    {{-- Modal: persoonlijke waardering bewerken --}}
                                    <flux:modal :name="'edit-valuation-' . $valuation->id"
                                        class="md:w-[600px] bg-brown-3!">

                                        <div class="flex flex-col gap-4">

                                            <flux:heading size="lg" class="text-zinc-900!">
                                                {{ __('Waardering bewerken') }}
                                            </flux:heading>

                                            <flux:text class="text-zinc-700!">
                                                {{ __('Hier kun je de bestaande waardering aanpassen.') }}
                                            </flux:text>

                                            <form method="POST"
                                                action="{{ route('backend.stamp-copies.valuations.update', [$stampCopy, $valuation]) }}"
                                                class="flex flex-col gap-4">

                                                @csrf
                                                @method('PUT')

                                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                                                    {{-- Waarderingsdatum --}}
                                                    <div>
                                                        <x-forms.date-picker name="valuation_date" :label="__('Waarderingsdatum')"
                                                            :value="$valuation->valuation_date?->format('Y-m-d')" />
                                                    </div>

                                                    {{-- Waarde --}}
                                                    <div class="flex flex-col gap-2">

                                                        <label for="valuation-value-{{ $valuation->id }}"
                                                            class="flex items-center gap-1 text-sm font-medium text-zinc-900">
                                                            {{ __('Waarde') }}
                                                            <span class="text-red-600">*</span>
                                                        </label>

                                                        <div x-data="{
                                                            value: @js($valuation->value),

                                                            adjust(amount) {
                                                                const current = parseFloat(this.value) || 0;

                                                                this.value = Math.max(
                                                                    0,
                                                                    Math.round((current + amount) * 100) / 100
                                                                ).toFixed(2);
                                                            }
                                                        }" class="relative w-full">

                                                            <input type="text"
                                                                id="valuation-value-{{ $valuation->id }}"
                                                                name="value" inputmode="decimal" x-model="value"
                                                                required
                                                                class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3/30">

                                                            <div
                                                                class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                                                <button type="button" x-on:click="adjust(0.01)"
                                                                    aria-label="{{ __('Waarde verhogen') }}"
                                                                    class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                                                    <flux:icon.chevron-up class="size-3" />
                                                                </button>

                                                                <button type="button" x-on:click="adjust(-0.01)"
                                                                    :disabled="(parseFloat(value) || 0) <= 0"
                                                                    aria-label="{{ __('Waarde verlagen') }}"
                                                                    class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5 disabled:cursor-not-allowed disabled:opacity-40">
                                                                    <flux:icon.chevron-down class="size-3" />
                                                                </button>

                                                            </div>

                                                        </div>

                                                        <div class="min-h-5">
                                                            <flux:error name="value" />
                                                        </div>

                                                    </div>

                                                    {{-- Valuta --}}
                                                    <flux:field>
                                                        <flux:label>
                                                            {{ __('Valuta') }}
                                                            <span class="text-red-600">*</span>
                                                        </flux:label>

                                                        <flux:input name="currency" :value="$valuation->currency"
                                                            maxlength="3" required
                                                            class="[&>input]:bg-brown-5! [&>input]:border-theme-accent-3! [&>input]:text-zinc-900!" />
                                                    </flux:field>

                                                    {{-- Bron --}}
                                                    <flux:field>
                                                        <flux:label>{{ __('Bron') }}</flux:label>

                                                        <flux:input name="source" :value="$valuation->source"
                                                            maxlength="150"
                                                            class="[&>input]:bg-brown-5! [&>input]:border-theme-accent-3! [&>input]:text-zinc-900!" />
                                                    </flux:field>

                                                </div>

                                                {{-- Opmerkingen --}}
                                                <flux:field>
                                                    <flux:label>{{ __('Opmerkingen') }}</flux:label>

                                                    <flux:textarea name="notes" rows="3"
                                                        class="border-theme-accent-3! bg-brown-5!">
                                                        {{ $valuation->notes }}</flux:textarea>
                                                </flux:field>

                                                {{-- Acties --}}
                                                <div class="flex justify-end gap-3">

                                                    <flux:modal.close>
                                                        <flux:button type="button" variant="ghost">
                                                            {{ __('Annuleren') }}
                                                        </flux:button>
                                                    </flux:modal.close>

                                                    <flux:button type="submit" variant="primary" icon="check"
                                                        class="bg-brown-2!">
                                                        {{ __('Wijzigingen opslaan') }}
                                                    </flux:button>

                                                </div>

                                            </form>
                                        </div>

                                    </flux:modal>
                                    {{-- Modal: persoonlijke waardering verwijderen --}}
                                    <flux:modal :name="'delete-valuation-' . $valuation->id"
                                        class="md:w-96 bg-brown-3!">

                                        <div class="flex flex-col gap-4">

                                            <flux:heading size="lg" class="text-zinc-900!">
                                                {{ __('Waardering verwijderen') }}
                                            </flux:heading>

                                            <flux:text class="text-zinc-700!">
                                                {{ __('Weet je zeker dat je deze waardering wilt verwijderen? Dit kan niet ongedaan worden gemaakt.') }}
                                            </flux:text>

                                            <div
                                                class="rounded-lg border border-theme-accent-3 bg-brown-5 p-3 text-sm text-zinc-900">

                                                <div>
                                                    <strong>{{ __('Datum') }}:</strong>
                                                    {{ $valuation->valuation_date?->format('d-m-Y') }}
                                                </div>

                                                <div>
                                                    <strong>{{ __('Waarde') }}:</strong>
                                                    {{ number_format((float) $valuation->value, 2, ',', '.') }}
                                                    {{ $valuation->currency }}
                                                </div>

                                            </div>

                                            <div class="flex justify-end gap-3">

                                                <flux:modal.close>
                                                    <flux:button type="button" variant="ghost">
                                                        {{ __('Annuleren') }}
                                                    </flux:button>
                                                </flux:modal.close>

                                                <form method="POST"
                                                    action="{{ route('backend.stamp-copies.valuations.destroy', [$stampCopy, $valuation]) }}">

                                                    @csrf
                                                    @method('DELETE')

                                                    <flux:button type="submit" variant="danger" icon="trash">

                                                        {{ __('Verwijderen') }}

                                                    </flux:button>

                                                </form>

                                            </div>

                                        </div>

                                    </flux:modal>
                                @endforeach

                            </tbody>

                        </table>

                    </div>

                </div>

            @endif

            <form method="POST" action="{{ route('backend.stamp-copies.valuations.store', $stampCopy) }}"
                class="mt-6 flex flex-col gap-4">

                @csrf

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">

                    {{-- Waarderingsdatum --}}
                    <div class="flex flex-col">
                        <x-forms.date-picker name="valuation_date" :label="__('Waarderingsdatum')" :value="old('valuation_date', now()->format('Y-m-d'))" />
                    </div>

                    {{-- Waarde --}}
                    <div class="flex flex-col gap-2">

                        <label for="valuation_value"
                            class="flex items-center gap-1 text-sm font-medium text-zinc-900">
                            {{ __('Waarde') }}
                            <span class="text-red-600">*</span>
                        </label>

                        <div x-data="{
                            value: @js(old('value', '')),

                            adjust(amount) {
                                const current = parseFloat(this.value) || 0;

                                this.value = Math.max(
                                    0,
                                    Math.round((current + amount) * 100) / 100
                                ).toFixed(2);
                            }
                        }" class="relative w-full sm:w-40">

                            <input type="text" name="value" id="valuation_value" inputmode="decimal"
                                x-model="value"
                                x-on:blur="
                if (value !== '' && value !== null) {
                    const number = Number(value);

                    if (Number.isFinite(number) && number >= 0) {
                        value = number.toFixed(2);
                    }
                }
            "
                                placeholder="0.00" required
                                class="quantity-input block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 pr-10 text-sm text-zinc-900! shadow-xs outline-none transition-colors hover:bg-brown-5! focus:border-theme-accent-3! focus:ring-2 focus:ring-theme-accent-3/30" />

                            {{-- Pijltjes binnen het invoerveld --}}
                            <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                {{-- Waarde verhogen --}}
                                {{-- Waarde verhogen --}}
                                <button type="button" x-on:click="adjust(0.01)"
                                    aria-label="{{ __('Waarde verhogen') }}"
                                    class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">

                                    <flux:icon.chevron-up class="size-3" />

                                </button>

                                {{-- Waarde verlagen --}}
                                {{-- Waarde verlagen --}}
                                <button type="button" x-on:click="adjust(-0.01)"
                                    :disabled="value === '' || value === null || (parseFloat(value) || 0) <= 0"
                                    aria-label="{{ __('Waarde verlagen') }}"
                                    class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5 disabled:cursor-not-allowed disabled:opacity-40">

                                    <flux:icon.chevron-down class="size-3" />

                                </button>

                            </div>

                        </div>

                        <div class="min-h-5">
                            <flux:error name="value" />
                        </div>

                    </div>

                    {{-- Valuta --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Valuta') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <input type="text" name="currency" maxlength="3"
                            value="{{ old('currency', $stampCopy->purchase_currency ?? 'EUR') }}" required
                            class="block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! focus:border-theme-accent-3! focus:outline-none focus:ring-2 focus:ring-theme-accent-3/30">

                        <flux:error name="currency" />
                    </flux:field>

                    {{-- Bron --}}
                    <flux:field>
                        <flux:label>{{ __('Bron') }}</flux:label>

                        <input type="text" name="source" maxlength="150" value="{{ old('source') }}"
                            class="block h-10 w-full rounded-lg border border-theme-accent-3! bg-brown-5! px-3 text-sm text-zinc-900! focus:border-theme-accent-3! focus:outline-none focus:ring-2 focus:ring-theme-accent-3/30">

                        <flux:error name="source" />
                    </flux:field>

                </div>

                {{-- Opmerkingen --}}
                <flux:field>
                    <flux:label>{{ __('Opmerkingen') }}</flux:label>

                    <flux:textarea name="notes" rows="3" class="border-theme-accent-3! bg-brown-5!">
                        {{ old('notes') }}
                    </flux:textarea>

                    <flux:error name="notes" />
                </flux:field>

                {{-- Opslaan --}}
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="check" class="bg-brown-2!">
                        {{ __('Waardering toevoegen') }}
                    </flux:button>
                </div>

            </form>

        </div>

        {{-- Eigen afbeeldingen van het postzegelexemplaar --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <flux:heading size="lg">
                {{ __('Afbeeldingen van mijn postzegelexemplaar') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Voeg eigen afbeeldingen toe van de voorzijde, achterzijde of bijzondere details.') }}
            </flux:text>

            <form method="POST" action="{{ route('backend.stamp-copies.images.store', $stampCopy) }}"
                enctype="multipart/form-data" class="mt-6 flex flex-col gap-4">
                @csrf

                {{-- Afbeelding selecteren met live preview --}}
                <flux:field x-data="{
                    preview: null,
                    updatePreview(event) {
                        const file = event.target.files[0];

                        if (this.preview) {
                            URL.revokeObjectURL(this.preview);
                            this.preview = null;
                        }

                        if (file && file.type.startsWith('image/')) {
                            this.preview = URL.createObjectURL(file);
                        }
                    }
                }">
                    <flux:label>
                        <span class="flex items-center gap-1">
                            {{ __('Afbeelding selecteren') }}
                            <span class="text-red-600">*</span>
                        </span>
                    </flux:label>

                    {{-- Bestand selecteren --}}
                    <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp" required
                        x-on:change="updatePreview($event)"
                        class="block w-full rounded-lg border border-theme-accent-3 bg-brown-5 p-2 text-sm text-zinc-900">

                    <flux:error name="image" />

                    {{-- Live preview --}}
                    <div x-show="preview" x-cloak class="mt-4 rounded-lg border border-theme-accent-3 bg-brown-5 p-4">
                        <flux:text class="mb-3 font-medium">
                            {{ __('Voorbeeld afbeelding') }}
                        </flux:text>

                        <div class="flex justify-center">
                            <img x-bind:src="preview"
                                alt="{{ __('Voorbeeld van geselecteerde afbeelding') }}"
                                class="max-h-64 max-w-full rounded-lg border border-theme-accent-3 object-contain">
                        </div>
                    </div>

                </flux:field>

                {{-- Afbeeldingstype --}}
                <flux:field>
                    <flux:label>
                        <span class="flex items-center gap-1">
                            {{ __('Afbeeldingstype') }}
                            <span class="text-red-600">*</span>
                        </span>
                    </flux:label>

                    <select name="image_type" required
                        class="block w-full rounded-lg border border-theme-accent-3 bg-brown-5 p-2 text-sm text-zinc-900 sm:w-80">
                        <option value="front" @selected(old('image_type') === 'front')>
                            {{ __('Voorzijde') }}
                        </option>

                        <option value="back" @selected(old('image_type') === 'back')>
                            {{ __('Achterzijde') }}
                        </option>

                        <option value="detail" @selected(old('image_type') === 'detail')>
                            {{ __('Detail') }}
                        </option>
                    </select>

                    <flux:error name="image_type" />
                </flux:field>

                {{-- Alternatieve tekst --}}
                <flux:field>
                    <flux:label for="alt_text">
                        {{ __('Alternatieve tekst') }}
                    </flux:label>

                    <input type="text" id="alt_text" name="alt_text" value="{{ old('alt_text') }}"
                        maxlength="255"
                        class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 focus:border-theme-accent-3 focus:outline-none focus:ring-1 focus:ring-theme-accent-3">

                    <flux:error name="alt_text" />
                </flux:field>

                {{-- Uploadknop --}}
                <div class="flex justify-end">
                    <flux:button type="submit" variant="primary" icon="arrow-up-tray" class="bg-brown-2!">
                        {{ __('Afbeelding uploaden') }}
                    </flux:button>
                </div>

            </form>

            {{-- Reeds opgeslagen afbeeldingen --}}
            <div class="mt-8 border-t border-theme-accent-3 pt-6">

                <flux:heading size="md">
                    {{ __('Opgeslagen afbeeldingen') }}
                </flux:heading>

                @if ($stampCopy->images->isNotEmpty())

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

                        @foreach ($stampCopy->images as $image)
                            <div class="overflow-hidden rounded-lg border border-theme-accent-3 bg-brown-5">

                                {{-- Afbeelding --}}
                                <div class="flex h-48 items-center justify-center p-3">
                                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->path) }}"
                                        alt="{{ $image->alt_text ?: __('Afbeelding van mijn postzegelexemplaar') }}"
                                        class="max-h-full max-w-full rounded-lg object-contain" loading="lazy">
                                </div>

                                {{-- Afbeeldingsgegevens --}}
                                <div class="border-t border-theme-accent-3 p-3">

                                    <flux:text class="font-medium">
                                        {{ __(
                                            match ($image->image_type) {
                                                'front' => 'Voorzijde',
                                                'back' => 'Achterzijde',
                                                'detail' => 'Detail',
                                                default => 'Afbeelding',
                                            },
                                        ) }}
                                    </flux:text>

                                    @if ($image->alt_text)
                                        <flux:text class="mt-1 text-sm">
                                            {{ $image->alt_text }}
                                        </flux:text>
                                    @endif

                                </div>
                                {{-- Afbeelding bewerken --}}
                                <div x-data="{
                                    preview: null,
                                    updatePreview(event) {
                                        const file = event.target.files[0];

                                        if (this.preview) {
                                            URL.revokeObjectURL(this.preview);
                                            this.preview = null;
                                        }

                                        if (file && file.type.startsWith('image/')) {
                                            this.preview = URL.createObjectURL(file);
                                        }
                                    }
                                }" class="border-t border-theme-accent-3 p-3">
                                    <form method="POST"
                                        action="{{ route('backend.stamp-copies.images.update', [$stampCopy, $image]) }}"
                                        enctype="multipart/form-data" class="flex flex-col gap-4">
                                        @csrf
                                        @method('PUT')

                                        {{-- Afbeeldingstype --}}
                                        <flux:field>
                                            <flux:label>
                                                {{ __('Afbeeldingstype') }}
                                            </flux:label>

                                            <div x-data="{
                                                selectedType: @js($image->image_type),
                                                selectedName: @js(
    __(
        match ($image->image_type) {
            'front' => 'Voorzijde',
            'back' => 'Achterzijde',
            'detail' => 'Detail',
            default => 'Voorzijde',
        },
    ),
)
                                            }">

                                                <input type="hidden" name="image_type" x-model="selectedType">

                                                <flux:dropdown position="bottom" align="start" class="block w-full">

                                                    <flux:button type="button" icon-trailing="chevron-down"
                                                        class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">
                                                        <span x-text="selectedName"
                                                            class="min-w-0 truncate text-left"></span>
                                                    </flux:button>

                                                    <flux:menu class="min-w-48 border-theme-accent-3! bg-brown-6!">

                                                        <flux:menu.item as="button" type="button"
                                                            x-on:click="
                        selectedType = 'front';
                        selectedName = @js(__('Voorzijde'));
                    "
                                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                                            {{ __('Voorzijde') }}
                                                        </flux:menu.item>

                                                        <flux:menu.item as="button" type="button"
                                                            x-on:click="
                        selectedType = 'back';
                        selectedName = @js(__('Achterzijde'));
                    "
                                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                                            {{ __('Achterzijde') }}
                                                        </flux:menu.item>

                                                        <flux:menu.item as="button" type="button"
                                                            x-on:click="
                        selectedType = 'detail';
                        selectedName = @js(__('Detail'));
                    "
                                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                                            {{ __('Detail') }}
                                                        </flux:menu.item>

                                                    </flux:menu>

                                                </flux:dropdown>

                                            </div>

                                            <flux:error name="image_type" />
                                        </flux:field>
                                        {{-- Alternatieve tekst --}}
                                        <flux:field>
                                            <flux:label for="alt_text_{{ $image->id }}">
                                                {{ __('Alternatieve tekst') }}
                                            </flux:label>

                                            <input type="text" id="alt_text_{{ $image->id }}" name="alt_text"
                                                value="{{ $image->alt_text }}" maxlength="255"
                                                class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 focus:border-theme-accent-3 focus:outline-none focus:ring-1 focus:ring-theme-accent-3">

                                            <flux:error name="alt_text" />
                                        </flux:field>

                                        {{-- Afbeelding vervangen --}}
                                        <flux:field>
                                            <flux:label for="image_{{ $image->id }}">
                                                {{ __('Afbeelding vervangen') }}
                                            </flux:label>

                                            <input type="file" id="image_{{ $image->id }}" name="image"
                                                accept=".jpg,.jpeg,.png,.webp" x-on:change="updatePreview($event)"
                                                class="block w-full rounded-lg border border-theme-accent-3 bg-brown-5 p-2 text-sm text-zinc-900">

                                            <flux:error name="image" />
                                        </flux:field>

                                        {{-- Preview van vervangende afbeelding --}}
                                        <div x-show="preview" x-cloak
                                            class="rounded-lg border border-theme-accent-3 bg-brown-6 p-3">
                                            <flux:text class="mb-3">
                                                {{ __('Voorbeeld nieuwe afbeelding') }}
                                            </flux:text>

                                            <div class="flex justify-center">
                                                <img x-bind:src="preview"
                                                    alt="{{ __('Voorbeeld van vervangende afbeelding') }}"
                                                    class="max-h-48 max-w-full rounded-lg object-contain">
                                            </div>
                                        </div>

                                        {{-- Wijzigingen opslaan --}}
                                        <div class="flex justify-end">
                                            <flux:button type="submit" variant="primary" icon="check"
                                                class="bg-brown-2!">
                                                {{ __('Wijzigingen opslaan') }}
                                            </flux:button>
                                        </div>

                                    </form>
                                    {{-- Afbeelding verwijderen met Flux-bevestiging --}}
                                    <div class="flex justify-end">
                                        <flux:modal.trigger :name="'delete-stamp-copy-image-' . $image->id">
                                            <flux:button variant="danger" icon="trash" class="mt-3">
                                                {{ __('Afbeelding verwijderen') }}
                                            </flux:button>
                                        </flux:modal.trigger>
                                    </div>

                                    <flux:modal :name="'delete-stamp-copy-image-' . $image->id" class="md:w-96">
                                        <div class="space-y-6">
                                            <div>
                                                <flux:heading size="lg">
                                                    {{ __('Afbeelding verwijderen') }}
                                                </flux:heading>

                                                <flux:text class="mt-2">
                                                    {{ __('Weet je zeker dat je deze afbeelding wilt verwijderen? Dit kan niet ongedaan worden gemaakt.') }}
                                                </flux:text>
                                            </div>

                                            <div class="flex justify-end gap-3">
                                                <flux:modal.close>
                                                    <flux:button variant="ghost">
                                                        {{ __('Annuleren') }}
                                                    </flux:button>
                                                </flux:modal.close>

                                                <form
                                                    action="{{ route('backend.stamp-copies.images.destroy', [$stampCopy, $image]) }}"
                                                    method="POST">
                                                    @csrf
                                                    @method('DELETE')

                                                    <flux:button type="submit" variant="danger" icon="trash">
                                                        {{ __('Definitief verwijderen') }}
                                                    </flux:button>
                                                </form>
                                            </div>
                                        </div>
                                    </flux:modal>
                                </div>
                            </div>
                        @endforeach

                    </div>
                @else
                    <flux:text class="mt-4">
                        {{ __('Er zijn nog geen afbeeldingen toegevoegd.') }}
                    </flux:text>

                @endif

            </div>

        </div>

    </div>
</x-layouts::app>
