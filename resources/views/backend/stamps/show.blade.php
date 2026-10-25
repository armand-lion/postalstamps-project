<x-layouts::app :title="__('Postzegel bekijken')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel en acties --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">
                    {{ $stamp->translationWithFallback()?->name ?? $stamp->name }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Details van deze postzegel.') }}
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-3">
                <flux:button :href="route('backend.stamps.index')" variant="ghost" icon="arrow-left">
                    {{ __('Terug naar overzicht') }}
                </flux:button>

                <flux:button :href="route('backend.stamps.edit', $stamp)" variant="primary" icon="pencil">
                    {{ __('Postzegel bewerken') }}
                </flux:button>
            </div>
        </div>

        {{-- Algemene gegevens --}}
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
            <flux:heading size="lg">
                {{ __('Algemene gegevens') }}
            </flux:heading>

            <dl class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Naam') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $stamp->translationWithFallback()?->name ?? $stamp->name }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Land') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $stamp->country?->translationWithFallback()?->name ?? ($stamp->country?->name ?? '—') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Postzegelserie') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $stamp->series?->translationWithFallback()?->name ?? ($stamp->series?->name ?? '—') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Uitgiftejaar') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $stamp->issue_year ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Uitgiftedatum') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $stamp->issue_date?->format('d-m-Y') ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Nominale waarde') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        @if ($stamp->face_value !== null)
                            {{ number_format((float) $stamp->face_value, 2, ',', '.') }}
                            {{ $stamp->currency ?? '' }}
                        @else
                            —
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Ontwerper') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $stamp->designer ?: '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Druktechniek') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $stamp->printing_method ?: '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Catalogusreferentie') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $stamp->catalog_reference ?: '—' }}
                    </dd>
                </div>

            </dl>
        </div>

        {{-- Catalogusnummers en cataloguswaarden --}}
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">

            <flux:heading size="lg">
                {{ __('Catalogusnummers en cataloguswaarden') }}
            </flux:heading>

            @if ($stamp->catalogEntries->isNotEmpty())

                <div class="mt-4 overflow-x-auto">

                    <table class="w-full text-sm">

                        <thead>
                            <tr class="border-b border-neutral-200 text-left dark:border-neutral-700">
                                <th class="px-3 py-2">{{ __('Catalogus') }}</th>
                                <th class="px-3 py-2">{{ __('Cataloguseditie') }}</th>
                                <th class="px-3 py-2">{{ __('Catalogusnummer') }}</th>
                                <th class="px-3 py-2">{{ __('Postfris') }}</th>
                                <th class="px-3 py-2">{{ __('Gestempeld') }}</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($stamp->catalogEntries as $entry)
                                @php
                                    $postfris = $entry->values->first(
                                        fn($value) => $value->condition?->slug === 'postfris',
                                    );

                                    $gestempeld = $entry->values->first(
                                        fn($value) => $value->condition?->slug === 'gestempeld',
                                    );
                                @endphp

                                <tr class="border-b border-neutral-200/50 dark:border-neutral-700/50">

                                    <td class="px-3 py-2">
                                        {{ $entry->catalogEdition?->catalog?->abbreviation ?: $entry->catalogEdition?->catalog?->name }}
                                    </td>

                                    <td class="px-3 py-2">
                                        {{ $entry->catalogEdition?->edition_name }}
                                    </td>

                                    <td class="px-3 py-2 font-semibold">
                                        {{ $entry->catalog_number }}
                                    </td>

                                    <td class="px-3 py-2">
                                        @if ($postfris)
                                            {{ $postfris->currency }}
                                            {{ number_format($postfris->catalog_value, 2, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </td>

                                    <td class="px-3 py-2">
                                        @if ($gestempeld)
                                            {{ $gestempeld->currency }}
                                            {{ number_format($gestempeld->catalog_value, 2, ',', '.') }}
                                        @else
                                            —
                                        @endif
                                    </td>

                                </tr>
                            @endforeach
                        </tbody>

                    </table>

                </div>
            @else
                <flux:text class="mt-4">
                    {{ __('Er zijn nog geen catalogusnummers geregistreerd.') }}
                </flux:text>

            @endif

        </div>
        {{-- Thema's --}}
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">

            <flux:heading size="lg">
                {{ __('Thema\'s') }}
            </flux:heading>

            @if ($stamp->themes->isNotEmpty())

                <div class="mt-4 flex flex-wrap gap-2">

                    @foreach ($stamp->themes as $theme)
                        <span
                            class="inline-flex items-center rounded-lg border border-neutral-200 bg-neutral-100 px-3 py-1.5 text-sm font-medium dark:border-neutral-700 dark:bg-neutral-800">

                            {{ $theme->translationWithFallback()?->name ?? $theme->name }}

                        </span>
                    @endforeach

                </div>
            @else
                <flux:text class="mt-4">
                    {{ __('Er zijn nog geen thema\'s gekoppeld aan deze postzegel.') }}
                </flux:text>

            @endif

        </div>


        {{-- Beschrijving --}}
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
            <flux:heading size="lg">
                {{ __('Beschrijving') }}
            </flux:heading>

            <div class="mt-4 whitespace-pre-line text-sm">
                {{ $stamp->translationWithFallback()?->description ?? ($stamp->description ?? '—') }}
            </div>
        </div>

        {{-- Notities --}}
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
            <flux:heading size="lg">
                {{ __('Notities') }}
            </flux:heading>

            <div class="mt-4 whitespace-pre-line text-sm">
                {{ $stamp->notes ?: '—' }}
            </div>
        </div>

        {{-- Postzegelafbeeldingen --}}
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
            <flux:heading size="lg">
                {{ __('Postzegelafbeeldingen') }}
            </flux:heading>

            @if ($stamp->images->isNotEmpty())
                <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($stamp->images as $image)
                        <div class="rounded-lg border border-neutral-200 p-4 dark:border-neutral-700">
                            <img src="{{ asset('storage/' . $image->path) }}"
                                alt="{{ $image->alt_text ?: __('Postzegelafbeelding') }}"
                                class="h-64 w-full rounded-lg object-contain" />

                            <div class="mt-3">
                                <flux:text class="font-medium">
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
                                    @endswitch
                                </flux:text>

                                @if ($image->alt_text)
                                    <flux:text class="mt-1 text-sm">
                                        {{ $image->alt_text }}
                                    </flux:text>
                                @endif
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

        {{-- Fysieke exemplaren --}}
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
            <flux:heading size="lg">
                {{ __('Fysieke exemplaren') }}
            </flux:heading>

            @if ($stamp->copies->isNotEmpty())
                <flux:table class="mt-4">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Conditie') }}</flux:table.column>
                        <flux:table.column>{{ __('Aantal') }}</flux:table.column>
                        <flux:table.column>{{ __('Aankoopdatum') }}</flux:table.column>
                        <flux:table.column>{{ __('Aankoopprijs') }}</flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($stamp->copies as $copy)
                            <flux:table.row>
                                <flux:table.cell>
                                    {{ $copy->condition?->translationWithFallback()?->name ?? '—' }}
                                </flux:table.cell>

                                <flux:table.cell>
                                    {{ $copy->quantity ?? 1 }}
                                </flux:table.cell>

                                <flux:table.cell>
                                    {{ $copy->purchase_date?->format('d-m-Y') ?? '—' }}
                                </flux:table.cell>

                                <flux:table.cell>
                                    @if ($copy->purchase_price !== null)
                                        {{ number_format((float) $copy->purchase_price, 2, ',', '.') }}
                                        {{ $copy->purchase_currency ?? '' }}
                                    @else
                                        —
                                    @endif
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @else
                <flux:text class="mt-4">
                    {{ __('Er zijn nog geen fysieke exemplaren geregistreerd.') }}
                </flux:text>
            @endif
        </div>

    </div>
</x-layouts::app>
