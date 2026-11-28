<x-layouts::app :title="__('Wijzigingsdetails postzegels')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div
            class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5 flex flex-col justify-between gap-4 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ __('Wijzigingsdetails postzegels') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Details van de geregistreerde wijzigingen binnen deze bewerking.') }}
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-3">

                <flux:button :href="route('backend.stamps.history.index')" variant="ghost" icon="arrow-left">

                    {{ __('Terug naar geschiedenis') }}

                </flux:button>

            </div>

        </div>

        @include('partials.flash-messages')

        {{-- Bewerkingsinformatie --}}
        <section class="rounded-xl border border-theme-accent-1 bg-brown-6 overflow-hidden">

            <div class="border-b border-theme-accent-1 px-4 py-3">

                <flux:heading size="lg">
                    {{ __('Bewerkingsinformatie') }}
                </flux:heading>

            </div>

            <div class="grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3">

                {{-- Datum en tijd --}}
                <div>
                    <flux:text class="text-sm">
                        {{ __('Datum en tijd') }}
                    </flux:text>

                    <flux:text class="mt-1 font-medium">
                        {{ $batch->created_at?->format('d-m-Y H:i:s') ?? '—' }}
                    </flux:text>
                </div>

                {{-- Bewerking --}}
                <div>
                    <flux:text class="text-sm">
                        {{ __('Bewerking') }}
                    </flux:text>

                    <flux:text class="mt-1 font-medium text-zinc-900!">
                        {{ match ($batch->operation) {
                            'bulk_edit' => __('Bulkbewerking'),
                            default => $batch->operation,
                        } }}
                    </flux:text>
                </div>

                {{-- Aantal gewijzigde postzegels --}}
                <div>
                    <flux:text class="text-sm">
                        {{ __('Gewijzigde postzegels') }}
                    </flux:text>

                    <flux:text class="mt-1 font-medium text-zinc-900!">
                        {{ $batch->stamp_count }}
                    </flux:text>
                </div>

                {{-- Uitgevoerd door --}}
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

                {{-- Omschrijving --}}
                <div class="sm:col-span-2">
                    <flux:text class="text-sm">
                        {{ __('Omschrijving') }}
                    </flux:text>

                    <flux:text class="mt-1 font-medium text-zinc-900!">
                        {{ $batch->description ? __($batch->description) : '—' }}
                    </flux:text>
                </div>

            </div>

        </section>

        {{-- Veldnamen en waardeopmaak --}}
        @php
            $fieldLabels = [
                'country_id' => __('Land of gebied'),
                'issue_year' => __('Uitgiftejaar'),
                'issue_date' => __('Uitgiftedatum'),
                'stamp_series_id' => __('Postzegelserie'),
                'face_value' => __('Nominale waarde'),
                'currency' => __('Valuta'),
                'designer' => __('Ontwerper'),
                'printing_method' => __('Druktechniek'),
                'notes' => __('Opmerkingen'),
                'theme_ids' => __("Thema's"),
            ];

            $resolveName = function ($model, $id) {
                return $model?->translationWithFallback()?->name ??
                    ($model?->name ?? __('Onbekend (ID: :id)', ['id' => $id]));
            };

            $formatValue = function ($field, $value) use ($countriesById, $seriesById, $themesById, $resolveName) {
                if ($value === null || $value === '') {
                    return null;
                }

                if ($field === 'country_id') {
                    return $resolveName($countriesById->get($value), $value);
                }

                if ($field === 'stamp_series_id') {
                    return $resolveName($seriesById->get($value), $value);
                }

                if ($field === 'theme_ids') {
                    if ($value === []) {
                        return __('Geen thema’s');
                    }

                    return collect($value)->map(fn($id) => $resolveName($themesById->get($id), $id))->implode("\n");
                }

                if ($field === 'issue_date') {
                    return \Illuminate\Support\Carbon::parse($value)->format('d-m-Y');
                }

                return $value;
            };
        @endphp

        {{-- Geregistreerde wijzigingen --}}
        <section class="flex flex-col gap-4 overflow-hidden rounded-xl border border-theme-accent-1 bg-brown-6">

            <div class="border-b border-theme-accent-1 px-4 py-3">

                <flux:heading size="lg">
                    {{ __('Geregistreerde wijzigingen') }}
                </flux:heading>

                <flux:text class="mt-1">
                    {{ __('Hieronder worden de oude en nieuwe gegevens per postzegel weergegeven.') }}
                </flux:text>

            </div>

            <div class="flex flex-col gap-4 p-4">

                @forelse ($logs as $log)

                    @php
                        $oldValues = $log->old_values ?? [];
                        $newValues = $log->new_values ?? [];

                        $fields = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

                        $stampName =
                            $log->stamp?->translationWithFallback()?->name ?? ($log->stamp?->name ?? $log->stamp_name);
                    @endphp

                    <div class="rounded-lg border border-theme-accent-3 bg-brown-6 overflow-hidden">

                        {{-- Wijzigingskop --}}
                        <div
                            class="flex flex-col justify-between gap-3 border-b border-theme-accent-3 bg-brown-5 px-4 py-3 sm:flex-row sm:items-center">

                            <div>

                                <flux:heading size="md">
                                    {{ $stampName }}
                                </flux:heading>

                                <flux:text class="mt-1 text-sm text-zinc-900!">

                                    {{ __('Postzegelnummer') }}:
                                    {{ $log->stamp_id }}

                                    @if ($log->stamp === null)
                                        · {{ __('Postzegel verwijderd') }}
                                    @endif

                                </flux:text>

                            </div>

                            <span
                                class="inline-flex w-fit rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800">

                                {{ __('Gewijzigd') }}

                            </span>

                        </div>

                        {{-- Oude en nieuwe waarden --}}
                        @if ($fields === [])
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
                                            @php
                                                $oldValue = $formatValue($field, $oldValues[$field] ?? null);

                                                $newValue = $formatValue($field, $newValues[$field] ?? null);
                                            @endphp

                                            <tr class="transition-colors hover:bg-brown-5!">

                                                {{-- Veldnaam --}}
                                                <td class="px-4 py-3 align-top font-medium">
                                                    {{ $fieldLabels[$field] ?? str_replace('_', ' ', ucfirst($field)) }}
                                                </td>

                                                {{-- Oude waarde --}}
                                                <td
                                                    class="whitespace-pre-line wrap-break-word px-4 py-3 align-top text-red-700">

                                                    @if ($oldValue === null)
                                                        <span class="text-neutral-400">—</span>
                                                    @else
                                                        {{ $oldValue }}
                                                    @endif

                                                </td>

                                                {{-- Nieuwe waarde --}}
                                                <td
                                                    class="whitespace-pre-line wrap-break-word px-4 py-3 align-top text-green-700">

                                                    @if ($newValue === null)
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

                @empty

                    <div class="py-8 text-center">

                        <flux:heading size="lg">
                            {{ __('Geen wijzigingen gevonden') }}
                        </flux:heading>

                        <flux:text class="mt-2">
                            {{ __('Er zijn geen afzonderlijke wijzigingen bij deze bewerking geregistreerd.') }}
                        </flux:text>

                    </div>

                @endforelse

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

            <flux:button :href="route('backend.stamps.history.index')" variant="ghost" icon="arrow-left">

                {{ __('Terug naar geschiedenis') }}

            </flux:button>

        </div>

    </div>

</x-layouts::app>
