<x-layouts::app :title="__('Thema bewerken')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">

        {{-- Paginakop --}}
        <div>
            <flux:heading size="xl">
                {{ __('Thema bewerken') }}
            </flux:heading>

            <flux:subheading>
                {{ __('Bewerk de algemene gegevens en vertalingen van het thema.') }}
            </flux:subheading>
        </div>

        {{-- Validatiefouten --}}
        @if ($errors->any())
            <div
                class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-700 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('backend.themes.update', $theme) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Algemene gegevens --}}
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg" class="mb-4">
                    {{ __('Algemene gegevens') }}
                </flux:heading>

                <flux:field>
                    <flux:label>
                        <span class="flex items-center gap-1">
                            {{ __('Slug') }}
                            <span class="text-red-600">*</span>
                        </span>
                    </flux:label>

                    <flux:input name="slug" :value="old('slug', $theme->slug)" maxlength="255" required />

                    <flux:error name="slug" />
                </flux:field>
            </div>

            {{-- Vertalingen --}}
            <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
                <flux:heading size="lg" class="mb-4">
                    {{ __('Vertalingen') }}
                </flux:heading>

                <div class="space-y-6">
                    @foreach ($locales as $locale)
                        @php
                            $translation = $theme->translations->firstWhere('locale', $locale);

                            $language = match ($locale) {
                                'nl' => 'Nederlands',
                                'en' => 'English',
                                'de' => 'Deutsch',
                                'fr' => 'Français',
                                'es' => 'Español',
                            };

                            $defaultName = $translation?->name ?? ($locale === 'nl' ? $theme->name : '');
                        @endphp

                        <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                            <flux:heading size="sm">
                                {{ $language }}
                            </flux:heading>

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
                                    :value="old('translations.'.$locale.
                                        '.name', $defaultName)"
                                    maxlength="255" @required($locale === app()->getLocale()) />

                                <flux:error name="translations.{{ $locale }}.name" />
                            </flux:field>

                            <flux:field>
                                <flux:label>
                                    {{ __('Beschrijving') }}
                                </flux:label>

                                <flux:textarea name="translations[{{ $locale }}][description]" rows="3">
                                    {{ old('translations.' . $locale . '.description', $translation?->description ?? ($locale === 'nl' ? $theme->description : '')) }}
                                </flux:textarea>

                                <flux:error name="translations.{{ $locale }}.description" />
                            </flux:field>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Actieknoppen --}}
            <div class="flex items-center justify-end gap-3">
                <flux:button :href="route('backend.themes.show', $theme)" variant="ghost">
                    {{ __('Annuleren') }}
                </flux:button>

                <flux:button type="submit" variant="primary">
                    {{ __('Wijzigingen opslaan') }}
                </flux:button>
            </div>
        </form>
    </div>
</x-layouts::app>
