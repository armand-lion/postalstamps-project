<x-layouts::app :title="__('Cataloguswaarden bulk bewerken')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Succesmelding na het opslaan --}}
        @if (session('success'))
            <div role="status" class="rounded-lg border border-green-600 bg-green-50 px-4 py-3 text-sm text-green-800">
                <div class="flex items-center gap-2">

                    <flux:icon.check-circle class="size-5 shrink-0" />

                    <span>
                        {{ session('success') }}
                    </span>

                </div>
            </div>
        @endif

        {{-- Paginatitel --}}
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

            <flux:heading size="xl">
                {{ __('Cataloguswaarden bulk bewerken') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Bewerk de catalogusnummers en cataloguswaarden van meerdere postzegels tegelijk.') }}
            </flux:text>

            <flux:text class="mt-2 font-medium">
                {{ $catalog->name }} — {{ $edition->edition_name }}
            </flux:text>

        </div>

        {{-- Formulier voor bulkbewerking --}}
        <form method="POST" action="{{ route('backend.catalogs.editions.bulk-update', [$catalog, $edition]) }}"
            x-data="{
                isSubmitting: false,
                isLeaving: false,
                pendingUrl: null,
            
                get hasUnsavedChanges() {
                    return Array.from(
                        this.$el.querySelectorAll('tr[x-data]')
                    ).some(row => {
                        return Alpine.$data(row).isDirty === true;
                    });
                },
            
                confirmLeave() {
                    if (!this.pendingUrl) {
                        return;
                    }
            
                    this.isLeaving = true;
            
                    // Schakel de browserwaarschuwing uit voor deze bevestigde navigatie.
                    window.onbeforeunload = null;
            
                    window.location.assign(this.pendingUrl);
                }
            }"
            x-on:beforeunload.window="
                const formData = Alpine.$data($el);

                if (
                    formData.hasUnsavedChanges &&
                    !formData.isSubmitting &&
                    !formData.isLeaving
                ) {
                    $event.preventDefault();
                    $event.returnValue = '';
                }
            "
            x-on:livewire:navigate.window="
                const formData = Alpine.$data($el);

                if (
                    formData.hasUnsavedChanges &&
                    !formData.isSubmitting &&
                    !formData.isLeaving
                ) {
                    $event.preventDefault();

                    formData.pendingUrl = $event.detail.url;

                    $flux.modal('unsaved-bulk-changes').show();
                }
            "
            x-on:submit="
                if ($event.target.checkValidity()) {
                    Alpine.$data($el).isSubmitting = true;
                }
            "
            class="flex flex-col gap-6" novalidate>
            @csrf
            @method('PATCH')

            {{-- Validatiefouten --}}
            @if ($errors->any())
                <div role="alert" class="mb-6 rounded-lg border border-red-500 bg-red-50 p-4 text-red-700">

                    <p class="mb-2 font-semibold">
                        {{ __('De ingevoerde gegevens bevatten fouten.') }}
                    </p>

                    <ul class="list-inside list-disc space-y-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>

                </div>
            @endif

            {{-- Overzicht postzegels --}}
            <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

                <flux:heading size="lg">
                    {{ __('Postzegels') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Overzicht van de postzegels die bij deze catalogus horen.') }}
                </flux:text>

                {{-- Waarderingscategorieën voor de tabelkolommen --}}
                @php
                    $tableConditions = $stamps
                        ->getCollection()
                        ->flatMap(fn($stamp) => $stamp->applicableConditionRules)
                        ->unique('condition_id')
                        ->sortBy('sort_order')
                        ->values();
                @endphp

                {{-- Compacte tabel voor bulkbewerking --}}
                <div class="mt-6 overflow-x-auto rounded-lg border border-theme-accent-1">

                    <table class="w-full border-collapse text-left text-sm">

                        <thead class="bg-brown-5">
                            <tr class="border-b border-theme-accent-1">

                                <th scope="col" class="min-w-48 px-3 py-3 font-semibold">
                                    {{ __('Postzegel') }}
                                </th>

                                <th scope="col" class="w-24 px-3 py-3 font-semibold">
                                    {{ __('Jaar') }}
                                </th>

                                <th scope="col" class="min-w-48 px-3 py-3 font-semibold">
                                    {{ __('Catalogusnummer') }}
                                </th>

                                @foreach ($tableConditions as $rule)
                                    <th scope="col" class="min-w-40 px-3 py-3 font-semibold">
                                        {{ $rule->condition->translationWithFallback()?->name ?? $rule->condition->slug }}
                                    </th>
                                @endforeach

                                {{-- Status van de cataloguswaarden --}}
                                <th scope="col" class="w-50 min-w-50 px-3 py-3 font-semibold">
                                    {{ __('Status') }}
                                </th>

                            </tr>
                        </thead>

                        <tbody class="divide-y divide-theme-accent-1">

                            @forelse ($stamps as $stamp)

                                @php
                                    $catalogEntry = $stamp->catalogEntries->first();

                                    $applicableConditionIds = $stamp->applicableConditionRules->pluck('condition_id');
                                @endphp

                                <tr x-data="{
                                    catalogNumber: @js(old("stamps.{$stamp->id}.catalog_number", $catalogEntry?->catalog_number ?? '')),
                                    originalCatalogNumber: @js($catalogEntry?->catalog_number ?? ''),
                                    requiredConditionIds: @js($applicableConditionIds->map(fn($id) => (int) $id)->values()->all()),
                                
                                    catalogValues: @js(
    collect($applicableConditionIds)
        ->mapWithKeys(function ($conditionId) use ($stamp, $catalogEntry) {
            $catalogValue = $catalogEntry?->values->firstWhere('condition_id', $conditionId);

            return [
                $conditionId => old("stamps.{$stamp->id}.values.{$conditionId}", $catalogValue?->catalog_value),
            ];
        })
        ->all(),
),
                                    originalCatalogValues: @js(
    collect($applicableConditionIds)
        ->mapWithKeys(function ($conditionId) use ($catalogEntry) {
            $catalogValue = $catalogEntry?->values->firstWhere('condition_id', $conditionId);

            return [
                $conditionId => $catalogValue?->catalog_value,
            ];
        })
        ->all(),
),
                                
                                    get isLinked() {
                                        return this.catalogNumber.trim() !== '';
                                    },
                                
                                    get isDirty() {
                                        // Controleer of het catalogusnummer is gewijzigd.
                                        if (
                                            String(this.catalogNumber ?? '').trim() !==
                                            String(this.originalCatalogNumber ?? '').trim()
                                        ) {
                                            return true;
                                        }
                                
                                        // Controleer alle toepasselijke cataloguswaarden.
                                        return this.requiredConditionIds.some(conditionId => {
                                            const currentValue = this.catalogValues[conditionId];
                                            const originalValue = this.originalCatalogValues[conditionId];
                                
                                            // Lege waarden worden gelijk behandeld.
                                            const normalize = value => {
                                                if (value === null || value === undefined || String(value).trim() === '') {
                                                    return '';
                                                }
                                
                                                const stringValue = String(value).trim();
                                
                                                // Vergelijk geldige getallen op numerieke waarde.
                                                if (/^\d+(\.\d{1,2})?$/.test(stringValue)) {
                                                    return Number(stringValue).toFixed(2);
                                                }
                                
                                                return stringValue;
                                            };
                                
                                            return normalize(currentValue) !== normalize(originalValue);
                                        });
                                    },
                                
                                    get requiredCount() {
                                        return this.requiredConditionIds.length;
                                    },
                                
                                    get filledCount() {
                                        return this.requiredConditionIds.filter(conditionId => {
                                            const value = this.catalogValues[conditionId];
                                
                                            return value !== null &&
                                                value !== undefined &&
                                                String(value).trim() !== '';
                                        }).length;
                                    },
                                    get validCount() {
                                        return this.requiredConditionIds.filter(conditionId => {
                                            const value = this.catalogValues[conditionId];
                                
                                            if (value === null || value === undefined) {
                                                return false;
                                            }
                                
                                            const stringValue = String(value).trim();
                                
                                            if (stringValue === '') {
                                                return false;
                                            }
                                
                                            // Alleen niet-negatieve getallen met maximaal twee decimalen.
                                            return /^\d+(\.\d{1,2})?$/.test(stringValue);
                                        }).length;
                                    },
                                    get catalogStatus() {
                                        if (!this.isLinked) {
                                            return this.filledCount > 0 ?
                                                'incomplete' :
                                                'not_linked';
                                        }
                                
                                        if (this.requiredCount === 0) {
                                            return 'no_rules';
                                        }
                                
                                        return this.validCount === this.requiredCount ?
                                            'complete' :
                                            'incomplete';
                                    }
                                }" class="bg-brown-6 hover:bg-brown-5">


                                    {{-- Postzegelnaam --}}
                                    <td class="px-3 py-2 align-middle">
                                        <span class="font-medium">
                                            {{ $stamp->name }}
                                        </span>
                                    </td>

                                    {{-- Uitgiftejaar --}}
                                    <td class="px-3 py-2 align-middle">
                                        {{ $stamp->issue_year }}
                                    </td>

                                    {{-- Catalogusnummer --}}
                                    <td class="px-3 py-2 align-middle">

                                        <input type="text" name="stamps[{{ $stamp->id }}][catalog_number]"
                                            x-model="catalogNumber" maxlength="100"
                                            aria-label="{{ __('Catalogusnummer') }} - {{ $stamp->name }}"
                                            class="block h-10 w-44 rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900
                                focus:border-theme-accent-3
                                focus:outline-none
                                focus:ring-2
                                focus:ring-theme-accent-3
                                focus:ring-offset-2
                                focus:ring-offset-brown-5" />

                                    </td>

                                    {{-- Dynamische cataloguswaarden --}}
                                    @foreach ($tableConditions as $rule)
                                        <td class="px-3 py-2 align-middle">

                                            @if ($applicableConditionIds->contains($rule->condition_id))
                                                @php
                                                    $catalogValue = $catalogEntry?->values->firstWhere(
                                                        'condition_id',
                                                        $rule->condition_id,
                                                    );
                                                @endphp


                                                <div x-data="{
                                                    value: @js(old("stamps.{$stamp->id}.values.{$rule->condition_id}", $catalogValue !== null ? number_format((float) $catalogValue->catalog_value, 2, '.', '') : '')),
                                                    increase() {
                                                        this.value = (
                                                            Math.round((Number(this.value || 0) + 0.01) * 100) / 100
                                                        ).toFixed(2);
                                                
                                                        catalogValues[{{ $rule->condition_id }}] = this.value;
                                                    },
                                                    decrease() {
                                                        this.value = (
                                                            Math.max(
                                                                0,
                                                                Math.round((Number(this.value || 0) - 0.01) * 100) / 100
                                                            )
                                                        ).toFixed(2);
                                                
                                                        catalogValues[{{ $rule->condition_id }}] = this.value;
                                                    }
                                                }" class="relative w-36">

                                                    <input type="number"
                                                        name="stamps[{{ $stamp->id }}][values][{{ $rule->condition_id }}]"
                                                        x-model="value" min="0" step="0.01" placeholder="0,00"
                                                        x-on:input="catalogValues[{{ $rule->condition_id }}] = value"
                                                        aria-label="{{ $rule->condition->translationWithFallback()?->name ?? $rule->condition->slug }} - {{ $stamp->name }}"
                                                        class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900
                                            placeholder:text-zinc-500
                                            focus:border-theme-accent-3
                                            focus:outline-none
                                            focus:ring-2
                                            focus:ring-theme-accent-3
                                            focus:ring-offset-2
                                            focus:ring-offset-brown-5
                                            [&::-webkit-inner-spin-button]:appearance-none
                                            [&::-webkit-outer-spin-button]:appearance-none
                                            [-moz-appearance:textfield]" />

                                                    {{-- Aangepaste pijltjes --}}
                                                    <div
                                                        class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                                        <button type="button" x-on:click="increase()"
                                                            aria-label="{{ __('Cataloguswaarde verhogen') }}"
                                                            class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                                            <flux:icon.chevron-up class="size-3" />
                                                        </button>

                                                        <button type="button" x-on:click="decrease()"
                                                            aria-label="{{ __('Cataloguswaarde verlagen') }}"
                                                            class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                                            <flux:icon.chevron-down class="size-3" />
                                                        </button>

                                                    </div>

                                                </div>
                                            @else
                                                <span class="text-zinc-500">—</span>
                                            @endif

                                        </td>
                                    @endforeach

                                    {{-- Status van de cataloguswaarden --}}
                                    <td class="px-3 py-2 align-middle">

                                        <span x-show="catalogStatus === 'not_linked'" x-cloak
                                            class="font-medium text-zinc-500 dark:text-zinc-400">
                                            {{ __('Niet gekoppeld') }}
                                        </span>

                                        <span x-show="catalogStatus === 'complete'" x-cloak
                                            class="font-medium text-green-600 dark:text-green-400">
                                            {{ __('Compleet') }}
                                        </span>

                                        <span x-show="catalogStatus === 'incomplete'" x-cloak
                                            class="font-medium text-amber-600 dark:text-amber-400">
                                            {{ __('Onvolledig') }}
                                        </span>

                                        <span x-show="catalogStatus === 'no_rules'" x-cloak
                                            class="font-medium text-zinc-500 dark:text-zinc-400">
                                            {{ __('Geen waarderingsregels') }}
                                        </span>
                                        <span x-cloak x-show="isDirty"
                                            class="mt-1 inline-flex items-center rounded-md
                                                border border-amber-500/40
                                                bg-amber-500/10 px-2 py-1
                                                text-xs font-medium text-amber-700
                                                dark:text-amber-400">
                                            {{ __('Gewijzigd') }}
                                        </span>

                                    </td>

                                </tr>

                            @empty

                                <tr>
                                    <td colspan="{{ 4 + $tableConditions->count() }}"
                                        class="bg-brown-6 px-4 py-6 text-center">
                                </tr>

                            @endforelse

                        </tbody>

                    </table>

                </div>

                {{-- Paginering --}}
                @if ($stamps->hasPages())
                    <div class="mt-6">
                        {{ $stamps->links() }}
                    </div>
                @endif

            </div>

            {{-- Formulieracties --}}
            <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">
                <div class="flex flex-wrap justify-end gap-3">

                    <flux:button :href="route('backend.catalogs.editions.show', [$catalog, $edition])" variant="ghost"
                        icon="arrow-left"
                        x-on:click.prevent="
                            const formData = Alpine.$data(
                                $event.currentTarget.closest('form')
                            );

                            if (formData.hasUnsavedChanges) {
                                formData.pendingUrl = $event.currentTarget.href;
                                $flux.modal('unsaved-bulk-changes').show();
                            } else {
                                window.location.assign($event.currentTarget.href);
                            }
                        ">
                        {{ __('Terug naar cataloguseditie') }}
                    </flux:button>

                    <flux:button type="submit" variant="primary" icon="check" class="bg-brown-2 hover:bg-brown-1">
                        {{ __('Wijzigingen opslaan') }}
                    </flux:button>

                </div>
            </div>

        </form>

        {{-- Bevestiging bij niet-opgeslagen wijzigingen --}}
        <flux:modal name="unsaved-bulk-changes" class="md:w-96 bg-brown-3!">

            <div class="space-y-6">

                <div>
                    <flux:heading size="lg">
                        {{ __('Niet-opgeslagen wijzigingen') }}
                    </flux:heading>

                    <flux:text class="mt-2 text-white">
                        {{ __('Je hebt wijzigingen aangebracht die nog niet zijn opgeslagen.') }}
                    </flux:text>

                    <flux:text class="mt-2 text-white">
                        {{ __('Weet je zeker dat je deze pagina wilt verlaten?') }}
                    </flux:text>
                </div>

                <div class="flex justify-end gap-3">

                    <flux:modal.close>
                        <flux:button variant="ghost">
                            {{ __('Op pagina blijven') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button type="button" variant="danger"
                        x-on:click="
                            Alpine.$data(
                                document.querySelector('form[x-data]')
                            ).confirmLeave()
                        ">
                        {{ __('Pagina verlaten') }}
                    </flux:button>

                </div>

            </div>

        </flux:modal>

    </div>

</x-layouts::app>
