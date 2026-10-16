<x-layouts::app :title="__('Postzegel bekijken')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel en acties --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">
                    {{ $stamp->name }}
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
                        {{ $stamp->name }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Land') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $stamp->country?->name ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-neutral-500 dark:text-neutral-400">
                        {{ __('Postzegelserie') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $stamp->series?->name ?? '—' }}
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

        {{-- Beschrijving --}}
        <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
            <flux:heading size="lg">
                {{ __('Beschrijving') }}
            </flux:heading>

            <div class="mt-4 whitespace-pre-line text-sm">
                {{ $stamp->description ?: '—' }}
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
                                    {{ $copy->condition?->translation?->name ?? '—' }}
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
