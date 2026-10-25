<x-layouts::app :title="__('Thema bekijken')">
    <div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">

        {{-- Succesmelding --}}
        @if (session('success'))
            <div
                class="rounded-lg border border-green-300 bg-green-50 p-4 text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-300">
                {{ session('success') }}
            </div>
        @endif

        {{-- Foutmelding --}}
        @if (session('error'))
            <div
                class="rounded-lg border border-red-300 bg-red-50 p-4 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-300">
                {{ session('error') }}
            </div>
        @endif

        {{-- Paginakop --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">
                    {{ $theme->translationWithFallback()?->name ?? $theme->name }}
                </flux:heading>

                <flux:subheading>
                    {{ __('Details en vertalingen van het thema') }}
                </flux:subheading>
            </div>

            <div class="flex items-center gap-3">
                <flux:button :href="route('backend.themes.index')" variant="ghost">
                    {{ __('Terug naar overzicht') }}
                </flux:button>

                <flux:button :href="route('backend.themes.edit', $theme)" variant="primary">
                    {{ __('Bewerken') }}
                </flux:button>

                {{-- Thema verwijderen --}}
                <form method="POST" action="{{ route('backend.themes.destroy', $theme) }}" class="delete-confirm-form"
                    onsubmit="event.preventDefault(); openDeleteModal(this);">
                    @csrf
                    @method('DELETE')

                    <flux:button type="submit" variant="danger">
                        {{ __('Verwijderen') }}
                    </flux:button>
                </form>

            </div>
        </div>

        {{-- Algemene gegevens --}}
        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">
                {{ __('Algemene gegevens') }}
            </flux:heading>

            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-zinc-500">
                        {{ __('Naam') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $theme->name }}
                    </dd>
                </div>

                <div>
                    <dt class="text-sm text-zinc-500">
                        {{ __('Slug') }}
                    </dt>
                    <dd class="mt-1 font-medium">
                        {{ $theme->slug }}
                    </dd>
                </div>

                <div class="sm:col-span-2">
                    <dt class="text-sm text-zinc-500">
                        {{ __('Beschrijving') }}
                    </dt>
                    <dd class="mt-1">
                        {{ $theme->description ?: '—' }}
                    </dd>
                </div>
            </dl>
        </div>

        {{-- Vertalingen --}}
        <div class="rounded-xl border border-zinc-200 p-6 dark:border-zinc-700">
            <flux:heading size="lg" class="mb-4">
                {{ __('Vertalingen') }}
            </flux:heading>

            <div class="space-y-4">
                @foreach ([
        'nl' => 'Nederlands',
        'en' => 'English',
        'de' => 'Deutsch',
        'fr' => 'Français',
        'es' => 'Español',
    ] as $locale => $language)
                    @php
                        $translation = $theme->translations->firstWhere('locale', $locale);
                    @endphp

                    <div class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                        <flux:heading size="sm" class="mb-3">
                            {{ $language }}
                        </flux:heading>

                        @if ($translation)
                            <dl class="space-y-3">
                                <div>
                                    <dt class="text-sm text-zinc-500">
                                        {{ __('Naam') }}
                                    </dt>
                                    <dd class="mt-1">
                                        {{ $translation->name }}
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-sm text-zinc-500">
                                        {{ __('Beschrijving') }}
                                    </dt>
                                    <dd class="mt-1">
                                        {{ $translation->description ?: '—' }}
                                    </dd>
                                </div>
                            </dl>
                        @else
                            <p class="text-sm text-zinc-500">
                                {{ __('Geen vertaling beschikbaar.') }}
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

    </div>

    @include('components.delete-confirm-modal')
</x-layouts::app>
