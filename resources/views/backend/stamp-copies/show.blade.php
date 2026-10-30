<x-layouts::app :title="__('Verzamelde postzegel bekijken')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel en actieknoppen --}}
        <div
            class="flex flex-col justify-between gap-4 rounded-lg border border-theme-accent-1 bg-brown-6 p-5 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl">
                    {{ $stampCopy->stamp->translationWithFallback()?->name ?? $stampCopy->stamp->name }}
                </flux:heading>

                <flux:text class="mt-2">
                    {{ __('Details van deze postzegel in je persoonlijke verzameling.') }}
                </flux:text>
            </div>

            <div class="flex flex-wrap gap-3">

                <flux:button :href="route('backend.stamp-copies.index')" variant="ghost" icon="arrow-left">
                    {{ __('Terug naar overzicht') }}
                </flux:button>

                <flux:button :href="route('backend.stamp-copies.edit', $stampCopy)" variant="primary" icon="pencil"
                    class="bg-brown-2!">
                    {{ __('Bewerken') }}
                </flux:button>

            </div>

        </div>

        {{-- Algemene gegevens --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <flux:heading size="lg">
                {{ __('Algemene gegevens') }}
            </flux:heading>

            <dl class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Naam') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->stamp->translationWithFallback()?->name ?? $stampCopy->stamp->name }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Land') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->stamp->country?->translationWithFallback()?->name ?? ($stampCopy->stamp->country?->name ?? '—') }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Uitgiftejaar') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->stamp->issue_year ?? '—' }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Catalogusreferentie') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->stamp->catalog_reference ?: '—' }}
                    </dd>
                </div>

            </dl>

        </div>

        {{-- Verzamelgegevens --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <flux:heading size="lg">
                {{ __('Verzamelgegevens') }}
            </flux:heading>

            <dl class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                {{-- Conditie --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Conditie') }}
                    </dt>

                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->condition?->translationWithFallback()?->name ?? ($stampCopy->condition?->slug ?? '—') }}
                    </dd>
                </div>

                {{-- Aantal --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Aantal') }}
                    </dt>

                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->quantity }}
                    </dd>
                </div>

                {{-- Aankoopdatum --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Aankoopdatum') }}
                    </dt>

                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->acquisition_date?->format('d-m-Y') ?? '—' }}
                    </dd>
                </div>

                {{-- Aankoopprijs --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Aankoopprijs') }}
                    </dt>

                    <dd class="mt-1 font-medium text-zinc-900">
                        @if ($stampCopy->purchase_price !== null)
                            {{ number_format((float) $stampCopy->purchase_price, 2, ',', '.') }}
                            {{ $stampCopy->purchase_currency ?? '' }}
                        @else
                            —
                        @endif
                    </dd>
                </div>

            </dl>

        </div>

        {{-- Bijzonderheden --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <flux:heading size="lg">
                {{ __('Bijzonderheden') }}
            </flux:heading>

            <dl class="mt-6 grid grid-cols-1 gap-6 md:grid-cols-2">

                {{-- Watermerk --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Watermerk') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->stamp->country->watermarks()->where('slug', $stampCopy->watermark)->first()
                            ?->translationWithFallback()?->name ?? '—' }}
                    </dd>
                </div>

                {{-- Tanding --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Tanding') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->perforation ?: '—' }}
                    </dd>
                </div>

                {{-- Gomconditie --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Gomconditie') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->gumCondition?->translationWithFallback()?->name ?? '—' }}
                    </dd>
                </div>

                {{-- Bijzondere variant --}}
                <div>
                    <dt class="text-sm text-zinc-600">
                        {{ __('Bijzondere variant') }}
                    </dt>
                    <dd class="mt-1 font-medium text-zinc-900">
                        {{ $stampCopy->is_variant ? __('Ja') : __('Nee') }}
                    </dd>
                </div>

                {{-- Beschrijving variant --}}
                @if ($stampCopy->is_variant)
                    <div class="md:col-span-2">
                        <dt class="text-sm text-zinc-600">
                            {{ __('Beschrijving variant') }}
                        </dt>
                        <dd class="mt-1 font-medium text-zinc-900">
                            <span
                                class="whitespace-pre-line">{{ trim($stampCopy->translationWithFallback()?->variant_description ?? ($stampCopy->variant_description ?? '')) ?: '—' }}</span>
                        </dd>
                    </div>
                @endif

                {{-- Persoonlijke notities --}}
                <div class="md:col-span-2">
                    <dt class="text-sm text-zinc-600">
                        {{ __('Persoonlijke notities') }}
                    </dt>
                    <dd class="mt-1 whitespace-pre-line font-medium text-zinc-900">
                        {{ $stampCopy->notes ?: '—' }}
                    </dd>
                </div>

            </dl>

        </div>
        {{-- Catalogusafbeeldingen --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <flux:heading size="lg">
                {{ __('Catalogusafbeeldingen') }}
            </flux:heading>

            @if ($stampCopy->stamp->images->isNotEmpty())

                <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">

                    @foreach ($stampCopy->stamp->images as $image)
                        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-4">

                            {{-- Afbeelding --}}
                            <img src="{{ asset('storage/' . $image->path) }}"
                                alt="{{ $image->alt_text ?: __('Postzegelafbeelding') }}"
                                class="h-64 w-full rounded-lg object-contain" loading="lazy" />

                            {{-- Afbeeldingstype --}}
                            <div class="mt-3">

                                <p class="text-sm font-medium text-zinc-900">

                                    @switch($image->image_type)
                                        @case('front')
                                            {{ __('Voorzijde') }}
                                        @break

                                        @case('back')
                                            {{ __('Achterzijde') }}
                                        @break

                                        @case('detail')
                                            {{ __('Detail') }}
                                        @break

                                        @default
                                            {{ __('Afbeelding') }}
                                    @endswitch

                                </p>

                                @if ($image->alt_text)
                                    <p class="mt-1 text-sm text-zinc-600">
                                        {{ $image->alt_text }}
                                    </p>
                                @endif

                            </div>

                        </div>
                    @endforeach

                </div>
            @else
                <p class="mt-4 text-sm text-zinc-600">
                    {{ __('Er zijn nog geen catalogusafbeeldingen beschikbaar.') }}
                </p>

            @endif

        </div>
        {{-- Afbeeldingen van eigen exemplaren --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-4 md:p-6">

            <flux:heading size="lg">
                {{ __('Afbeeldingen van mijn exemplaar') }}
            </flux:heading>

            @if ($stampCopy->images->isNotEmpty())

                <div class="mt-6 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">

                    @foreach ($stampCopy->images->sortByDesc('is_primary') as $image)
                        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-4">

                            {{-- Afbeelding --}}
                            <img src="{{ asset('storage/' . $image->path) }}"
                                alt="{{ $image->alt_text ?: __('Afbeelding van mijn postzegel') }}"
                                class="h-64 w-full rounded-lg object-contain" loading="lazy" />

                            {{-- Afbeeldingstype --}}
                            <div class="mt-3">

                                <p class="text-sm font-medium text-zinc-900">

                                    @switch($image->image_type)
                                        @case('front')
                                            {{ __('Voorzijde') }}
                                        @break

                                        @case('back')
                                            {{ __('Achterzijde') }}
                                        @break

                                        @case('detail')
                                            {{ __('Detail') }}
                                        @break

                                        @default
                                            {{ __('Afbeelding') }}
                                    @endswitch

                                </p>

                                {{-- Hoofdafbeelding --}}
                                @if ($image->is_primary)
                                    <span
                                        class="mt-2 inline-flex rounded-lg border border-theme-accent-1 bg-brown-5 px-3 py-1 text-xs font-medium text-zinc-900">
                                        {{ __('Hoofdafbeelding') }}
                                    </span>
                                @endif

                                {{-- Alternatieve tekst --}}
                                @if ($image->alt_text)
                                    <p class="mt-2 text-sm text-zinc-600">
                                        {{ $image->alt_text }}
                                    </p>
                                @endif

                            </div>

                        </div>
                    @endforeach

                </div>
            @else
                <p class="mt-4 text-sm text-zinc-600">
                    {{ __('Er zijn nog geen afbeeldingen van dit exemplaar toegevoegd.') }}
                </p>

            @endif

        </div>

    </div>

</x-layouts::app>
