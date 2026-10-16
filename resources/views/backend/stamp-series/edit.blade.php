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

            <flux:button
                :href="route('backend.stamp-series.show', $series)"
                variant="ghost"
                icon="arrow-left"
            >
                {{ __('Terug naar serie') }}
            </flux:button>
        </div>

        {{-- Validatiefouten --}}
        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
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
        <form
            method="POST"
            action="{{ route('backend.stamp-series.update', $series) }}"
            class="flex flex-col gap-6"
        >
            @csrf
            @method('PUT')

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">

                <flux:heading size="lg">
                    {{ __('Algemene gegevens') }}
                </flux:heading>

                <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                    {{-- Land --}}
                    <div>
                        <label for="country_id" class="mb-2 block text-sm font-medium">
                            {{ __('Land') }}
                            <span class="text-red-600">*</span>
                        </label>

                        <select
                            id="country_id"
                            name="country_id"
                            required
                            class="w-full rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                        >
                            <option value="">
                                {{ __('Selecteer een land') }}
                            </option>

                            @foreach ($countries as $country)
                                <option
                                    value="{{ $country->id }}"
                                    @selected((string) old('country_id', $series->country_id) === (string) $country->id)
                                >
                                    {{ $country->name }}
                                </option>
                            @endforeach
                        </select>

                        @error('country_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Naam --}}
                    <div>
                        <label for="name" class="mb-2 block text-sm font-medium">
                            {{ __('Naam van de postzegelserie') }}
                            <span class="text-red-600">*</span>
                        </label>

                        <flux:input
                            id="name"
                            name="name"
                            :value="old('name', $series->name)"
                            required
                        />

                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Uitgiftejaar --}}
                    <div>
                        <label for="issue_year" class="mb-2 block text-sm font-medium">
                            {{ __('Uitgiftejaar') }}
                        </label>

                        <flux:input
                            id="issue_year"
                            name="issue_year"
                            type="number"
                            min="1840"
                            max="2200"
                            :value="old('issue_year', $series->issue_year)"
                        />

                        @error('issue_year')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Beschrijving --}}
                    <div class="md:col-span-2">
                        <label for="description" class="mb-2 block text-sm font-medium">
                            {{ __('Beschrijving') }}
                        </label>

                        <flux:textarea
                            id="description"
                            name="description"
                            rows="4"
                        >{{ old('description', $series->description) }}</flux:textarea>

                        @error('description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- Acties --}}
            <div class="flex flex-col-reverse justify-end gap-3 sm:flex-row">

                <flux:button
                    :href="route('backend.stamp-series.show', $series)"
                    variant="ghost"
                >
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button
                    type="submit"
                    variant="primary"
                    icon="check"
                >
                    {{ __('Wijzigingen opslaan') }}
                </flux:button>

            </div>
        </form>
    </div>
</x-layouts::app>
