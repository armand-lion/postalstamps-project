<x-layouts::app :title="__('Postzegel bekijken')">
    <div class="flex flex-col gap-6 rounded-lg border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

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
                <flux:button :href="route('backend.stamps.index')" variant="ghost" icon="arrow-left"
                    class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">
                    {{ __('Terug naar overzicht') }}
                </flux:button>

                <flux:button :href="route('backend.stamps.edit', $stamp)" variant="primary" icon="pencil"
                    class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">
                    {{ __('Postzegel bewerken') }}
                </flux:button>
            </div>
        </div>

        {{-- Algemene gegevens --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-5 p-4 md:p-6">
            <flux:heading size="lg" class="text-zinc-900!">
                {{ __('Algemene gegevens') }}
            </flux:heading>

            <dl class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                <div>
                    <dt class="text-sm text-zinc-700">
                        {{ __('Naam') }}
                    </dt>

                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stamp->translationWithFallback()?->name ?? $stamp->name }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-700">
                        {{ __('Land') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stamp->country?->translationWithFallback()?->name ?? ($stamp->country?->name ?? '—') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-700">
                        {{ __('Postzegelserie') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stamp->series?->translationWithFallback()?->name ?? ($stamp->series?->name ?? '—') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-700">
                        {{ __('Uitgiftejaar') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stamp->issue_year ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-700">
                        {{ __('Uitgiftedatum') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stamp->issue_date?->format('d-m-Y') ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-700">
                        {{ __('Nominale waarde') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        @if ($stamp->face_value !== null)
                            {{ number_format((float) $stamp->face_value, 2, ',', '.') }}
                            {{ $stamp->currency ?? '' }}
                        @else
                            —
                        @endif
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-700">
                        {{ __('Ontwerper') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stamp->designer ?: '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-700">
                        {{ __('Druktechniek') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stamp->printing_method ?: '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-700">
                        {{ __('Catalogusreferentie') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stamp->catalog_reference ?: '—' }}
                    </dd>
                </div>

            </dl>
        </div>

        {{-- Catalogusnummers en cataloguswaarden --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-5 p-4 md:p-6">

            <flux:heading size="lg" class="text-zinc-900!">
                {{ __('Catalogusnummers en cataloguswaarden') }}
            </flux:heading>

            @if ($stamp->catalogEntries->isNotEmpty())

                <div class="mt-4 overflow-x-auto">

                    <table class="w-full text-sm">

                        <thead class="bg-brown-6">
                            <tr class="border-b border-theme-accent-3 text-left text-zinc-900">
                                <th class="px-3 py-2 font-semibold">{{ __('Catalogus') }}</th>
                                <th class="px-3 py-2 font-semibold">{{ __('Cataloguseditie') }}</th>
                                <th class="px-3 py-2 font-semibold">{{ __('Catalogusnummer') }}</th>
                                <th class="px-3 py-2 font-semibold">{{ __('Postfris') }}</th>
                                <th class="px-3 py-2 font-semibold">{{ __('Gestempeld') }}</th>
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

                                <tr class="border-b border-theme-accent-1 bg-brown-5 text-zinc-900 hover:bg-brown-6">

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

        {{-- Historische cataloguswaarden --}}
        <x-backend.stamps.historical-catalog-values :catalog-entries="$stamp->catalogEntries" />

        {{-- Thema's --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-5 p-4 md:p-6">

            <flux:heading size="lg" class="text-zinc-900!">
                {{ __('Thema\'s') }}
            </flux:heading>

            @if ($stamp->themes->isNotEmpty())

                <div class="mt-4 flex flex-wrap gap-2">

                    @foreach ($stamp->themes as $theme)
                        <span
                            class="inline-flex items-center rounded-lg border border-theme-accent-3 bg-brown-6 px-3 py-1.5 text-sm font-medium text-zinc-900">

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
        <div class="rounded-xl border border-theme-accent-1 bg-brown-5 p-4 md:p-6">

            <flux:heading size="lg" class="text-zinc-900!">
                {{ __('Beschrijving') }}
            </flux:heading>

            <div class="mt-4 whitespace-pre-line text-sm text-zinc-900">
                {{ $stamp->translationWithFallback()?->description ?? ($stamp->description ?? '—') }}
            </div>

        </div>

        {{-- Notities --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-5 p-4 md:p-6">

            <flux:heading size="lg" class="text-zinc-900!">
                {{ __('Notities') }}
            </flux:heading>

            <div class="mt-4 whitespace-pre-line text-sm text-zinc-900">
                {{ $stamp->notes ?: '—' }}
            </div>

        </div>

        {{-- Postzegelafbeeldingen --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-5 p-4 md:p-6">

            <flux:heading size="lg" class="text-zinc-900!">
                {{ __('Postzegelafbeeldingen') }}
            </flux:heading>

            @if ($stamp->images->isNotEmpty())
                <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($stamp->images as $image)
                        <div class="rounded-lg border border-theme-accent-3 bg-brown-6 p-4">

                            <img src="{{ asset('storage/' . $image->path) }}"
                                alt="{{ $image->alt_text ?: __('Postzegelafbeelding') }}"
                                class="h-64 w-full rounded-lg object-contain" />

                            <div class="mt-3">
                                <flux:text class="font-medium text-zinc-900!">
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
                                    <flux:text class="mt-1 text-sm text-zinc-700!">
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

    </div>
</x-layouts::app>
