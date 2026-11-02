<x-layouts::app :title="__('Postzegel bewerken')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div
            class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center bg-brown-6 rounded-lg border border-theme-accent-1 p-5">
            <div>
                <flux:heading size="xl">
                    {{ __('Postzegel bewerken') }}: {{ $stamp->name }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Pas de gegevens van deze postzegel aan.') }}
                </flux:text>
            </div>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle" class="mb-6">
                {{ session('success') }}
            </flux:callout>
        @endif

        {{-- Validatiefouten --}}
        @if ($errors->any())
            <div
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Formulier --}}
        <form method="POST" action="{{ route('backend.stamps.update', $stamp) }}"
            class="flex flex-col gap-6 bg-brown-6 rounded-lg border border-theme-accent-1" novalidate>
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-neutral-200 bg-brown-6p-4 dark:border-neutral-700 md:p-6">
                <flux:heading size="lg">
                    {{ __('Algemene gegevens') }}
                </flux:heading>

                <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                    {{-- Land --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Land') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <flux:select name="country_id" required>
                            <option value="">{{ __('Selecteer een land') }}</option>

                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}" @selected((string) old('country_id', $stamp->country_id) === (string) $country->id)>
                                    {{ $country->name }}
                                </option>
                            @endforeach
                        </flux:select>
                        <div class="min-h-5">
                            <flux:error name="country_id" />
                        </div>
                    </flux:field>

                    {{-- Postzegelserie --}}
                    <flux:field>
                        <flux:label>{{ __('Postzegelserie') }}</flux:label>

                        <flux:select name="stamp_series_id">
                            <option value="">{{ __('Geen serie') }}</option>

                            @foreach ($series as $item)
                                <option value="{{ $item->id }}" data-country-id="{{ $item->country_id }}"
                                    @selected((string) old('stamp_series_id', $stamp->stamp_series_id) === (string) $item->id)>
                                    {{ $item->name }}
                                </option>
                            @endforeach
                        </flux:select>

                        <div class="min-h-5">
                            <flux:error name="stamp_series_id" />
                        </div>
                    </flux:field>

                    {{-- Thema's --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>{{ __("Thema's") }}</flux:label>

                        @php
                            $selectedThemeIds = array_map(
                                'strval',
                                old('theme_ids', $stamp->themes->pluck('id')->all()),
                            );
                        @endphp

                        <div
                            class="grid grid-cols-1 gap-3 rounded-lg border border-zinc-200 p-4 sm:grid-cols-2 lg:grid-cols-3 dark:border-zinc-700">
                            @forelse ($themes as $theme)
                                @php
                                    $translation = $theme->translationWithFallback();
                                    $themeName = $translation?->name ?? $theme->name;
                                @endphp

                                <label class="flex cursor-pointer items-center gap-2">
                                    <input type="checkbox" name="theme_ids[]" value="{{ $theme->id }}"
                                        @checked(in_array((string) $theme->id, $selectedThemeIds, true))
                                        class="rounded border-zinc-300 text-blue-600 focus:ring-blue-500 dark:border-zinc-600">

                                    <span class="text-sm text-zinc-700 dark:text-zinc-300">
                                        {{ $themeName }}
                                    </span>
                                </label>
                            @empty
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Geen thema’s gevonden') }}
                                </p>
                            @endforelse
                        </div>

                        <div class="min-h-5">
                            <flux:error name="theme_ids" />
                            <flux:error name="theme_ids.*" />
                        </div>
                    </flux:field>

                    {{-- Uitgiftejaar --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Uitgiftejaar') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <flux:input type="number" name="issue_year" min="1840" max="2200"
                            value="{{ old('issue_year', $stamp->issue_year) }}" />
                        <div class="min-h-5">
                            <flux:error name="issue_year" />
                        </div>
                    </flux:field>

                    {{-- Uitgiftedatum --}}
                    <flux:field>
                        <flux:label>{{ __('Uitgiftedatum') }}</flux:label>

                        <flux:input type="date" name="issue_date"
                            value="{{ old('issue_date', $stamp->issue_date?->format('Y-m-d')) }}" />
                        <div class="min-h-5">
                            <flux:error name="issue_date" />
                        </div>
                    </flux:field>

                    {{-- Nominale waarde --}}
                    <flux:field>
                        <flux:label>{{ __('Nominale waarde') }}</flux:label>

                        <flux:input type="number" name="face_value" min="0" step="0.01"
                            value="{{ old('face_value', $stamp->face_value) }}" />

                        <flux:error name="face_value" />

                    </flux:field>

                    {{-- Valuta --}}
                    <flux:field>
                        <flux:label>{{ __('Valuta') }}</flux:label>

                        <flux:input name="currency" maxlength="3" value="{{ old('currency', $stamp->currency) }}"
                            placeholder="EUR" />

                        <flux:error name="currency" />
                    </flux:field>

                    {{-- Ontwerper --}}
                    <flux:field>
                        <flux:label>{{ __('Ontwerper') }}</flux:label>

                        <flux:input name="designer" value="{{ old('designer', $stamp->designer) }}" />

                        <flux:error name="designer" />
                    </flux:field>

                    {{-- Druktechniek --}}
                    <flux:field>
                        <flux:label>{{ __('Druktechniek') }}</flux:label>

                        <flux:input name="printing_method"
                            value="{{ old('printing_method', $stamp->printing_method) }}" />

                        <flux:error name="printing_method" />
                    </flux:field>

                    {{-- Hoofdpostzegel (optioneel, voor varianten) --}}
                    <flux:field>
                        <flux:label>{{ __('Hoofdpostzegel') }}</flux:label>

                        <flux:select name="parent_stamp_id" id="parent_stamp_id">
                            <option value="">
                                {{ __('Geen hoofdpostzegel (zelfstandige postzegel)') }}
                            </option>

                            @foreach ($parentStamps as $parentStamp)
                                <option value="{{ $parentStamp->id }}"
                                    data-country-id="{{ $parentStamp->country_id }}" @selected((string) old('parent_stamp_id', $stamp->parent_stamp_id) === (string) $parentStamp->id)>
                                    {{ $parentStamp->translationWithFallback()?->name ?? $parentStamp->name }}
                                    ({{ $parentStamp->issue_year }})
                                </option>
                            @endforeach
                        </flux:select>

                        <div class="min-h-5">
                            <flux:error name="parent_stamp_id" />
                        </div>
                    </flux:field>

                    {{-- Catalogusreferentie --}}
                    <flux:field>
                        <flux:label>{{ __('Catalogusreferentie') }}</flux:label>

                        <flux:input name="catalog_reference"
                            value="{{ old('catalog_reference', $stamp->catalog_reference) }}" />

                        <flux:error name="catalog_reference" />
                    </flux:field>

                    @php
                        // Koppel bestaande catalogusvermeldingen aan hun cataloguseditie.
                        $catalogEntriesByEdition = $stamp->catalogEntries->keyBy('catalog_edition_id');
                    @endphp

                    {{-- Bestaande cataloguskoppelingen --}}
                    @if ($catalogEditions->isNotEmpty())
                        <div class="md:col-span-2 rounded-lg border border-theme-accent-1 bg-brown-6 p-4">

                            <flux:heading size="sm">
                                {{ __('Bestaande catalogusnummers en cataloguswaarden') }}
                            </flux:heading>

                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full text-sm">
                                    <thead>
                                        <tr class="border-b border-theme-accent-1 text-left">
                                            <th class="px-3 py-2">{{ __('Catalogus') }}</th>
                                            <th class="px-3 py-2">{{ __('Cataloguseditie') }}</th>
                                            <th class="px-3 py-2">{{ __('Catalogusnummer') }}</th>
                                            <th class="px-3 py-2">{{ __('Postfris') }}</th>
                                            <th class="px-3 py-2">{{ __('Gestempeld') }}</th>
                                            <th class="px-3 py-2">{{ __('Status') }}</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($catalogEditions as $edition)
                                            @php
                                                // Zoek de bestaande catalogusvermelding voor deze editie.
                                                $entry = $catalogEntriesByEdition->get($edition->id);

                                                // Zoek de bestaande cataloguswaarden, indien aanwezig.
                                                $postfris = $entry?->values->first(
                                                    fn($value) => $value->condition?->slug === 'postfris',
                                                );

                                                $gestempeld = $entry?->values->first(
                                                    fn($value) => $value->condition?->slug === 'gestempeld',
                                                );

                                                // Bepaal de status van deze cataloguseditie.
                                                if ($entry === null) {
                                                    $catalogStatus = 'not_linked';
                                                } elseif ($postfris && $gestempeld) {
                                                    $catalogStatus = 'complete';
                                                } elseif ($postfris) {
                                                    $catalogStatus = 'missing_used';
                                                } elseif ($gestempeld) {
                                                    $catalogStatus = 'missing_mint';
                                                } else {
                                                    $catalogStatus = 'missing_both';
                                                }
                                            @endphp

                                            <tr class="border-b border-theme-accent-1/30">
                                                <td class="px-3 py-2">
                                                    {{ $edition->catalog?->abbreviation ?: $edition->catalog?->name }}
                                                </td>

                                                <td class="px-3 py-2">
                                                    {{ $edition->edition_name }}
                                                </td>

                                                <td class="px-3 py-2">
                                                    <input type="text"
                                                        name="catalog_editions[{{ $edition->id }}][catalog_number]"
                                                        value="{{ old("catalog_editions.{$edition->id}.catalog_number", $entry?->catalog_number) }}"
                                                        class="block h-10 w-32 rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 focus:border-theme-accent-3 focus:outline-none focus:ring-1 focus:ring-theme-accent-3"
                                                        aria-label="{{ __('Catalogusnummer') }}">
                                                </td>

                                                <td class="px-3 py-2">
                                                    <div x-data="{
                                                        value: (() => {
                                                            const initial = @js(old("catalog_editions.{$edition->id}.values.postfris", $postfris?->catalog_value));
                                                            return initial === null || initial === '' ? '' : Number(initial).toFixed(2);
                                                        })(),
                                                        increase() {
                                                            const cents = Math.round(Number(this.value || 0) * 100);
                                                            this.value = ((cents + 1) / 100).toFixed(2);
                                                        },
                                                        decrease() {
                                                            const cents = Math.round(Number(this.value || 0) * 100);
                                                            this.value = (Math.max(0, cents - 1) / 100).toFixed(2);
                                                        }
                                                    }" class="relative w-32">
                                                        <input type="text"
                                                            name="catalog_editions[{{ $edition->id }}][values][postfris]"
                                                            x-model="value" min="0" step="0.01"
                                                            inputmode="decimal" aria-label="{{ __('Postfris') }}"
                                                            class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 focus:border-theme-accent-3 focus:outline-none focus:ring-1 focus:ring-theme-accent-3 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]">

                                                        <div
                                                            class="absolute inset-y-0 right-1 flex flex-col justify-center">
                                                            <button type="button" x-on:click="increase()"
                                                                aria-label="{{ __('Cataloguswaarde verhogen') }}"
                                                                class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                                                <flux:icon.chevron-up class="size-3" />
                                                            </button>

                                                            <button type="button" x-on:click="decrease()"
                                                                aria-label="{{ __('Cataloguswaarde verlagen') }}"
                                                                class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                                                <flux:icon.chevron-down class="size-3" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td class="px-3 py-2">
                                                <div x-data="{
                                                    value: (() => {
                                                        const initial = @js(old("catalog_editions.{$edition->id}.values.gestempeld", $gestempeld?->catalog_value));
                                                        return initial === null || initial === '' ? '' : Number(initial).toFixed(2);
                                                    })(),
                                                    increase() {
                                                        const cents = Math.round(Number(this.value || 0) * 100);
                                                        this.value = ((cents + 1) / 100).toFixed(2);
                                                    },
                                                    decrease() {
                                                        const cents = Math.round(Number(this.value || 0) * 100);
                                                        this.value = (Math.max(0, cents - 1) / 100).toFixed(2);
                                                    }
                                                }" class="relative w-32">
                                                    <input type="text"
                                                        name="catalog_editions[{{ $edition->id }}][values][gestempeld]"
                                                        x-model="value" min="0" step="0.01"
                                                        inputmode="decimal" aria-label="{{ __('Gestempeld') }}"
                                                        class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 focus:border-theme-accent-3 focus:outline-none focus:ring-1 focus:ring-theme-accent-3 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]">

                                                    <div
                                                        class="absolute inset-y-0 right-1 flex flex-col justify-center">
                                                        <button type="button" x-on:click="increase()"
                                                            aria-label="{{ __('Cataloguswaarde verhogen') }}"
                                                            class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                                            <flux:icon.chevron-up class="size-3" />
                                                        </button>

                                                        <button type="button" x-on:click="decrease()"
                                                            aria-label="{{ __('Cataloguswaarde verlagen') }}"
                                                            class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                                            <flux:icon.chevron-down class="size-3" />
                                                        </button>
                                                    </div>
                                                </div>
                                                </td>

                                                {{-- Status van de cataloguswaarden --}}
                                                <td class="px-3 py-2">
                                                    @if ($catalogStatus === 'not_linked')
                                                        <span class="font-medium text-zinc-500 dark:text-zinc-400">
                                                            {{ __('Niet gekoppeld') }}
                                                        </span>
                                                    @elseif ($catalogStatus === 'complete')
                                                        <span class="font-medium text-green-600 dark:text-green-400">
                                                            {{ __('Compleet') }}
                                                        </span>
                                                    @elseif ($catalogStatus === 'missing_used')
                                                        <span class="font-medium text-amber-600 dark:text-amber-400">
                                                            {{ __('Gestempeld ontbreekt') }}
                                                        </span>
                                                    @elseif ($catalogStatus === 'missing_mint')
                                                        <span class="font-medium text-amber-600 dark:text-amber-400">
                                                            {{ __('Postfris ontbreekt') }}
                                                        </span>
                                                    @else
                                                        <span class="font-medium text-red-600 dark:text-red-400">
                                                            {{ __('Beide ontbreken') }}
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                        </div>
                    @endif


                    {{-- Notities --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>{{ __('Notities') }}</flux:label>

                        <flux:textarea name="notes" rows="4">{{ old('notes', $stamp->notes) }}
                        </flux:textarea>

                        <flux:error name="notes" />
                    </flux:field>

                </div>
            </div>

            {{-- Vertalingen --}}
            <section class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">

                <flux:heading size="lg">
                    {{ __('Vertalingen') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Beheer de naam en beschrijving van de postzegel per taal.') }}
                </flux:text>

                @php
                    $languageNames = [
                        'nl' => 'Nederlands',
                        'en' => 'English',
                        'de' => 'Deutsch',
                        'fr' => 'Français',
                        'es' => 'Español',
                    ];
                @endphp

                <div class="mt-6 space-y-6">

                    @foreach ($locales as $locale)
                        @php
                            $translation = $stamp->translations->firstWhere('locale', $locale);

                            $name = $translation?->name ?? ($locale === app()->getLocale() ? $stamp->name : '');

                            $description =
                                $translation?->description ??
                                ($locale === app()->getLocale() ? $stamp->description : '');
                        @endphp

                        <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">

                            <flux:heading size="md" class="mb-4">
                                {{ $languageNames[$locale] }}
                            </flux:heading>

                            <div class="grid grid-cols-1 gap-4">

                                <flux:field>
                                    <flux:label>
                                        <span class="flex items-center gap-1">
                                            {{ __('Naam') }}


                                            @if ($locale === app()->getLocale())
                                                <span class="text-red-600">*</span>
                                            @endif
                                            <span>
                                    </flux:label>

                                    <flux:input name="translations[{{ $locale }}][name]"
                                        :value="old('translations.'.$locale.
                                            '.name', $name)"
                                        maxlength="255" @required($locale === app()->getLocale()) />

                                    <flux:error name="translations.{{ $locale }}.name" />
                                </flux:field>

                                <flux:field>
                                    <flux:label>{{ __('Beschrijving') }}</flux:label>

                                    <flux:textarea name="translations[{{ $locale }}][description]"
                                        rows="3">
                                        {{ old('translations.' . $locale . '.description', $description) }}
                                    </flux:textarea>

                                    <flux:error name="translations.{{ $locale }}.description" />
                                </flux:field>

                            </div>
                        </div>
                    @endforeach

                </div>
            </section>

            {{-- Acties --}}
            <div class="flex flex-wrap justify-end gap-3">
                <flux:button :href="route('backend.stamps.show', $stamp)" variant="ghost" icon="x-mark">
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Wijzigingen opslaan') }}
                </flux:button>
            </div>

        </form>

        {{-- Postzegelafbeeldingen --}}
        <section class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
            <flux:heading size="lg">
                {{ __('Postzegelafbeeldingen beheren') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Voeg afbeeldingen toe of beheer de bestaande afbeeldingen van deze postzegel.') }}
            </flux:text>

            {{-- Reeds opgeslagen afbeeldingen --}}
            @if ($stamp->images->isNotEmpty())
                <div class="mt-6">
                    <flux:heading size="md">
                        {{ __('Bestaande afbeeldingen') }}
                    </flux:heading>

                    <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($stamp->images as $image)
                            <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                                <img src="{{ asset('storage/' . $image->path) }}"
                                    alt="{{ $image->alt_text ?: __('Postzegelafbeelding') }}"
                                    class="h-48 w-full rounded-lg object-contain" />

                                <div class="mt-3">
                                    <span class="inline-flex rounded-md bg-brown-6 px-3 py-1 text-sm font-medium">
                                        @switch($image->image_type)
                                            @case('front')
                                                {{ __('Voorzijde') }}
                                            @break

                                            @case('back')
                                                {{ __('Achterzijde') }}
                                            @break

                                            @case('detail')
                                                {{ __('Detail') }}
                                            @break

                                            @default
                                                {{ $image->image_type }}
                                        @endswitch
                                    </span>
                                </div>
                                <flux:text class="mt-3 text-sm">
                                    {{ $image->alt_text ?: __('Geen beschrijving') }}
                                </flux:text>

                                {{-- Afbeeldingsgegevens wijzigen --}}
                                <form action="{{ route('backend.stamps.images.update', [$stamp, $image]) }}"
                                    method="POST" enctype="multipart/form-data" class="mt-4 space-y-3">
                                    @csrf
                                    @method('PATCH')

                                    <flux:field>
                                        <flux:label>{{ __('Afbeeldingstype wijzigen') }}</flux:label>

                                        <flux:select name="image_type" required>
                                            <flux:select.option value="front"
                                                :selected="$image->image_type === 'front'">
                                                {{ __('Voorzijde') }}
                                            </flux:select.option>

                                            <flux:select.option value="back"
                                                :selected="$image->image_type === 'back'">
                                                {{ __('Achterzijde') }}
                                            </flux:select.option>

                                            <flux:select.option value="detail"
                                                :selected="$image->image_type === 'detail'">
                                                {{ __('Detail') }}
                                            </flux:select.option>
                                        </flux:select>
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>{{ __('Afbeeldingsbeschrijving wijzigen') }}</flux:label>

                                        <flux:input name="alt_text" :value="$image->alt_text" maxlength="255" />
                                    </flux:field>

                                    <flux:field>
                                        <flux:label>
                                            {{ __('Afbeelding vervangen (optioneel)') }}
                                        </flux:label>

                                        <input type="file" name="image" accept=".jpg,.jpeg,.png,.webp"
                                            data-stamp-replacement-input
                                            data-preview-target="stamp-replacement-preview-{{ $image->id }}"
                                            class="block w-full rounded-lg border border-neutral-300 p-2 text-sm dark:border-neutral-600" />

                                        {{-- Preview van de vervangende afbeelding --}}
                                        <div id="stamp-replacement-preview-{{ $image->id }}"
                                            class="mt-4 hidden rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                                            <flux:text class="mb-3">
                                                {{ __('Voorbeeld van de nieuwe afbeelding') }}
                                            </flux:text>

                                            <img alt="{{ __('Voorbeeld van de nieuwe afbeelding') }}"
                                                class="max-h-64 max-w-full rounded-lg object-contain" />
                                        </div>

                                        <flux:description>
                                            {{ __('Laat dit veld leeg om de bestaande afbeelding te behouden.') }}
                                        </flux:description>
                                    </flux:field>

                                    <flux:button type="submit" variant="primary" icon="check">
                                        {{ __('Wijzigingen opslaan') }}
                                    </flux:button>
                                </form>


                                {{-- Afbeelding verwijderen met Flux-bevestiging --}}
                                <flux:modal.trigger :name="'delete-stamp-image-' . $image->id">
                                    <flux:button variant="danger" icon="trash" class="mt-3">
                                        {{ __('Afbeelding verwijderen') }}
                                    </flux:button>
                                </flux:modal.trigger>

                                <flux:modal :name="'delete-stamp-image-' . $image->id" class="md:w-96">
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
                                                action="{{ route('backend.stamps.images.destroy', [$stamp, $image]) }}"
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
                        @endforeach
                    </div>
                </div>
            @else
                <flux:text class="mt-4">
                    {{ __('Er zijn nog geen afbeeldingen aan deze postzegel gekoppeld.') }}
                </flux:text>
            @endif

            <form action="{{ route('backend.stamps.images.store', $stamp) }}" method="POST"
                enctype="multipart/form-data" class="mt-6 space-y-4">
                @csrf

                <flux:field>
                    <flux:label>{{ __('Afbeelding') }}</flux:label>

                    <input type="file" id="stamp-image-input" name="image" accept=".jpg,.jpeg,.png,.webp"
                        required
                        class="block w-full rounded-lg border border-neutral-300 p-2 text-sm dark:border-neutral-600" />

                    {{-- Preview van de geselecteerde afbeelding --}}
                    <div id="stamp-image-preview-container"
                        class="mt-4 hidden rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                        <flux:text class="mb-3">
                            {{ __('Voorbeeld van de geselecteerde afbeelding') }}
                        </flux:text>

                        <img id="stamp-image-preview" src=""
                            alt="{{ __('Voorbeeld van de geselecteerde afbeelding') }}"
                            class="max-h-64 max-w-full rounded-lg object-contain" />
                    </div>

                    <flux:error name="image" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('Afbeeldingstype') }}</flux:label>

                    <flux:select name="image_type" required>
                        <flux:select.option value="front" :selected="old('image_type', 'front') === 'front'">
                            {{ __('Voorzijde') }}
                        </flux:select.option>

                        <flux:select.option value="back" :selected="old('image_type') === 'back'">
                            {{ __('Achterzijde') }}
                        </flux:select.option>

                        <flux:select.option value="detail" :selected="old('image_type') === 'detail'">
                            {{ __('Detail') }}
                        </flux:select.option>
                    </flux:select>

                    <flux:error name="image_type" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Afbeeldingsbeschrijving') }}</flux:label>

                    <flux:input name="alt_text" :value="old('alt_text')"
                        :placeholder="__('Beschrijf wat er op de afbeelding te zien is')" maxlength="255" />

                    <flux:error name="alt_text" />
                </flux:field>

                <flux:button type="submit" variant="primary" icon="arrow-up-tray">
                    {{ __('Afbeelding uploaden') }}
                </flux:button>
            </form>
        </section>

    </div>


    <script>
        const countrySelect = document.querySelector('select[name="country_id"]');
        const seriesSelect = document.querySelector('select[name="stamp_series_id"]');
        const parentStampSelect = document.querySelector('select[name="parent_stamp_id"]');

        function filterParentStamps() {
            const selectedCountryId = countrySelect.value;
            const selectedParentStampId = parentStampSelect.value;

            Array.from(parentStampSelect.options).forEach(option => {
                if (option.value === '') {
                    option.hidden = false;
                    return;
                }

                option.hidden = !selectedCountryId ||
                    option.dataset.countryId !== selectedCountryId;
            });

            const selectedOption = Array.from(parentStampSelect.options).find(
                option => option.value === selectedParentStampId
            );

            if (
                selectedOption &&
                selectedOption.value !== '' &&
                selectedOption.hidden
            ) {
                parentStampSelect.value = '';
            }
        }

        function filterStampSeries() {
            const selectedCountryId = countrySelect.value;

            Array.from(seriesSelect.options).forEach(option => {
                if (option.value === '') {
                    option.hidden = false;
                    return;
                }

                option.hidden = option.dataset.countryId !== selectedCountryId;
            });

            const selectedOption = seriesSelect.selectedOptions[0];

            if (
                selectedOption &&
                selectedOption.value !== '' &&
                selectedOption.dataset.countryId !== selectedCountryId
            ) {
                seriesSelect.value = '';
            }
        }

        countrySelect.addEventListener('change', filterStampSeries);
        countrySelect.addEventListener('change', filterParentStamps);

        filterStampSeries();
        filterParentStamps();

        // Preview van de geselecteerde postzegelafbeelding
        const stampImageInput = document.getElementById('stamp-image-input');
        const stampImagePreview = document.getElementById('stamp-image-preview');
        const stampImagePreviewContainer = document.getElementById(
            'stamp-image-preview-container'
        );

        let stampImagePreviewUrl = null;

        stampImageInput.addEventListener('change', function() {
            // Vorige preview opruimen
            if (stampImagePreviewUrl !== null) {
                URL.revokeObjectURL(stampImagePreviewUrl);
                stampImagePreviewUrl = null;
            }

            const file = this.files[0];

            if (!file || !file.type.startsWith('image/')) {
                stampImagePreview.removeAttribute('src');
                stampImagePreviewContainer.classList.add('hidden');
                return;
            }

            // Tijdelijke URL voor de geselecteerde afbeelding maken
            stampImagePreviewUrl = URL.createObjectURL(file);

            stampImagePreview.src = stampImagePreviewUrl;
            stampImagePreviewContainer.classList.remove('hidden');
        });

        // Live preview bij het vervangen van bestaande afbeeldingen.
        document.querySelectorAll('[data-stamp-replacement-input]').forEach((input) => {
            const previewId = input.dataset.previewTarget;
            const previewContainer = document.getElementById(previewId);
            const previewImage = previewContainer.querySelector('img');

            let previewUrl = null;

            input.addEventListener('change', function() {
                // Vorige tijdelijke preview opruimen.
                if (previewUrl !== null) {
                    URL.revokeObjectURL(previewUrl);
                    previewUrl = null;
                }

                const file = this.files[0];

                // Geen geldig afbeeldingsbestand geselecteerd.
                if (!file || !file.type.startsWith('image/')) {
                    previewImage.removeAttribute('src');
                    previewContainer.classList.add('hidden');
                    return;
                }

                // Nieuwe afbeelding tonen zonder deze al te uploaden.
                previewUrl = URL.createObjectURL(file);

                previewImage.src = previewUrl;
                previewContainer.classList.remove('hidden');
            });
        });
    </script>
</x-layouts::app>
