<x-layouts::app :title="__('Watermerk bewerken')">
    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginaheader --}}
        <div
            class="flex flex-col justify-between gap-4 rounded-lg border border-theme-accent-1 bg-brown-6 p-5 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ __('Watermerk bewerken') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Wijzig de gegevens van het geselecteerde watermerk.') }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.watermarks.index')" variant="ghost" icon="arrow-left">
                {{ __('Terug naar overzicht') }}
            </flux:button>
        </div>

        {{-- Succesmelding --}}
        @if (session('success'))
            <div
                class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-900 dark:bg-green-950 dark:text-green-200">
                {{ session('success') }}
            </div>
        @endif

        {{-- Validatiefouten --}}
        @if ($errors->any())
            <div class="rounded-lg border border-red-300 bg-red-50 p-4 text-sm text-red-800">
                <p class="font-medium">
                    {{ __('Controleer de volgende velden:') }}
                </p>

                <ul class="mt-2 list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Formulier --}}
        <form method="POST" action="{{ route('backend.watermarks.update', $watermark) }}" enctype="multipart/form-data"
            class="flex flex-col gap-6" novalidate>
            @csrf
            @method('PUT')

            {{-- Basisgegevens --}}
            <section class="rounded-xl border border-theme-accent-1 bg-brown-6">

                <div class="border-b border-theme-accent-1 px-4 py-3">
                    <flux:heading size="lg">
                        {{ __('Basisgegevens') }}
                    </flux:heading>

                    <flux:text class="mt-1">
                        {{ __('Algemene gegevens van het watermerk.') }}
                    </flux:text>
                </div>

                <div class="grid gap-5 p-4 md:grid-cols-2">

                    {{-- Land --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Land of gebied') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        {{-- Landdropdown --}}
                        @php
                            $selectedCountryId = old('country_id', $watermark->country_id);
                            $selectedCountry = $countries->firstWhere('id', $selectedCountryId);
                        @endphp

                        <div x-data="{
                            selectedId: @js((string) $selectedCountryId),
                            selectedName: @js($selectedCountry?->translationWithFallback()?->name ?? __('Selecteer een land of gebied'))
                        }">
                            <input type="hidden" name="country_id" x-model="selectedId">

                            <flux:dropdown position="bottom" align="start" class="block w-full">

                                <flux:button type="button" icon-trailing="chevron-down"
                                    class="w-full min-w-0 justify-between! border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5! focus:ring-2! focus:ring-theme-accent-3! focus:ring-offset-2! focus:ring-offset-brown-5!">
                                    <span x-text="selectedName" class="min-w-0 truncate text-left"></span>
                                </flux:button>

                                <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">
                                    <flux:menu.item as="button" type="button"
                                        x-on:click="selectedId = ''; selectedName = @js(__('Selecteer een land of gebied'))"
                                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                        {{ __('Selecteer een land of gebied') }}
                                    </flux:menu.item>

                                    @foreach ($countries as $country)
                                        <flux:menu.item as="button" type="button"
                                            x-on:click="selectedId = @js((string) $country->id); selectedName = @js($country->translationWithFallback()?->name ?? $country->name)"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                            {{ $country->translationWithFallback()?->name ?? $country->name }}
                                        </flux:menu.item>
                                    @endforeach

                                </flux:menu>

                            </flux:dropdown>
                        </div>

                        <flux:description>
                            {{ __('Selecteer het land of gebied waartoe dit watermerk behoort.') }}
                        </flux:description>

                        <div class="min-h-5">
                            <flux:error name="country_id" />
                        </div>
                    </flux:field>

                    {{-- Slug --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Slug') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <flux:input name="slug" value="{{ old('slug', $watermark->slug) }}" maxlength="100" required
                            placeholder="{{ __('Bijvoorbeeld: staande-leeuw') }}"
                            class:input="border-theme-accent-3! bg-brown-5! text-zinc-900! placeholder:text-zinc-500! focus:border-theme-accent-3! focus:ring-offset-brown-5!" />

                        <flux:description>
                            {{ __('Unieke identificatie van het watermerk, bijvoorbeeld staande-leeuw.') }}
                        </flux:description>

                        <div class="min-h-5">
                            <flux:error name="slug" />
                        </div>
                    </flux:field>

                    {{-- Sorteervolgorde --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Sorteervolgorde') }}
                        </flux:label>

                        <div x-data="{
                            value: @js(old('sort_order', $watermark->sort_order)),
                            increase() {
                                this.value = Math.max(0, Number(this.value || 0) + 1);
                            },
                            decrease() {
                                this.value = Math.max(0, Number(this.value || 0) - 1);
                            }
                        }" class="relative">
                            <input type="number" name="sort_order" min="0" step="1" x-model="value"
                                class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]">

                            {{-- Aangepaste omhoog- en omlaagknoppen --}}
                            <div class="absolute inset-y-0 right-1 flex flex-col justify-center">
                                <button type="button" x-on:click="increase()"
                                    aria-label="{{ __('Sorteervolgorde verhogen') }}"
                                    class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                    <flux:icon.chevron-up class="size-3" />
                                </button>

                                <button type="button" x-on:click="decrease()"
                                    aria-label="{{ __('Sorteervolgorde verlagen') }}"
                                    class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                    <flux:icon.chevron-down class="size-3" />
                                </button>
                            </div>
                        </div>

                        <flux:description>
                            {{ __('Bepaal de volgorde waarin watermerken worden weergegeven. Een lager getal verschijnt eerder.') }}
                        </flux:description>

                        <div class="min-h-5">
                            <flux:error name="sort_order" />
                        </div>
                    </flux:field>

                    {{-- Actief --}}
                    <div class="md:col-span-2">
                        <flux:field>
                            <input type="hidden" name="is_active" value="0">
                            <flux:checkbox name="is_active" value="1"
                                :checked="(bool) old('is_active', $watermark->is_active)" :label="__('Actief')"
                                class="[&_[data-flux-checkbox-indicator]]:bg-brown-5! [&_[data-flux-checkbox-indicator]]:border-theme-accent-3!" />

                            <flux:description class="mt-2">
                                {{ __('Geef aan of dit watermerk actief in de catalogus gebruikt mag worden.') }}
                            </flux:description>

                            <div class="min-h-5">
                                <flux:error name="is_active" />
                            </div>

                        </flux:field>
                    </div>

                </div>
            </section>

            {{-- Vertalingen --}}
            <section class="rounded-xl border border-theme-accent-1 bg-brown-6">

                <div class="border-b border-theme-accent-1 px-4 py-3">
                    <flux:heading size="lg">
                        {{ __('Vertalingen') }}
                    </flux:heading>

                    <flux:text class="mt-1">
                        {{ __('Vul de naam en eventueel de beschrijving van het watermerk in voor iedere taal.') }}
                    </flux:text>
                </div>

                <div class="flex flex-col gap-6 p-4">

                    @php
                        $languageNames = [
                            'nl' => 'Nederlands',
                            'en' => 'English',
                            'de' => 'Deutsch',
                            'fr' => 'Français',
                            'es' => 'Español',
                        ];
                    @endphp

                    @foreach ($locales as $locale)
                        @php
                            $translation = $watermark->translations->firstWhere('locale', $locale);
                        @endphp

                        <div
                            class="grid gap-5 border-b border-theme-accent-1 pb-6 last:border-b-0 last:pb-0 md:grid-cols-2">

                            {{-- Taalnaam --}}
                            <div class="md:col-span-2">
                                <flux:heading>
                                    {{ $languageNames[$locale] ?? strtoupper($locale) }}
                                </flux:heading>
                            </div>

                            {{-- Vertaalde naam --}}
                            <flux:field>
                                <flux:label>
                                    <span class="flex items-center gap-1">
                                        {{ __('Naam') }}

                                        @if ($locale === app()->getLocale())
                                            <span class="text-red-600">*</span>
                                        @endif
                                    </span>
                                </flux:label>

                                <flux:input name="translations[{{ $locale }}][name]"
                                    value="{{ old('translations.' . $locale . '.name', $translation?->name) }}"
                                    maxlength="100" @required($locale === app()->getLocale())
                                    placeholder="{{ $languageNames[$locale] ?? strtoupper($locale) }}"
                                    class:input="border-theme-accent-3! bg-brown-5! text-zinc-900! placeholder:text-zinc-500! focus:border-theme-accent-3! focus:ring-offset-brown-5!" />

                                @if ($locale === app()->getLocale())
                                    <flux:description>
                                        {{ __('Deze naam is verplicht omdat dit de actieve applicatietaal is.') }}
                                    </flux:description>
                                @else
                                    <flux:description>
                                        {{ __('Optioneel. Laat leeg als je deze vertaling nog niet hebt.') }}
                                    </flux:description>
                                @endif

                                <div class="min-h-5">
                                    <flux:error name="translations.{{ $locale }}.name" />
                                </div>
                            </flux:field>

                            {{-- Vertaalde beschrijving --}}
                            <flux:field>
                                <flux:label>
                                    {{ __('Beschrijving') }}
                                </flux:label>

                                <flux:textarea name="translations[{{ $locale }}][description]" rows="3"
                                    class="border-theme-accent-3! bg-brown-5! text-zinc-900! focus:border-theme-accent-3! focus:ring-offset-brown-5!">
                                    {{ old("translations.$locale.description", $translation?->description) }}
                                </flux:textarea>

                                <flux:description>
                                    {{ __('Optioneel. Voeg een beschrijving van het watermerk toe.') }}
                                </flux:description>

                                <div class="min-h-5">
                                    <flux:error name="translations.{{ $locale }}.description" />
                                </div>
                            </flux:field>

                        </div>
                    @endforeach

                </div>

            </section>

            {{-- Watermerkafbeelding --}}
            <section class="rounded-xl border border-theme-accent-1 bg-brown-6">

                <div class="border-b border-theme-accent-1 px-4 py-3">
                    <flux:heading size="lg">
                        {{ __('Watermerkafbeelding') }}
                    </flux:heading>

                    <flux:text class="mt-1">
                        {{ __('Upload een afbeelding van het watermerk en voeg eventueel een beschrijving toe.') }}
                    </flux:text>
                </div>

                <div class="grid items-start gap-5 p-4 md:grid-cols-2">

                    {{-- Bestaande watermerkafbeelding --}}
                    @php
                        $primaryImage =
                            $watermark->images->firstWhere('is_primary', true) ?? $watermark->images->first();
                    @endphp

                    @if ($primaryImage)
                        <input type="hidden" name="remove_existing_image" id="remove-existing-image"
                            value="{{ old('remove_existing_image', '0') }}">
                        <div class="md:col-span-2">

                            <flux:text class="mb-3 font-semibold">
                                {{ __('Huidige afbeelding') }}
                            </flux:text>

                            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">

                                <img src="{{ asset('storage/' . $primaryImage->path) }}"
                                    alt="{{ $primaryImage->alt_text ?: $watermark->translationWithFallback()?->name ?? $watermark->slug }}"
                                    class="h-48 w-48 rounded-lg border border-theme-accent-3 bg-brown-5 object-contain">

                                <div>
                                    <flux:text class="font-semibold">
                                        {{ __('Alternatieve tekst') }}
                                    </flux:text>

                                    <flux:text class="mt-1">
                                        {{ $primaryImage->alt_text ?: '—' }}
                                    </flux:text>
                                    <div class="mt-4">
                                        <button type="button" id="remove-existing-image-button"
                                            onclick="toggleExistingWatermarkImage()"
                                            class="inline-flex cursor-pointer items-center gap-2 rounded-lg
               border border-theme-accent-3 bg-brown-2 px-4 py-2
               text-sm font-medium text-white
               transition-colors hover:bg-brown-1
               focus:outline-none focus:ring-2 focus:ring-theme-accent-3
               focus:ring-offset-2 focus:ring-offset-brown-5">

                                            <flux:icon.trash class="size-4" />

                                            {{ __('Bestaande afbeelding verwijderen') }}

                                        </button>
                                        <p id="existing-image-removal-message"
                                            class="mt-3 hidden text-sm font-medium text-red-700" role="status"
                                            aria-live="polite">
                                            {{ __('De bestaande afbeelding wordt verwijderd wanneer je de wijzigingen opslaat.') }}
                                        </p>
                                    </div>
                                </div>

                            </div>

                        </div>
                    @endif

                    {{-- Watermerkafbeelding uploaden --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Afbeelding') }}
                        </flux:label>

                        <div x-data="{
                            previewUrl: null,
                            selectedFileName: '',
                        
                            updatePreview(event) {
                                const file = event.target.files[0];
                                this.selectedFileName = file ? file.name : '';
                        
                                if (this.previewUrl) {
                                    URL.revokeObjectURL(this.previewUrl);
                                }
                        
                                this.previewUrl = file && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type) ?
                                    URL.createObjectURL(file) :
                                    null;
                            },
                        
                            removeImage() {
                                if (this.previewUrl) {
                                    URL.revokeObjectURL(this.previewUrl);
                                }
                        
                                this.previewUrl = null;
                                this.selectedFileName = '';
                                this.$refs.imageInput.value = '';
                            }
                        }">

                            <div
                                class="flex w-full items-center overflow-hidden rounded-lg border border-theme-accent-3 bg-brown-5">

                                <input type="file" name="image" id="watermark-image"
                                    accept="image/jpeg,image/png,image/webp" @change="updatePreview($event)"
                                    x-ref="imageInput" class="sr-only">

                                <label for="watermark-image" tabindex="0" role="button"
                                    @keydown.enter.prevent="$refs.imageInput.click()"
                                    @keydown.space.prevent="$refs.imageInput.click()"
                                    class="shrink-0 cursor-pointer border-r border-theme-accent-3 bg-brown-6 px-4 py-2.5
               text-sm font-medium text-zinc-900 hover:bg-brown-5
               focus:outline-none focus-visible:ring-2 focus-visible:ring-inset
               focus-visible:ring-theme-accent-3">

                                    {{ __('Bestand kiezen') }}

                                </label>

                                <span class="min-w-0 flex-1 truncate px-4 text-sm text-zinc-700"
                                    x-text="selectedFileName || @js(__('Geen bestand geselecteerd'))">
                                </span>

                            </div>

                            {{-- Preview van geselecteerde watermerkafbeelding --}}
                            <div x-show="previewUrl" x-cloak
                                class="mt-4 rounded-xl border border-theme-accent-3 bg-brown-5 p-4">

                                <p class="mb-3 text-sm font-medium text-zinc-900">
                                    {{ __('Voorbeeld van geselecteerde afbeelding') }}
                                </p>

                                <div
                                    class="flex min-h-48 items-center justify-center rounded-lg border border-theme-accent-3 bg-brown-6 p-4">

                                    <img :src="previewUrl" alt="{{ __('Voorbeeld watermerkafbeelding') }}"
                                        class="max-h-64 max-w-full rounded-lg object-contain">


                                </div>
                                {{-- Geselecteerde afbeelding verwijderen --}}
                                <div class="mt-4 flex justify-end">
                                    <button type="button" @click="removeImage()"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-lg
               border border-theme-accent-3 bg-brown-2 px-4 py-2
               text-sm font-medium text-white
               transition-colors hover:bg-brown-1
               focus:outline-none focus:ring-2 focus:ring-theme-accent-3
               focus:ring-offset-2 focus:ring-offset-brown-5">

                                        <flux:icon.trash class="size-4" />

                                        {{ __('Afbeelding verwijderen') }}
                                    </button>
                                </div>

                            </div>
                        </div>

                        {{-- Beschrijving onder het uploadveld --}}
                        <flux:description>
                            {{ __('Optioneel. Upload een JPG-, PNG- of WebP-afbeelding van het watermerk.') }}
                        </flux:description>

                        <div class="min-h-5">
                            <flux:error name="image" />
                        </div>

                    </flux:field>


                    {{-- Alternatieve tekst --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Alternatieve tekst') }}
                        </flux:label>

                        <flux:input name="image_alt_text"
                            value="{{ old('image_alt_text', $primaryImage?->alt_text) }}" maxlength="255"
                            placeholder="{{ __('Bijvoorbeeld: Watermerk met staande leeuw') }}"
                            class:input="border-theme-accent-3! bg-brown-5! text-zinc-900! placeholder:text-zinc-500! focus:border-theme-accent-3! focus:ring-offset-brown-5!" />

                        <flux:description>
                            {{ __('Optioneel. Beschrijf wat er op de afbeelding van het watermerk te zien is.') }}
                        </flux:description>

                        <div class="min-h-5">
                            <flux:error name="image_alt_text" />
                        </div>
                    </flux:field>

                </div>

            </section>

            {{-- Formulieracties --}}
            <div
                class="flex flex-col-reverse justify-end gap-3 border border-theme-accent-1 bg-brown-6 p-5 sm:flex-row sm:items-center">

                <flux:button :href="route('backend.watermarks.index')" variant="ghost">
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button type="submit" variant="primary" icon="check" class="bg-brown-2!">
                    {{ __('Watermerk opslaan') }}
                </flux:button>

            </div>

        </form>





    </div>

    <script>
        function toggleExistingWatermarkImage() {
            const input = document.getElementById('remove-existing-image');
            const message = document.getElementById('existing-image-removal-message');
            const button = document.getElementById('remove-existing-image-button');

            const shouldRemove = input.value !== '1';

            input.value = shouldRemove ? '1' : '0';

            message.classList.toggle('hidden', !shouldRemove);

            button.setAttribute('aria-pressed', shouldRemove ? 'true' : 'false');
        }
    </script>
</x-layouts::app>
