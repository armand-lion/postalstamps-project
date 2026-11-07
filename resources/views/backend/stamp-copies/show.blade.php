<x-layouts::app :title="__('Verzamelde postzegel bekijken')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

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

        {{-- Paginatitel en actieknoppen --}}
        <div
            class="flex flex-col justify-between gap-4 rounded-lg border border-theme-accent-1 bg-brown-6 p-5 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ $stampCopy->stamp->translationWithFallback()?->name ?? $stampCopy->stamp->name }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Details van deze postzegel in je persoonlijke verzameling.') }}
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-3">

                <flux:button :href="route('backend.stamp-copies.index')" variant="ghost" icon="arrow-left">
                    {{ __('Terug naar overzicht') }}
                </flux:button>

                <flux:button :href="route('backend.stamp-copies.edit', $stampCopy)" variant="primary" icon="pencil"
                    class="bg-brown-2!">
                    {{ __('Bewerken') }}
                </flux:button>

            </div>

        </div>

        {{-- Algemene gegevens --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <flux:heading size="lg">
                {{ __('Algemene gegevens') }}
            </flux:heading>

            <dl class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Naam') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->stamp->translationWithFallback()?->name ?? $stampCopy->stamp->name }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Land') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->stamp->country?->translationWithFallback()?->name ?? ($stampCopy->stamp->country?->name ?? '—') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Uitgiftejaar') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->stamp->issue_year ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Catalogusreferentie') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->stamp->catalog_reference ?: '—' }}
                    </dd>
                </div>

            </dl>

        </div>

        {{-- Verzamelgegevens --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <flux:heading size="lg">
                {{ __('Verzamelgegevens') }}
            </flux:heading>

            <dl class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                {{-- Conditie --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Conditie') }}
                    </dt>

                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->condition?->translationWithFallback()?->name ?? ($stampCopy->condition?->slug ?? '—') }}
                    </dd>
                </div>

                {{-- Aantal --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Aantal') }}
                    </dt>

                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->quantity }}
                    </dd>
                </div>

                {{-- Aankoopdatum --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Aankoopdatum') }}
                    </dt>

                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->acquisition_date?->format('d-m-Y') ?? '—' }}
                    </dd>
                </div>

                {{-- Aankoopprijs --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Aankoopprijs') }}
                    </dt>

                    <dd class="mt-1 font-medium text-zinc-900">
                        @if ($stampCopy->purchase_price !== null)
                            {{ number_format((float) $stampCopy->purchase_price, 2, ',', '.') }}
                            {{ $stampCopy->purchase_currency ?? '' }}
                        @else
                            —
                        @endif
                    </dd>
                </div>

            </dl>

        </div>

        {{-- Cataloguswaardering --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <flux:heading size="lg" class="text-zinc-900!">
                {{ __('Cataloguswaardering') }}
            </flux:heading>

            <flux:text class="mt-2 text-zinc-700!">
                {{ __('Cataloguswaarden voor de conditie van dit exemplaar.') }}
            </flux:text>

            @if ($stampCopy->stamp->catalogEntries->isNotEmpty())

                <div class="mt-6 overflow-x-auto">
                    <table class="w-full text-sm">

                        <thead class="bg-brown-5">
                            <tr class="border-b border-theme-accent-3 text-left text-zinc-900">
                                <th class="px-3 py-2 font-semibold">
                                    {{ __('Catalogus') }}
                                </th>

                                <th class="px-3 py-2 font-semibold">
                                    {{ __('Editie') }}
                                </th>

                                <th class="px-3 py-2 font-semibold">
                                    {{ __('Waarde per stuk') }}
                                </th>

                                <th class="px-3 py-2 font-semibold">
                                    {{ __('Aantal') }}
                                </th>

                                <th class="px-3 py-2 font-semibold">
                                    {{ __('Totale waarde') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($stampCopy->stamp->catalogEntries as $entry)
                                @php
                                    $catalogValue = $entry->values->firstWhere(
                                        'condition_id',
                                        $stampCopy->condition_id,
                                    );

                                    $totalValue =
                                        $catalogValue !== null
                                            ? (float) $catalogValue->catalog_value * $stampCopy->quantity
                                            : null;
                                @endphp

                                <tr class="border-b border-theme-accent-1 bg-brown-6 text-zinc-900 hover:bg-brown-5">

                                    <td class="px-3 py-2">
                                        {{ $entry->catalogEdition?->catalog?->name ?? '—' }}
                                    </td>

                                    <td class="px-3 py-2">
                                        {{ $entry->catalogEdition?->edition_year ?? '—' }}
                                    </td>

                                    <td class="px-3 py-2">
                                        @if ($catalogValue !== null)
                                            {{ number_format((float) $catalogValue->catalog_value, 2, ',', '.') }}
                                            {{ $catalogValue->currency }}
                                        @else
                                            —
                                        @endif
                                    </td>

                                    <td class="px-3 py-2">
                                        {{ $stampCopy->quantity }}
                                    </td>

                                    <td class="px-3 py-2 font-semibold">
                                        @if ($totalValue !== null)
                                            {{ number_format($totalValue, 2, ',', '.') }}
                                            {{ $catalogValue->currency }}
                                        @else
                                            {{ __('Geen cataloguswaarde beschikbaar') }}
                                        @endif
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>

                    </table>
                </div>
            @else
                <flux:text class="mt-4 text-zinc-700!">
                    {{ __('Er zijn geen catalogusvermeldingen beschikbaar voor deze postzegel.') }}
                </flux:text>

            @endif

        </div>

        {{-- Waarderingen --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <flux:heading size="lg" class="text-zinc-900!">
                {{ __('Waarderingen') }}
            </flux:heading>

            @if ($stampCopy->valuations->isNotEmpty())

                <div class="mt-6 overflow-x-auto">
                    <table class="w-full text-sm">

                        <thead class="bg-brown-5">
                            <tr class="border-b border-theme-accent-3 text-left text-zinc-900">
                                <th class="px-3 py-2 font-semibold">
                                    {{ __('Datum') }}
                                </th>

                                <th class="px-3 py-2 font-semibold">
                                    {{ __('Waarde') }}
                                </th>

                                <th class="px-3 py-2 font-semibold">
                                    {{ __('Bron') }}
                                </th>

                                <th class="px-3 py-2 font-semibold">
                                    {{ __('Opmerkingen') }}
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($stampCopy->valuations->sortByDesc('valuation_date') as $valuation)
                                <tr class="border-b border-theme-accent-1 bg-brown-6 text-zinc-900 hover:bg-brown-5">

                                    <td class="px-3 py-2">
                                        {{ $valuation->valuation_date?->format('d-m-Y') ?? '—' }}
                                    </td>

                                    <td class="px-3 py-2">
                                        {{ number_format((float) $valuation->value, 2, ',', '.') }}
                                        {{ $valuation->currency ?? '' }}
                                    </td>

                                    <td class="px-3 py-2">
                                        {{ $valuation->source ?: '—' }}
                                    </td>

                                    <td class="px-3 py-2">
                                        {{ $valuation->notes ?: '—' }}
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>

                    </table>
                </div>
            @else
                <flux:text class="mt-4 text-zinc-700!">
                    {{ __('Er zijn nog geen waarderingen geregistreerd.') }}
                </flux:text>

            @endif

        </div>

        {{-- Bijzonderheden --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <flux:heading size="lg">
                {{ __('Bijzonderheden') }}
            </flux:heading>

            <dl class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                {{-- Watermerk --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Watermerk') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->stamp->country->watermarks()->where('slug', $stampCopy->watermark)->first()
                            ?->translationWithFallback()?->name ?? '—' }}
                    </dd>
                </div>

                {{-- Tanding --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Tanding') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->perforation ?: '—' }}
                    </dd>
                </div>

                {{-- Gomconditie --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Gomconditie') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->gumCondition?->translationWithFallback()?->name ?? '—' }}
                    </dd>
                </div>

                {{-- Bijzondere variant --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Bijzondere variant') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->is_variant ? __('Ja') : __('Nee') }}
                    </dd>
                </div>

                {{-- Beschrijving variant --}}
                @if ($stampCopy->is_variant)
                    <div class="md:col-span-2">
                        <dt class="text-sm text-zinc-600">
                            {{ __('Beschrijving variant') }}
                        </dt>
                        <dd class="mt-1 font-medium text-zinc-900">
                            <span
                                class="whitespace-pre-line">{{ trim($stampCopy->translationWithFallback()?->variant_description ?? ($stampCopy->variant_description ?? '')) ?: '—' }}</span>
                        </dd>
                    </div>
                @endif

                {{-- Persoonlijke notities --}}
                <div class="md:col-span-2">
                    <dt class="text-sm text-zinc-600">
                        {{ __('Persoonlijke notities') }}
                    </dt>
                    <dd class="mt-1 whitespace-pre-line font-medium text-zinc-900">
                        {{ $stampCopy->notes ?: '—' }}
                    </dd>
                </div>

            </dl>

        </div>
        {{-- Catalogusafbeeldingen --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <flux:heading size="lg">
                {{ __('Catalogusafbeeldingen') }}
            </flux:heading>

            @if ($stampCopy->stamp->images->isNotEmpty())

                <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">

                    @foreach ($stampCopy->stamp->images as $image)
                        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-4">

                            {{-- Afbeelding --}}
                            <img src="{{ asset('storage/' . $image->path) }}"
                                alt="{{ $image->alt_text ?: __('Postzegelafbeelding') }}"
                                class="h-64 w-full rounded-lg object-contain" loading="lazy" />

                            {{-- Afbeeldingstype --}}
                            <div class="mt-3">

                                <p class="text-sm font-medium text-zinc-900">

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
                                            {{ __('Afbeelding') }}
                                    @endswitch

                                </p>

                                @if ($image->alt_text)
                                    <p class="mt-1 text-sm text-zinc-600">
                                        {{ $image->alt_text }}
                                    </p>
                                @endif

                            </div>

                        </div>
                    @endforeach

                </div>
            @else
                <p class="mt-4 text-sm text-zinc-600">
                    {{ __('Er zijn nog geen catalogusafbeeldingen beschikbaar.') }}
                </p>

            @endif

        </div>
        {{-- Afbeeldingen van eigen exemplaren --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <flux:heading size="lg">
                {{ __('Afbeeldingen van mijn exemplaar') }}
            </flux:heading>

            @if ($stampCopy->images->isNotEmpty())

                <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">

                    @foreach ($stampCopy->images->sortByDesc('is_primary') as $image)
                        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-4">

                            {{-- Afbeelding --}}
                            <img src="{{ asset('storage/' . $image->path) }}"
                                alt="{{ $image->alt_text ?: __('Afbeelding van mijn postzegel') }}"
                                class="h-64 w-full rounded-lg object-contain" loading="lazy" />

                            {{-- Afbeeldingstype --}}
                            <div class="mt-3">

                                <p class="text-sm font-medium text-zinc-900">

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
                                            {{ __('Afbeelding') }}
                                    @endswitch

                                </p>

                                {{-- Hoofdafbeelding --}}
                                @if ($image->is_primary)
                                    <span
                                        class="mt-2 inline-flex rounded-lg border border-theme-accent-1 bg-brown-5 px-3 py-1 text-xs font-medium text-zinc-900">
                                        {{ __('Hoofdafbeelding') }}
                                    </span>
                                @endif

                                {{-- Alternatieve tekst --}}
                                @if ($image->alt_text)
                                    <p class="mt-2 text-sm text-zinc-600">
                                        {{ $image->alt_text }}
                                    </p>
                                @endif

                            </div>

                        </div>
                    @endforeach

                </div>
            @else
                <p class="mt-4 text-sm text-zinc-600">
                    {{ __('Er zijn nog geen afbeeldingen van dit exemplaar toegevoegd.') }}
                </p>

            @endif

        </div>

    </div>

</x-layouts::app>
