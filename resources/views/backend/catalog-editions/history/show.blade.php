<x-layouts::app :title="__('Wijzigingsdetails')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div
            class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Wijzigingsdetails') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Details van de geregistreerde wijzigingen binnen deze bewerking.') }}
                </flux:text>

                <flux:text class="mt-1 font-medium">
                    {{ $catalog->name }} — {{ $edition->edition_name }}
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-3">

                <flux:button :href="route('backend.catalogs.editions.history.index', [$catalog, $edition])"
                    variant="ghost" icon="arrow-left">
                    {{ __('Terug naar geschiedenis') }}
                </flux:button>

            </div>

        </div>

        {{-- Bewerkingsinformatie --}}
        <section class="rounded-xl border border-theme-accent-1 bg-brown-6 overflow-hidden">

            <div class="border-b border-theme-accent-1 px-4 py-3">

                <flux:heading size="lg">
                    {{ __('Bewerkingsinformatie') }}
                </flux:heading>

            </div>

            <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">

                <div>
                    <flux:text class="text-sm">
                        {{ __('Datum en tijd') }}
                    </flux:text>

                    <flux:text class="mt-1 font-medium">
                        {{ $batch->created_at?->format('d-m-Y H:i:s') ?? '—' }}
                    </flux:text>
                </div>

                <div>
                    <flux:text class="text-sm">
                        {{ __('Bewerking') }}
                    </flux:text>

                    <flux:text class="mt-1 font-medium text-zinc-900!">
                        {{ $batch->operationLabel() }}
                    </flux:text>
                </div>

                <div>
                    <flux:text class="text-sm">
                        {{ __('Aantal wijzigingen') }}
                    </flux:text>

                    <flux:text class="mt-1 font-medium text-zinc-900!">
                        {{ $batch->logs_count }}
                    </flux:text>
                </div>

                @if ($isAdmin)
                    <div>
                        <flux:text class="text-sm">
                            {{ __('Uitgevoerd door') }}
                        </flux:text>

                        <flux:text class="mt-1 font-medium text-zinc-900!">
                            {{ $batch->user_name ?: '—' }}
                        </flux:text>
                    </div>
                @endif

                <div class="sm:col-span-2">
                    <flux:text class="text-sm">
                        {{ __('Omschrijving') }}
                    </flux:text>

                    <flux:text class="mt-1 font-medium text-zinc-900!">
                        {{ $batch->description ?: '—' }}
                    </flux:text>
                </div>

            </div>

        </section>

        {{-- Geregistreerde wijzigingen --}}
        <section class="flex flex-col gap-4 overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6">

            <div class="border-b border-theme-accent-1 px-4 py-3">

                <flux:heading size="lg">
                    {{ __('Geregistreerde wijzigingen') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Hieronder worden de oude en nieuwe gegevens per wijziging weergegeven.') }}
                </flux:text>

            </div>

            <div class="flex flex-col gap-4 p-4">

                @if ($logs->isEmpty())

                    <div class="py-8 text-center">

                        <flux:heading size="lg">
                            {{ __('Geen wijzigingen gevonden') }}
                        </flux:heading>

                        <flux:text class="mt-2">
                            {{ __('Er zijn geen afzonderlijke wijzigingen bij deze bewerking geregistreerd.') }}
                        </flux:text>

                    </div>
                @else
                    @foreach ($logs as $log)
                        @php
                            $oldValues = $log->old_values ?? [];
                            $newValues = $log->new_values ?? [];

                            $fieldLabels = [
                                'catalog_number' => __('Catalogusnummer'),
                                'catalog_value' => __('Cataloguswaarde'),
                                'currency' => __('Valuta'),
                                'condition_id' => __('Waarderingscategorie'),
                                'notes' => __('Opmerkingen'),
                                'needs_review' => __('Controle vereist'),
                            ];

                            $hiddenFields = [
                                'id',
                                'catalog_edition_id',
                                'stamp_id',
                                'stamp_series_id',
                                'stamp_catalog_entry_id',
                                'created_at',
                                'updated_at',
                            ];

                            $fields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

                            $recordName = match ($log->record_type) {
                                'stamp_catalog_entry', 'stamp_catalog_value' => $log->stamp?->translationWithFallback()
                                    ?->name ??
                                    ($log->stamp?->name ?? __('Onbekende postzegel')),

                                'series_catalog_value' => $log->stampSeries?->translationWithFallback()?->name ??
                                    ($log->stampSeries?->name ?? __('Onbekende postzegelserie')),

                                default => __('Onbekend record'),
                            };

                            $recordType = match ($log->record_type) {
                                'stamp_catalog_entry' => __('Catalogusvermelding'),
                                'stamp_catalog_value' => __('Cataloguswaarde postzegel'),
                                'series_catalog_value' => __('Cataloguswaarde postzegelserie'),
                                default => $log->record_type,
                            };

                            $conditionName =
                                $log->condition_id !== null
                                    ? $conditionsById->get($log->condition_id)?->translationWithFallback()?->name ??
                                        __('Onbekende waarderingscategorie')
                                    : null;

                            $actionLabel = match ($log->action) {
                                'created' => __('Aangemaakt'),
                                'updated' => __('Gewijzigd'),
                                'deleted' => __('Verwijderd'),
                                default => $log->action,
                            };
                        @endphp

                        <div class="rounded-lg border border-theme-accent-3 bg-brown-6 overflow-hidden">

                            {{-- Wijzigingskop --}}
                            <div
                                class="flex flex-col justify-between gap-3 border-b border-theme-accent-3 bg-brown-5 px-4 py-3 sm:flex-row sm:items-center">

                                <div>

                                    <flux:heading size="md">
                                        {{ $recordName }}
                                    </flux:heading>

                                    <flux:text class="mt-1 text-sm text-zinc-900!">
                                        {{ $recordType }}
                                        ·
                                        {{ __('Recordnummer') }}:
                                        {{ $log->record_id }}

                                        @if ($conditionName !== null)
                                            ·
                                            {{ __('Waarderingscategorie') }}:
                                            {{ $conditionName }}
                                        @endif
                                    </flux:text>
                                </div>

                                <span @class([
                                    'inline-flex w-fit rounded-full px-3 py-1 text-xs font-medium',
                                    'bg-green-100 text-green-800' => $log->action === 'created',
                                    'bg-blue-100 text-blue-800' => $log->action === 'updated',
                                    'bg-red-100 text-red-800' => $log->action === 'deleted',
                                    'bg-neutral-100 text-neutral-700' => !in_array($log->action, [
                                        'created',
                                        'updated',
                                        'deleted',
                                    ]),
                                ])>

                                    {{ $actionLabel }}

                                </span>

                            </div>

                            {{-- Oude en nieuwe waarden --}}
                            <div>

                                @if (count(array_diff($fields, $hiddenFields)) === 0)
                                    <flux:text class="p-4 text-sm text-zinc-700!">
                                        {{ __('Er zijn geen aanvullende veldgegevens beschikbaar.') }}
                                    </flux:text>
                                @else
                                    <div class="overflow-x-auto">

                                        <table class="w-full table-fixed text-left text-sm">
                                            <thead class="bg-brown-3 text-zinc-800 border-b border-theme-accent-3">

                                                <tr>

                                                    <th class="w-[30%] px-4 py-3 text-left font-semibold">
                                                        {{ __('Veld') }}
                                                    </th>

                                                    <th class="w-[35%] px-4 py-3 text-left font-semibold">
                                                        {{ __('Oude waarde') }}
                                                    </th>

                                                    <th class="w-[35%] px-4 py-3 text-left font-semibold">
                                                        {{ __('Nieuwe waarde') }}
                                                    </th>

                                                </tr>

                                            </thead>

                                            <tbody class="divide-y divide-brown-4 dark:divide-neutral-700">

                                                @foreach ($fields as $field)
                                                    @continue(in_array($field, $hiddenFields, true))

                                                    @php
                                                        $oldValue = $oldValues[$field] ?? null;
                                                        $newValue = $newValues[$field] ?? null;

                                                        if ($field === 'catalog_value') {
                                                            $oldValue = is_numeric($oldValue)
                                                                ? \Illuminate\Support\Number::format(
                                                                    (float) $oldValue,
                                                                    precision: 2,
                                                                    locale: app()->getLocale(),
                                                                )
                                                                : $oldValue;

                                                            $newValue = is_numeric($newValue)
                                                                ? \Illuminate\Support\Number::format(
                                                                    (float) $newValue,
                                                                    precision: 2,
                                                                    locale: app()->getLocale(),
                                                                )
                                                                : $newValue;
                                                        }

                                                        if ($field === 'condition_id') {
                                                            $oldValue =
                                                                $oldValue !== null
                                                                    ? $conditionsById
                                                                            ->get($oldValue)
                                                                            ?->translationWithFallback()?->name ??
                                                                        $oldValue
                                                                    : null;

                                                            $newValue =
                                                                $newValue !== null
                                                                    ? $conditionsById
                                                                            ->get($newValue)
                                                                            ?->translationWithFallback()?->name ??
                                                                        $newValue
                                                                    : null;
                                                        }

                                                        if ($field === 'needs_review') {
                                                            $oldValue =
                                                                $oldValue === null
                                                                    ? null
                                                                    : ($oldValue
                                                                        ? __('Ja')
                                                                        : __('Nee'));

                                                            $newValue =
                                                                $newValue === null
                                                                    ? null
                                                                    : ($newValue
                                                                        ? __('Ja')
                                                                        : __('Nee'));
                                                        }
                                                    @endphp

                                                    <tr class="transition-colors hover:bg-brown-5!">

                                                        <td class="px-4 py-3 font-medium">
                                                            {{ $fieldLabels[$field] ?? str_replace('_', ' ', ucfirst($field)) }}
                                                        </td>

                                                        <td class="px-4 py-3">
                                                            @if ($oldValue === null || $oldValue === '')
                                                                <span class="text-neutral-400">—</span>
                                                            @else
                                                                {{ $oldValue }}
                                                            @endif
                                                        </td>

                                                        <td class="px-4 py-3">
                                                            @if ($newValue === null || $newValue === '')
                                                                <span class="text-neutral-400">—</span>
                                                            @else
                                                                {{ $newValue }}
                                                            @endif
                                                        </td>

                                                    </tr>
                                                @endforeach

                                            </tbody>

                                        </table>

                                    </div>
                                @endif

                            </div>

                        </div>
                    @endforeach

                @endif

            </div>

            {{-- Paginering --}}
            @if ($logs->hasPages())
                <div class="border-t border-theme-accent-1 px-4 py-4">

                    {{ $logs->links() }}

                </div>
            @endif

        </section>

        {{-- Terug naar geschiedenis --}}
        <div
            class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5 flex flex-col justify-end gap-4 sm:flex-row sm:items-center">

            <flux:button :href="route('backend.catalogs.editions.history.index', [$catalog, $edition])" variant="ghost"
                icon="arrow-left">

                {{ __('Terug naar geschiedenis') }}

            </flux:button>

        </div>

    </div>

</x-layouts::app>
