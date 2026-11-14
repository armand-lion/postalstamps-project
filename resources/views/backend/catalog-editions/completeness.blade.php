<x-layouts::app.sidebar :title="__('Catalogus volledigheid')">

    <flux:main>

        <div class="space-y-6">

            {{-- Paginatitel en bulkbewerking --}}
            <div class="flex flex-wrap items-center justify-between gap-4">

                <flux:heading size="xl" class="text-white!">
                    {{ __('Catalogus volledigheid') }}
                </flux:heading>

                <flux:button :href="route('backend.catalogs.editions.bulk-edit', [$catalog, $edition])"
                    icon="pencil-square"
                    class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">

                    {{ __('Catalogusgegevens in bulk bewerken') }}

                </flux:button>

            </div>

            <section class="rounded-xl border border-theme-accent-3 bg-brown-5 p-6">

                {{-- Catalogusgegevens --}}
                <div class="grid gap-4 md:grid-cols-2">

                    {{-- Catalogus --}}
                    <div>
                        <div class="text-sm font-semibold text-zinc-900">
                            {{ __('Catalogus') }}
                        </div>

                        <div class="mt-1 text-base text-zinc-900">
                            {{ $catalog->name }}
                        </div>
                    </div>

                    {{-- Cataloguseditie --}}
                    <div>
                        <div class="text-sm font-semibold text-zinc-900">
                            {{ __('Cataloguseditie') }}
                        </div>

                        <div class="mt-1 text-base text-zinc-900">
                            {{ $edition->edition_name }}
                        </div>
                    </div>

                </div>

                {{-- Filters --}}
                <div class="mt-6 flex flex-wrap items-end gap-4">
                    {{-- Filter op land of gebied --}}
                    <form method="GET"
                        action="{{ route('backend.catalogs.editions.completeness', [$catalog, $edition]) }}">

                        {{-- Behoud de geselecteerde waarderingscategorie --}}
                        <input type="hidden" name="condition_id" value="{{ $selectedConditionId ?: '' }}">

                        <div class="w-full sm:w-80">

                            <flux:field>

                                <flux:label>{{ __('Land of gebied') }}</flux:label>

                                @php
                                    $selectedCountry = $countries->firstWhere('id', $selectedCountryId);
                                @endphp

                                <div x-data="{
                                    selectedId: @js($selectedCountryId > 0 ? (string) $selectedCountryId : ''),
                                    selectedName: @js($selectedCountry?->translationWithFallback()?->name ?? __('Alle landen en gebieden'))
                                }">

                                    <input type="hidden" name="country_id" x-model="selectedId">

                                    <flux:dropdown position="bottom" align="start" class="block w-full">

                                        <flux:button type="button" icon-trailing="chevron-down"
                                            class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-4! text-zinc-900! hover:bg-brown-3!">

                                            <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                        </flux:button>

                                        <flux:menu
                                            class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                            {{-- Alle landen --}}
                                            <flux:menu.item as="button" type="button"
                                                x-on:click="
                                                selectedId = '';
                                                selectedName = @js(__('Alle landen en gebieden'));
                                                $nextTick(() => $el.closest('form').requestSubmit());
                                            "
                                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                                {{ __('Alle landen en gebieden') }}

                                            </flux:menu.item>

                                            {{-- Landen van de catalogus --}}
                                            @foreach ($countries as $country)
                                                <flux:menu.item as="button" type="button"
                                                    x-on:click="
                                                    selectedId = @js((string) $country->id);
                                                    selectedName = @js($country->translationWithFallback()?->name);
                                                    $nextTick(() => $el.closest('form').requestSubmit());
                                                "
                                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                                    {{ $country->translationWithFallback()?->name }}

                                                </flux:menu.item>
                                            @endforeach

                                        </flux:menu>

                                    </flux:dropdown>

                                </div>

                            </flux:field>

                        </div>

                    </form>

                    {{-- Filter op waarderingscategorie --}}
                    <form method="GET"
                        action="{{ route('backend.catalogs.editions.completeness', [$catalog, $edition]) }}">

                        {{-- Behoud het geselecteerde land --}}
                        <input type="hidden" name="country_id" value="{{ $selectedCountryId ?: '' }}">

                        <div class="w-full sm:w-80">

                            <flux:field>

                                <flux:label>{{ __('Waarderingscategorie') }}</flux:label>

                                @php
                                    $selectedCondition = $conditions->firstWhere('id', $selectedConditionId);
                                @endphp

                                <div x-data="{
                                    selectedId: @js($selectedConditionId > 0 ? (string) $selectedConditionId : ''),
                                    selectedName: @js($selectedCondition?->translationWithFallback()?->name ?? __('Alle waarderingscategorieën'))
                                }">

                                    <input type="hidden" name="condition_id" x-model="selectedId">

                                    <flux:dropdown position="bottom" align="start" class="block w-full">

                                        <flux:button type="button" icon-trailing="chevron-down"
                                            class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-4! text-zinc-900! hover:bg-brown-3!">

                                            <span x-text="selectedName" class="min-w-0 truncate text-left"></span>

                                        </flux:button>

                                        <flux:menu
                                            class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                                            {{-- Alle waarderingscategorieën --}}
                                            <flux:menu.item as="button" type="button"
                                                x-on:click="
                                                selectedId = '';
                                                selectedName = @js(__('Alle waarderingscategorieën'));
                                                $nextTick(() => $el.closest('form').requestSubmit());
                                            "
                                                class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                                {{ __('Alle waarderingscategorieën') }}

                                            </flux:menu.item>

                                            {{-- Waarderingscategorieën van de catalogus --}}
                                            @foreach ($conditions as $condition)
                                                <flux:menu.item as="button" type="button"
                                                    x-on:click="
                                    selectedId = @js((string) $condition->id);
                                    selectedName = @js($condition->translationWithFallback()?->name ?? $condition->slug);
                                    $nextTick(() => $el.closest('form').requestSubmit());
                                "
                                                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                                                    {{ $condition->translationWithFallback()?->name ?? $condition->slug }}

                                                </flux:menu.item>
                                            @endforeach

                                        </flux:menu>

                                    </flux:dropdown>

                                </div>

                            </flux:field>

                        </div>

                    </form>


                    {{-- Filters wissen --}}
                    @if ($selectedCountryId > 0 || $selectedConditionId > 0)
                        <div class="mt-4">

                            <flux:button :href="route('backend.catalogs.editions.completeness', [$catalog, $edition])"
                                variant="ghost" icon="x-mark"
                                class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-6!">

                                {{ __('Filters wissen') }}

                            </flux:button>

                        </div>
                    @endif
                </div>
                {{-- Einde filters --}}


                {{-- Statistieken catalogus volledigheid --}}
                <div class="mt-6 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    {{-- Totaal aantal relevante postzegels --}}
                    <div class="rounded-xl border border-theme-accent-3 bg-brown-6 p-5">
                        <flux:text class="text-sm font-medium text-zinc-900!">
                            {{ __('Totaal aantal postzegels') }}
                        </flux:text>

                        <div class="mt-3 text-3xl font-bold text-zinc-900">
                            {{ number_format($totalStamps, 0, ',', '.') }}
                        </div>
                    </div>

                    {{-- Postzegels met catalogusnummer --}}
                    <div class="rounded-xl border border-theme-accent-3 bg-brown-6 p-5">
                        <flux:text class="text-sm font-medium text-zinc-900!">
                            {{ __('Met catalogusnummer') }}
                        </flux:text>

                        <div class="mt-3 text-3xl font-bold text-zinc-900">
                            {{ number_format($stampsWithCatalogNumber, 0, ',', '.') }}
                        </div>
                    </div>

                    {{-- Postzegels zonder catalogusnummer --}}
                    <div class="rounded-xl border border-theme-accent-3 bg-brown-6 p-5">
                        <flux:text class="text-sm font-medium text-zinc-900!">
                            {{ __('Zonder catalogusnummer') }}
                        </flux:text>

                        <div class="mt-3 text-3xl font-bold text-zinc-900">
                            {{ number_format($stampsWithoutCatalogNumber, 0, ',', '.') }}
                        </div>
                    </div>

                    {{-- Volledigheid catalogusnummers --}}
                    <div class="rounded-xl border border-theme-accent-3 bg-brown-6 p-5">
                        <flux:text class="text-sm font-medium text-zinc-900!">
                            {{ __('Volledigheid catalogusnummers') }}
                        </flux:text>

                        <div class="mt-3 text-3xl font-bold text-zinc-900">
                            {{ number_format($catalogNumberCompleteness, 1, ',', '.') }}%
                        </div>
                    </div>
                </div>


                <div class="mt-4 grid gap-4 md:grid-cols-2 lg:grid-cols-4">

                    {{-- Ontbrekende cataloguswaarderingen --}}
                    <div class="rounded-xl border border-theme-accent-3 bg-brown-6 p-5">
                        <flux:text class="text-sm font-medium text-zinc-900!">
                            {{ __('Ontbrekende cataloguswaarderingen') }}
                        </flux:text>

                        <div class="mt-3 text-3xl font-bold text-zinc-900">
                            {{ number_format($missingCatalogValues, 0, ',', '.') }}
                        </div>
                    </div>

                    {{-- Volledigheid cataloguswaarderingen --}}
                    <div class="rounded-xl border border-theme-accent-3 bg-brown-6 p-5">

                        <flux:text class="text-sm font-medium text-zinc-900!">
                            {{ __('Volledigheid cataloguswaarderingen') }}
                        </flux:text>

                        <div class="mt-3 text-3xl font-bold text-zinc-900">
                            {{ number_format($catalogValueCompleteness, 1, ',', '.') }}%
                        </div>
                    </div>
                </div>

                {{-- Overzicht ontbrekende catalogusnummers --}}
                <div class="mt-8">

                    <flux:heading size="lg" class="mb-4 text-zinc-900!">
                        {{ __('Postzegels zonder catalogusnummer') }}
                    </flux:heading>

                    <div class="overflow-x-auto rounded-xl border border-theme-accent-3">

                        <table class="min-w-full divide-y divide-theme-accent-3">

                            <thead class="bg-brown-6">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-zinc-900">
                                        {{ __('Postzegel') }}
                                    </th>

                                    <th class="px-4 py-3 text-left text-sm font-semibold text-zinc-900">
                                        {{ __('Land') }}
                                    </th>

                                    <th class="px-4 py-3 text-left text-sm font-semibold text-zinc-900">
                                        {{ __('Uitgiftejaar') }}
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-theme-accent-3 bg-brown-5">

                                @forelse ($stampsMissingCatalogNumber as $stamp)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-zinc-900">

                                            <a href="{{ route('backend.stamps.edit', $stamp) }}"
                                                class="font-medium text-zinc-900 underline decoration-theme-accent-3 underline-offset-4 hover:text-theme-accent-1">

                                                {{ $stamp->name }}

                                            </a>

                                        </td>

                                        <td class="px-4 py-3 text-sm text-zinc-900">
                                            {{ $stamp->country?->name ?? '—' }}
                                        </td>

                                        <td class="px-4 py-3 text-sm text-zinc-900">
                                            {{ $stamp->issue_year ?? '—' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-6 text-center text-sm text-zinc-900">
                                            {{ __('Alle postzegels hebben een catalogusnummer.') }}
                                        </td>
                                    </tr>
                                @endforelse

                            </tbody>

                        </table>
                    </div>
                </div>

                {{-- Overzicht ontbrekende cataloguswaarderingen --}}
                <div class="mt-8">

                    <flux:heading size="lg" class="mb-4 text-zinc-900!">
                        {{ __('Postzegels met ontbrekende cataloguswaarderingen') }}
                    </flux:heading>

                    <div class="overflow-x-auto rounded-xl border border-theme-accent-3">

                        <table class="min-w-full divide-y divide-theme-accent-3">

                            <thead class="bg-brown-6">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-zinc-900">
                                        {{ __('Postzegel') }}
                                    </th>

                                    <th class="px-4 py-3 text-left text-sm font-semibold text-zinc-900">
                                        {{ __('Uitgiftejaar') }}
                                    </th>

                                    <th class="px-4 py-3 text-left text-sm font-semibold text-zinc-900">
                                        {{ __('Ontbrekende waarderingscategorie') }}
                                    </th>
                                </tr>
                            </thead>

                            <tbody class="divide-y divide-theme-accent-3 bg-brown-5">

                                @forelse ($missingCatalogValueDetails as $detail)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-zinc-900">

                                            <a href="{{ route('backend.stamps.edit', $detail['stamp_id']) }}"
                                                class="font-medium text-zinc-900 underline decoration-theme-accent-3 underline-offset-4 hover:text-theme-accent-1">

                                                {{ $detail['stamp_name'] }}

                                            </a>

                                        </td>

                                        <td class="px-4 py-3 text-sm text-zinc-900">
                                            {{ $detail['issue_year'] ?? '—' }}
                                        </td>

                                        <td class="px-4 py-3 text-sm text-zinc-900">
                                            {{ $detail['condition_name'] }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-6 text-center text-sm text-zinc-900">
                                            {{ __('Alle cataloguswaarderingen zijn ingevuld.') }}
                                        </td>
                                    </tr>
                                @endforelse

                            </tbody>

                        </table>

                    </div>

                </div>







            </section>

        </div>

    </flux:main>

</x-layouts::app.sidebar>
