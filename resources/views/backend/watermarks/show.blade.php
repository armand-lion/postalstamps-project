<x-layouts::app :title="__('Watermerkdetails')">
    <div class="flex flex-col gap-6">

        {{-- Paginakop --}}
        <div
            class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-theme-accent-1 bg-brown-6 p-5 sm:flex-row sm:items-center">
            <div>
                <flux:heading size="xl">
                    {{ __('Watermerkdetails') }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Bekijk de gegevens van het geselecteerde watermerk.') }}
                </flux:text>
            </div>

            <flux:button :href="route('backend.watermarks.index')" variant="ghost" icon="arrow-left">
                {{ __('Terug naar overzicht') }}
            </flux:button>
        </div>

        {{-- Basisgegevens --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-6">

            <flux:heading size="lg">
                {{ __('Basisgegevens') }}
            </flux:heading>

            @php
                $primaryImage = $watermark->images->firstWhere('is_primary', true) ?? $watermark->images->first();
            @endphp

            <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">

                {{-- Kolom 1: Afbeelding --}}
                <div>
                    @if ($primaryImage)
                        <img src="{{ asset('storage/' . $primaryImage->path) }}"
                            alt="{{ $primaryImage->alt_text ?: $watermark->translationWithFallback()?->name ?? $watermark->slug }}"
                            class="h-48 w-48 rounded-lg border border-theme-accent-3 bg-brown-5 object-contain">

                        @if ($primaryImage->alt_text)
                            <flux:text class="mt-2 text-sm">
                                {{ $primaryImage->alt_text }}
                            </flux:text>
                        @endif
                    @else
                        <div
                            class="flex h-48 w-48 items-center justify-center rounded-lg border border-theme-accent-3 bg-brown-5 p-4">
                            <flux:text class="text-center text-sm italic">
                                {{ __('Geen afbeelding beschikbaar.') }}
                            </flux:text>
                        </div>
                    @endif
                </div>

                {{-- Kolom 2: Watermerk en land --}}
                <div class="flex flex-col gap-6">

                    {{-- Watermerknaam --}}
                    <div>
                        <flux:text class="font-semibold">
                            {{ __('Watermerk') }}
                        </flux:text>

                        <flux:text class="mt-1">
                            {{ $watermark->translationWithFallback()?->name ?? $watermark->slug }}
                        </flux:text>
                    </div>

                    {{-- Land --}}
                    <div>
                        <flux:text class="font-semibold">
                            {{ __('Land') }}
                        </flux:text>

                        <flux:text class="mt-1">
                            {{ $watermark->country?->translationWithFallback()?->name ?? ($watermark->country?->name ?? '—') }}
                        </flux:text>
                    </div>

                </div>

                {{-- Kolom 3: Overige gegevens --}}
                <div class="flex flex-col gap-6">

                    {{-- Slug --}}
                    <div>
                        <flux:text class="font-semibold">
                            {{ __('Slug') }}
                        </flux:text>

                        <flux:text class="mt-1">
                            {{ $watermark->slug }}
                        </flux:text>
                    </div>

                    {{-- Sorteervolgorde --}}
                    <div>
                        <flux:text class="font-semibold">
                            {{ __('Sorteervolgorde') }}
                        </flux:text>

                        <flux:text class="mt-1">
                            {{ $watermark->sort_order }}
                        </flux:text>
                    </div>

                    {{-- Status --}}
                    <div>
                        <flux:text class="font-semibold">
                            {{ __('Status') }}
                        </flux:text>

                        <div class="mt-2">
                            @if ($watermark->is_active)
                                <flux:badge color="green">
                                    {{ __('Actief') }}
                                </flux:badge>
                            @else
                                <flux:badge color="red">
                                    {{ __('Inactief') }}
                                </flux:badge>
                            @endif
                        </div>
                    </div>

                </div>

            </div>
        </div>

        {{-- Vertalingen --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-6">
            <flux:heading size="lg">
                {{ __('Vertalingen') }}
            </flux:heading>

            <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">

                @foreach ([
        'nl' => 'Nederlands',
        'en' => 'Engels',
        'de' => 'Duits',
        'fr' => 'Frans',
        'es' => 'Spaans',
    ] as $locale => $language)
                    @php
                        $translation = $watermark->translations->firstWhere('locale', $locale);
                    @endphp

                    <div class="rounded-lg border border-theme-accent-3 bg-brown-5 p-4">

                        <flux:heading size="sm">
                            {{ __($language) }}
                        </flux:heading>

                        @if ($translation)
                            <div class="mt-3">
                                <flux:text class="font-semibold">
                                    {{ __('Naam') }}
                                </flux:text>

                                <flux:text class="mt-1">
                                    {{ $translation->name }}
                                </flux:text>
                            </div>

                            <div class="mt-4">
                                <flux:text class="font-semibold">
                                    {{ __('Beschrijving') }}
                                </flux:text>

                                <flux:text class="mt-1 whitespace-pre-line">
                                    {{ $translation->description ?: '—' }}
                                </flux:text>
                            </div>
                        @else
                            <flux:text class="mt-3 italic">
                                {{ __('Geen vertaling beschikbaar.') }}
                            </flux:text>
                        @endif

                    </div>
                @endforeach

            </div>
        </div>


    </div>
</x-layouts::app>
