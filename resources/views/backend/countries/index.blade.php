<x-layouts::app :title="__('Landen en gebieden beheren')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ __('Landen en gebieden beheren') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Beheer huidige landen, historische staten en postzegeluitgevende gebieden.') }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.countries.create')" variant="primary" icon="plus">
                {{ __('Land of gebied toevoegen') }}
            </flux:button>
        </div>

        {{-- Succesmelding --}}
        @if (session('success'))
            <div
                class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        {{-- Foutmelding --}}
        @if (session('error'))
            <div
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('backend.countries.index') }}"
            class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 sm:flex-row sm:flex-wrap sm:items-end">

            {{-- Zoekterm --}}
            <flux:field class="flex-1">
                <flux:label>{{ __('Zoeken') }}</flux:label>

                <flux:input name="search" value="{{ request('search') }}"
                    placeholder="{{ __('Naam, code of slug') }}" />
            </flux:field>

            {{-- Type entiteit --}}
            <flux:field class="flex-1">
                <flux:label>{{ __('Type entiteit') }}</flux:label>

                <flux:select name="entity_type">
                    <option value="">{{ __('Alle typen') }}</option>

                    @foreach ($entityTypes as $value => $label)
                        <option value="{{ $value }}" @selected(request('entity_type') === $value)>
                            {{ __($label) }}
                        </option>
                    @endforeach
                </flux:select>
            </flux:field>

            {{-- Status --}}
            <flux:field class="flex-1">
                <flux:label>{{ __('Status') }}</flux:label>

                <flux:select name="status">
                    <option value="">{{ __('Alle statussen') }}</option>
                    <option value="active" @selected(request('status') === 'active')>
                        {{ __('Actief') }}
                    </option>
                    <option value="inactive" @selected(request('status') === 'inactive')>
                        {{ __('Inactief') }}
                    </option>
                </flux:select>
            </flux:field>

            {{-- Acties --}}
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">
                    {{ __('Filteren') }}
                </flux:button>

                <flux:button :href="route('backend.countries.index')" variant="ghost">
                    {{ __('Wissen') }}
                </flux:button>
            </div>
        </form>

        {{-- Overzicht --}}
        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">

            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <flux:heading size="lg">
                    {{ __('Landen en gebieden') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ trans_choice(':count entiteit gevonden|:count entiteiten gevonden', $countries->total()) }}
                </flux:text>
            </div>

            @if ($countries->isEmpty())
                <div class="p-8 text-center">
                    <flux:heading>
                        {{ __('Geen landen of gebieden gevonden') }}
                    </flux:heading>

                    <flux:text class="mt-2">
                        {{ __('Voeg een land of gebied toe of pas de filters aan.') }}
                    </flux:text>

                    <div class="mt-4">
                        <flux:button :href="route('backend.countries.create')" variant="primary">
                            {{ __('Eerste land of gebied toevoegen') }}
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ __('Naam') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Code') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Type') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Historische periode') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Postzegels') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Series') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Catalogi') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('Acties') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($countries as $country)
                                <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-900/50">

                                    {{-- Naam --}}
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('backend.countries.show', $country) }}"
                                            class="underline decoration-neutral-300 underline-offset-4 hover:decoration-current">
                                            {{ $country->translationWithFallback()?->name ?? $country->name }}
                                        </a>
                                    </td>

                                    {{-- Code --}}
                                    <td class="px-4 py-3">
                                        {{ $country->iso_code ?? '—' }}
                                    </td>

                                    {{-- Type --}}
                                    <td class="px-4 py-3">
                                        {{ __($entityTypes[$country->entity_type] ?? $country->entity_type) }}
                                    </td>

                                    {{-- Historische periode --}}
                                    <td class="px-4 py-3">
                                        @if ($country->start_year !== null || $country->end_year !== null)
                                            {{ $country->start_year ?? '…' }}
                                            –
                                            {{ $country->end_year ?? 'heden' }}
                                        @else
                                            —
                                        @endif
                                    </td>

                                    {{-- Status --}}
                                    <td class="px-4 py-3">
                                        @if ($country->is_active)
                                            <span class="text-green-700 dark:text-green-400">
                                                {{ __('Actief') }}
                                            </span>
                                        @else
                                            <span class="text-neutral-500">
                                                {{ __('Inactief') }}
                                            </span>
                                        @endif
                                    </td>

                                    {{-- Aantallen --}}
                                    <td class="px-4 py-3">
                                        {{ $country->stamps_count }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $country->stamp_series_count }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $country->catalogs_count }}
                                    </td>

                                    {{-- Acties --}}
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap justify-end gap-2">

                                            {{-- Bekijken --}}
                                            <flux:button
                                                :href="route('backend.countries.show', $country)"
                                                size="sm"
                                                variant="ghost"
                                                icon="eye"
                                            >
                                                {{ __('Bekijken') }}
                                            </flux:button>

                                            {{-- Bewerken --}}
                                            <flux:button
                                                :href="route('backend.countries.edit', $country)"
                                                size="sm"
                                                variant="ghost"
                                                icon="pencil"
                                            >
                                                {{ __('Bewerken') }}
                                            </flux:button>

                                            {{-- Verwijderen --}}
                                            <form
                                                method="POST"
                                                action="{{ route('backend.countries.destroy', $country) }}"
                                                class="delete-confirm-form"
                                            >
                                                @csrf
                                                @method('DELETE')

                                                <flux:button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    icon="trash"
                                                    class="text-red-600 dark:text-red-400"
                                                    onclick="openDeleteModal(this.closest('form'))"
                                                >
                                                    {{ __('Verwijderen') }}
                                                </flux:button>

                                            </form>

                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Paginering --}}
                <div class="border-t border-neutral-200 p-4 dark:border-neutral-700">
                    {{ $countries->links() }}
                </div>
            @endif
        </div>
    </div>

    @include('components.delete-confirm-modal')
</x-layouts::app>
