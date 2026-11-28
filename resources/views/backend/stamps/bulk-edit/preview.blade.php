<x-layouts::app :title="__('Wijzigingsoverzicht bulkbewerking')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div
            class="flex flex-col justify-between gap-4 rounded-lg border border-theme-accent-1 bg-brown-6 p-5 sm:flex-row sm:items-center">

            <div>

                <flux:heading size="xl">
                    {{ __('Wijzigingsoverzicht bulkbewerking') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Controleer de voorgestelde wijzigingen voordat je deze definitief opslaat.') }}
                </flux:text>

            </div>

            <flux:button :href="route('backend.stamps.bulk-edit.edit')" icon="arrow-left"
                class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-4!">
                {{ __('Terug naar bulkbewerking') }}
            </flux:button>
        </div>

        @include('partials.flash-messages')

        {{-- Samenvatting --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">

            @foreach (['total' => __('Totaal geselecteerd'), 'changed' => __('Gewijzigd'), 'unchanged' => __('Ongewijzigd'), 'conflict' => __('Met conflicten'), 'excluded' => __('Uitgesloten')] as $key => $label)
                <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-4">

                    <flux:text class="font-medium">
                        {{ $label }}
                    </flux:text>

                    <div @class([
                        'mt-2 text-3xl font-semibold',
                        'text-zinc-900' => $key === 'total',
                        'text-green-700' => $key === 'changed',
                        'text-zinc-600' => $key === 'unchanged',
                        'text-red-700' => $key === 'conflict',
                        'text-amber-700' => $key === 'excluded',
                    ])>
                        {{ $summary[$key] }}
                    </div>

                </div>
            @endforeach
        </div>

        {{-- Algemene statusmelding --}}
        @if ($summary['conflict'] > 0)
            <div class="rounded-lg border border-red-400 bg-red-50 p-4">

                <flux:heading class="text-red-800!">
                    {{ __('Er zijn conflicten gevonden') }}
                </flux:heading>

                <flux:text class="mt-2 text-red-700!">
                    {{ __('Los de conflicten op of sluit de betreffende postzegels uit voordat je de bulkbewerking uitvoert.') }}
                </flux:text>

            </div>
        @elseif ($summary['changed'] === 0)
            <div class="rounded-lg border border-amber-400 bg-amber-50 p-4">

                <flux:heading class="text-amber-800!">
                    {{ __('Geen wijzigingen gevonden') }}
                </flux:heading>

                <flux:text class="mt-2 text-amber-700!">
                    {{ __('De gekozen bewerkingen leiden niet tot wijzigingen bij de geselecteerde postzegels.') }}
                </flux:text>

            </div>
        @else
            <div class="rounded-lg border border-green-400 bg-green-50 p-4">

                <flux:heading class="text-green-800!">
                    {{ __('Geen conflicten gevonden') }}
                </flux:heading>

                <flux:text class="mt-2 text-green-700!">
                    {{ __('Controleer de voorgestelde wijzigingen voordat je de bulkbewerking bevestigt.') }}
                </flux:text>

            </div>
        @endif

        {{-- Statusfilter --}}
        <form method="GET" action="{{ route('backend.stamps.bulk-edit.preview.show') }}"
            class="rounded-lg border border-theme-accent-1 bg-brown-6 p-4">

            <div class="w-full sm:max-w-xs" x-data="{
                selectedStatus: @js($selectedStatus),
                selectedName: @js($statusOptions[$selectedStatus])
            }">

                <flux:field>

                    <flux:label>
                        {{ __('Filter op status') }}
                    </flux:label>

                    <input type="hidden" name="status" x-model="selectedStatus">

                    <flux:dropdown class="w-full">

                        <flux:button icon:trailing="chevron-down"
                            class="w-full! min-w-0! max-w-full! overflow-hidden justify-between! [&>span]:min-w-0 [&>span]:flex-1 border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                            <span class="block truncate" x-text="selectedName"></span>

                        </flux:button>

                        <flux:menu class="max-h-80 min-w-64 overflow-y-auto border border-theme-accent-3! bg-brown-6!">

                            @foreach ($statusOptions as $value => $label)
                                <flux:menu.item class="text-zinc-900! hover:bg-brown-4! data-focus:bg-brown-4!"
                                    x-on:click="
                                selectedStatus = @js($value);
                                selectedName = @js($label);
                                $nextTick(() => $el.closest('form')?.requestSubmit());
                            ">

                                    {{ $label }}

                                </flux:menu.item>
                            @endforeach

                        </flux:menu>

                    </flux:dropdown>

                </flux:field>

            </div>

        </form>

        {{-- Wijzigingen per postzegel --}}
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

            <flux:heading size="lg">
                {{ __('Wijzigingen per postzegel') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Bekijk per postzegel welke gegevens worden gewijzigd en welke conflicten zijn gevonden.') }}
            </flux:text>

            <div class="mt-5 flex flex-col gap-4">

                @forelse ($filteredPreview as $row)
                    @php
                        $stamp = $row['stamp'];
                        $status = $row['status'];

                        $stampName = $stamp->translationWithFallback()?->name ?? $stamp->name;

                        $countryName = $stamp->country->translationWithFallback()?->name ?? $stamp->country->name;
                    @endphp

                    <div class="rounded-lg border border-theme-accent-3 bg-brown-5 p-4">

                        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">

                            <div>

                                <flux:heading>
                                    {{ $stampName }}
                                </flux:heading>

                                <flux:text class="mt-1">
                                    {{ $countryName }} — {{ $stamp->issue_year }}
                                </flux:text>

                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                <span @class([
                                    'w-fit rounded-md px-3 py-1 text-sm font-medium',
                                    'bg-green-100 text-green-800' => $status === 'changed',
                                    'bg-zinc-200 text-zinc-700' => $status === 'unchanged',
                                    'bg-red-100 text-red-800' => $status === 'conflict',
                                    'bg-amber-100 text-amber-800' => $status === 'excluded',
                                ])>
                                    {{ match ($status) {
                                        'changed' => __('Gewijzigd'),
                                        'unchanged' => __('Ongewijzigd'),
                                        'conflict' => __('Conflict'),
                                        'excluded' => __('Uitgesloten'),
                                    } }}
                                </span>

                                {{-- Postzegel uitsluiten of opnieuw opnemen --}}
                                <form method="POST"
                                    action="{{ route('backend.stamps.bulk-edit.preview.exclusion') }}">

                                    @csrf

                                    <input type="hidden" name="stamp_id" value="{{ $stamp->id }}">

                                    <input type="hidden" name="action"
                                        value="{{ $status === 'excluded' ? 'include' : 'exclude' }}">

                                    <flux:button type="submit" size="sm"
                                        :icon="$status === 'excluded' ? 'plus-circle' : 'minus-circle'"
                                        class="border border-theme-accent-3! bg-brown-6! text-zinc-900! hover:bg-brown-4!">

                                        {{ $status === 'excluded' ? __('Opnieuw opnemen') : __('Uitsluiten') }}

                                    </flux:button>

                                </form>
                            </div>
                        </div>

                        {{-- Conflicten --}}
                        @if ($row['conflicts'] !== [])
                            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 p-4">

                                <flux:heading class="text-red-800!">
                                    {{ __('Geconstateerde conflicten') }}
                                </flux:heading>

                                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-red-700">

                                    @foreach ($row['conflicts'] as $conflict)
                                        <li>
                                            {{ $conflict }}
                                        </li>
                                    @endforeach

                                </ul>

                            </div>
                        @endif

                        {{-- Gewijzigde algemene gegevens --}}
                        @if ($row['attribute_changes'] !== [])
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
                                    'notes' => __('Notities'),
                                ];
                            @endphp

                            <div class="mt-4 overflow-x-auto rounded-lg border border-theme-accent-3">

                                <table class="w-full text-left text-sm">

                                    <thead class="bg-brown-6">

                                        <tr>
                                            <th class="px-4 py-3 font-medium">
                                                {{ __('Veld') }}
                                            </th>

                                            <th class="px-4 py-3 font-medium">
                                                {{ __('Huidige waarde') }}
                                            </th>

                                            <th class="px-4 py-3 font-medium">
                                                {{ __('Nieuwe waarde') }}
                                            </th>
                                        </tr>

                                    </thead>

                                    <tbody class="divide-y divide-theme-accent-3">

                                        @foreach ($row['attribute_changes'] as $field => $change)
                                            @php
                                                $formatValue = function ($value) use ($field, $countries, $series) {
                                                    if ($value === null || $value === '') {
                                                        return __('Niet ingevuld');
                                                    }

                                                    $model = match ($field) {
                                                        'country_id' => $countries->get((int) $value),
                                                        'stamp_series_id' => $series->get((int) $value),
                                                        default => null,
                                                    };

                                                    if (in_array($field, ['country_id', 'stamp_series_id'], true)) {
                                                        if ($model === null) {
                                                            return __('Onbekend') . ' (ID: ' . $value . ')';
                                                        }

                                                        $translation =
                                                            $model->translations->firstWhere(
                                                                'locale',
                                                                app()->getLocale(),
                                                            ) ??
                                                            $model->translations->firstWhere(
                                                                'locale',
                                                                config('app.fallback_locale', 'nl'),
                                                            );

                                                        return $translation?->name ?? $model->name;
                                                    }

                                                    return $value;
                                                };
                                            @endphp
                                            <tr>

                                                <td class="px-4 py-3 font-medium">
                                                    {{ $fieldLabels[$field] ?? $field }}
                                                </td>

                                                <td class="whitespace-pre-line px-4 py-3 align-top text-red-700">
                                                    {{ $formatValue($change['old']) }}
                                                </td>

                                                <td class="whitespace-pre-line px-4 py-3 align-top text-green-700">
                                                    {{ $formatValue($change['new']) }}
                                                </td>

                                            </tr>
                                        @endforeach

                                    </tbody>

                                </table>

                            </div>
                        @endif

                        {{-- Gewijzigde themakoppelingen --}}
                        @if ($row['theme_changes'] !== null)
                            @php
                                $themeChanges = $row['theme_changes'];

                                $themeGroups = [
                                    'old' => __('Huidige thema’s'),
                                    'new' => __('Nieuwe thema’s'),
                                    'added' => __('Toegevoegde thema’s'),
                                    'removed' => __('Verwijderde thema’s'),
                                ];
                            @endphp

                            <div class="mt-4 rounded-lg border border-theme-accent-3 p-4">

                                <flux:heading>
                                    {{ __('Gewijzigde themakoppelingen') }}
                                </flux:heading>

                                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">

                                    @foreach ($themeGroups as $key => $label)
                                        <div class="rounded-lg border border-theme-accent-3 bg-brown-6 p-3">

                                            <flux:text class="font-medium">
                                                {{ $label }}
                                            </flux:text>

                                            <div class="mt-2 flex flex-wrap gap-2">

                                                @forelse ($themeChanges[$key] as $themeId)
                                                    @php
                                                        $theme = $themes->get($themeId);

                                                        $translation =
                                                            $theme?->translations->firstWhere(
                                                                'locale',
                                                                app()->getLocale(),
                                                            ) ??
                                                            $theme?->translations->firstWhere(
                                                                'locale',
                                                                config('app.fallback_locale', 'nl'),
                                                            );

                                                        $themeName =
                                                            $translation?->name ??
                                                            ($theme?->name ??
                                                                __('Onbekend') . ' (ID: ' . $themeId . ')');
                                                    @endphp

                                                    <span @class([
                                                        'rounded-md px-3 py-1 text-sm',
                                                        'border border-theme-accent-3 bg-brown-5 text-zinc-900' => in_array(
                                                            $key,
                                                            ['old', 'new'],
                                                            true),
                                                        'bg-green-100 text-green-800' => $key === 'added',
                                                        'bg-red-100 text-red-800' => $key === 'removed',
                                                    ])>
                                                        {{ $themeName }}
                                                    </span>

                                                @empty

                                                    <flux:text>
                                                        {{ __('Geen thema’s') }}
                                                    </flux:text>
                                                @endforelse

                                            </div>

                                        </div>
                                    @endforeach

                                </div>

                            </div>
                        @endif

                    </div>
                @empty

                    <div class="rounded-lg border border-theme-accent-3 bg-brown-5 p-6 text-center">

                        <flux:heading>
                            {{ __('Geen postzegels gevonden') }}
                        </flux:heading>

                        <flux:text class="mt-2">
                            {{ __('Er zijn geen postzegels met de geselecteerde status.') }}
                        </flux:text>

                    </div>
                @endforelse

                {{-- Paginering --}}
                @if ($filteredPreview->hasPages())
                    <div class="mt-4 border-t border-theme-accent-3 pt-4">
                        {{ $filteredPreview->links() }}
                    </div>
                @endif

            </div>

        </div>

        {{-- Definitieve bevestiging --}}
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

            <flux:heading size="lg">
                {{ __('Bulkbewerking bevestigen') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Controleer alle voorgestelde wijzigingen voordat je deze definitief opslaat.') }}
            </flux:text>

            @if ($canApply)
                <form method="POST" action="{{ route('backend.stamps.bulk-edit.apply') }}" class="mt-4"
                    x-data="{ confirmed: false }">

                    @csrf

                    <input type="hidden" name="preview_hash" value="{{ $previewHash }}">

                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">

                        <flux:checkbox name="confirmed" value="1" x-model="confirmed"
                            :label="__('Ik heb de voorgestelde wijzigingen gecontroleerd en wil deze definitief opslaan.')"
                            class="[&_[data-flux-checkbox-indicator]]:bg-brown-5! [&_[data-flux-checkbox-indicator]]:border-theme-accent-3!" />

                        <div class="flex shrink-0 justify-end">

                            <flux:button type="submit" icon="check" disabled x-bind:disabled="!confirmed"
                                class="border border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-4!">

                                {{ __('Bulkbewerking uitvoeren') }}

                            </flux:button>

                        </div>

                    </div>

                </form>
            @else
                <flux:text class="mt-4 text-amber-700!">
                    {{ __('De bulkbewerking kan momenteel niet worden uitgevoerd.') }}
                </flux:text>
            @endif

        </div>

    </div>

</x-layouts::app>
