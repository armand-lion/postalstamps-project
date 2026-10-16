<x-layouts::app :title="__('Postzegels beheren')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ __('Postzegels beheren') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Beheer de postzegels in je catalogus.') }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.stamps.create')" variant="primary" icon="plus">
                {{ __('Postzegel toevoegen') }}
            </flux:button>
        </div>

        {{-- Succesmelding --}}
        @if (session('success'))
            <div
                class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        {{-- foutmelding --}}
        @if (session('error'))
            <div
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                {{ session('error') }}
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('backend.stamps.index') }}"
            class="flex flex-col gap-4 rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 sm:flex-row sm:items-end">
            {{-- Land --}}
            <flux:field class="flex-1">
                <flux:label>
                    {{ __('Land') }}
                </flux:label>

                <flux:select name="country_id">
                    <option value="">
                        {{ __('Alle landen') }}
                    </option>

                    @foreach ($countries as $country)
                        <option value="{{ $country->id }}" @selected((string) request('country_id') === (string) $country->id)>
                            {{ $country->name }}
                        </option>
                    @endforeach
                </flux:select>
            </flux:field>

            {{-- Uitgiftejaar --}}
            <flux:field class="flex-1">
                <flux:label>
                    {{ __('Uitgiftejaar') }}
                </flux:label>

                <flux:input type="number" name="issue_year" min="1840" max="2200"
                    value="{{ request('issue_year') }}" placeholder="{{ __('Bijvoorbeeld 2026') }}" />
            </flux:field>

            {{-- Acties --}}
            <div class="flex gap-2">
                <flux:button type="submit" variant="primary">
                    {{ __('Filteren') }}
                </flux:button>

                <flux:button :href="route('backend.stamps.index')" variant="ghost">
                    {{ __('Wissen') }}
                </flux:button>
            </div>
        </form>

        {{-- Resultaten --}}
        <div class="overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">

            <div class="border-b border-neutral-200 px-4 py-3 dark:border-neutral-700">
                <flux:heading size="lg">
                    {{ __('Catalogus') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ trans_choice(__(':count stamp found|:count stamps found'), $stamps->total()) }}
                </flux:text>
            </div>

            @if ($stamps->isEmpty())
                <div class="p-8 text-center">
                    <flux:heading>{{ __('Geen postzegels gevonden') }}</flux:heading>

                    <flux:text class="mt-2">
                        {{ __('Voeg een postzegel toe of pas de filters aan.') }}
                    </flux:text>

                    <div class="mt-4">
                        <flux:button :href="route('backend.stamps.create')" variant="primary">
                            {{ __('Eerste postzegel toevoegen') }}
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-neutral-50 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                            <tr>
                                <th class="px-4 py-3 font-medium">{{ __('Naam') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Land') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Jaar') }}</th>
                                <th class="px-4 py-3 font-medium">{{ __('Nominale waarde') }}</th>
                                <th class="px-4 py-3 text-right font-medium">{{ __('Acties') }}</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-neutral-200 dark:divide-neutral-700">
                            @foreach ($stamps as $stamp)
                                <tr class="hover:bg-neutral-50 dark:hover:bg-neutral-900/50">
                                    <td class="px-4 py-3 font-medium">
                                        <a href="{{ route('backend.stamps.show', $stamp) }}"
                                            class="underline decoration-neutral-300 underline-offset-4 hover:decoration-current">
                                            {{ $stamp->name }}
                                        </a>
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $stamp->country->name }}
                                    </td>

                                    <td class="px-4 py-3">
                                        {{ $stamp->issue_year }}
                                    </td>

                                    <td class="px-4 py-3">
                                        @if ($stamp->face_value !== null)
                                            {{ number_format((float) $stamp->face_value, 2, ',', '.') }}
                                            {{ $stamp->currency }}
                                        @else
                                            —
                                        @endif
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap justify-end gap-3">
                                            <a href="{{ route('backend.stamps.show', $stamp) }}"
                                                class="underline underline-offset-4">
                                                {{ __('Bekijken') }}
                                            </a>

                                            <a href="{{ route('backend.stamps.edit', $stamp) }}"
                                                class="underline underline-offset-4">
                                                {{ __('Bewerken') }}
                                            </a>

                                            <form method="POST" action="{{ route('backend.stamps.destroy', $stamp) }}"
                                                class="delete-confirm-form">
                                                @csrf
                                                @method('DELETE')

                                                <button type="button"
                                                    class="text-red-600 underline underline-offset-4 hover:text-red-800"
                                                    onclick="openDeleteModal(this.form)">
                                                    {{ __('Verwijderen') }}
                                                </button>
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
                    {{ $stamps->links() }}
                </div>
            @endif
        </div>
    </div>

    @include('components.delete-confirm-modal')
</x-layouts::app>
