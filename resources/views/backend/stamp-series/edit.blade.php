<x-layouts::app :title="__('Postzegelserie bewerken')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ __('Postzegelserie bewerken') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Pas de gegevens van deze postzegelserie aan.') }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.stamp-series.show', $series)" variant="ghost" icon="arrow-left">
                {{ __('Terug naar serie') }}
            </flux:button>
        </div>

        {{-- Validatiefouten --}}
        @if ($errors->any())
            <div
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                <p class="font-medium">
                    {{ __('Controleer de volgende gegevens:') }}
                </p>

                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Formulier --}}
        <form method="POST" action="{{ route('backend.stamp-series.update', $series) }}" class="flex flex-col gap-6"
            novalidate>
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">

                <flux:heading size="lg">
                    {{ __('Algemene gegevens') }}
                </flux:heading>

                <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                    {{-- Land --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Land') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <flux:select name="country_id" required>
                            <option value="">
                                {{ __('Selecteer een land') }}
                            </option>

                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}" @selected((string) old('country_id', $series->country_id) === (string) $country->id)>
                                    {{ $country->name }}
                                </option>
                            @endforeach
                        </flux:select>

                        <div class="min-h-5">
                            <flux:error name="country_id" />
                        </div>
                    </flux:field>

                    {{-- Naam --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Naam van de postzegelserie') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <flux:input name="name" value="{{ old('name', $series->name) }}"
                            placeholder="{{ __('Bijvoorbeeld: Nederlandse molens') }}" required />

                        <div class="min-h-5">
                            <flux:error name="name" />
                        </div>
                    </flux:field>

                    {{-- Uitgiftejaar --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Uitgiftejaar') }}
                        </flux:label>

                        <flux:input type="number" name="issue_year" min="1840" max="2200"
                            value="{{ old('issue_year', $series->issue_year) }}"
                            placeholder="{{ __('Bijvoorbeeld 2026') }}" />

                        <div class="min-h-5">
                            <flux:error name="issue_year" />
                        </div>
                    </flux:field>

                    {{-- Beschrijving --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>
                            {{ __('Beschrijving') }}
                        </flux:label>

                        <flux:textarea name="description" rows="4"
                            placeholder="{{ __('Beschrijf deze postzegelserie...') }}">
                            {{ old('description', $series->description) }}</flux:textarea>

                        <div class="min-h-5">
                            <flux:error name="description" />
                        </div>
                    </flux:field>

                </div>
            </div>

            {{-- Acties --}}
            <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row">

                <flux:button :href="route('backend.stamp-series.show', $series)" variant="ghost" icon="x-mark">
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Wijzigingen opslaan') }}
                </flux:button>

            </div>
        </form>
    </div>
</x-layouts::app>
