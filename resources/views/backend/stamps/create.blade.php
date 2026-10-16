<x-layouts::app :title="__('Postzegel toevoegen')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div>
            <flux:heading size="xl">
                {{ __('Postzegel toevoegen') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Voeg een postzegel toe aan je catalogus.') }}
            </flux:text>
        </div>

        {{-- Validatiefouten --}}
        @if ($errors->any())
            <div
                class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Formulier --}}
        <form method="POST" action="{{ route('backend.stamps.store') }}" class="flex flex-col gap-6">
            @csrf

            <div class="rounded-xl border border-neutral-200 p-4 dark:border-neutral-700 md:p-6">
                <flux:heading size="lg">
                    {{ __('Algemene gegevens') }}
                </flux:heading>

                <div class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                    {{-- Naam --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Naam') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>
                        <flux:input name="name" value="{{ old('name') }}" required autofocus />
                        <flux:error name="name" />
                    </flux:field>

                    {{-- Land --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Land') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>
                        <flux:select name="country_id" required>
                            <option value="">{{ __('Selecteer een land') }}</option>

                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}" @selected((string) old('country_id') === (string) $country->id)>
                                    {{ $country->name }}
                                </option>
                            @endforeach
                        </flux:select>
                        <flux:error name="country_id" />
                    </flux:field>

                    {{-- Serie --}}
                    <flux:field>
                        <flux:label>{{ __('Postzegelserie') }}</flux:label>
                        <flux:select name="stamp_series_id">
                            <option value="">{{ __('Geen serie') }}</option>

                            @foreach ($series as $item)
                                <option value="{{ $item->id }}" @selected((string) old('stamp_series_id') === (string) $item->id)>
                                    {{ $item->name }}
                                </option>
                            @endforeach
                        </flux:select>
                        <flux:error name="stamp_series_id" />
                    </flux:field>

                    {{-- Uitgiftejaar --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Uitgiftejaar') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>
                        <flux:input type="number" name="issue_year" min="1840" max="2200"
                            value="{{ old('issue_year') }}" />
                        <flux:error name="issue_year" />
                    </flux:field>

                    {{-- Uitgiftedatum --}}
                    <flux:field>
                        <flux:label>{{ __('Uitgiftedatum') }}</flux:label>
                        <flux:input type="date" name="issue_date" value="{{ old('issue_date') }}" />
                        <flux:error name="issue_date" />
                    </flux:field>

                    {{-- Nominale waarde --}}
                    <flux:field>
                        <flux:label>{{ __('Nominale waarde') }}</flux:label>
                        <flux:input type="number" name="face_value" min="0" step="0.01"
                            value="{{ old('face_value') }}" />
                        <flux:error name="face_value" />
                    </flux:field>

                    {{-- Valuta --}}
                    <flux:field>
                        <flux:label>{{ __('Valuta') }}</flux:label>
                        <flux:input name="currency" maxlength="3" value="{{ old('currency') }}" placeholder="EUR" />
                        <flux:error name="currency" />
                    </flux:field>

                    {{-- Ontwerper --}}
                    <flux:field>
                        <flux:label>{{ __('Ontwerper') }}</flux:label>
                        <flux:input name="designer" value="{{ old('designer') }}" />
                        <flux:error name="designer" />
                    </flux:field>

                    {{-- Druktechniek --}}
                    <flux:field>
                        <flux:label>{{ __('Druktechniek') }}</flux:label>
                        <flux:input name="printing_method" value="{{ old('printing_method') }}" />
                        <flux:error name="printing_method" />
                    </flux:field>

                    {{-- Catalogusreferentie --}}
                    <flux:field>
                        <flux:label>{{ __('Catalogusreferentie') }}</flux:label>
                        <flux:input name="catalog_reference" value="{{ old('catalog_reference') }}" />
                        <flux:error name="catalog_reference" />
                    </flux:field>

                    {{-- Beschrijving --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>{{ __('Beschrijving') }}</flux:label>
                        <flux:textarea name="description" rows="4">{{ old('description') }}</flux:textarea>
                        <flux:error name="description" />
                    </flux:field>

                    {{-- Notities --}}
                    <flux:field class="md:col-span-2">
                        <flux:label>{{ __('Notities') }}</flux:label>
                        <flux:textarea name="notes" rows="4">{{ old('notes') }}</flux:textarea>
                        <flux:error name="notes" />
                    </flux:field>

                </div>
            </div>

            {{-- Acties --}}
            <div class="flex flex-wrap justify-end gap-3">
                <flux:button :href="route('backend.stamps.index')" variant="ghost">
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button type="submit" variant="primary" icon="check">
                    {{ __('Postzegel opslaan') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
