<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">

    <head>
        @include('partials.head')
    </head>

    <body class="min-h-screen bg-theme-accent-2 antialiased">
        <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-theme-accent-2 p-6 md:p-10">
            <div
                class="flex h-auto w-full max-w-sm flex-col gap-2 rounded-xl border border-theme-accent-1 bg-brown-6 p-6 text-zinc-900">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-9 w-9 mb-1 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                    </span>
                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>

                {{-- Taalkeuze --}}
                <div class="mt-4 mb-4">
                    @php
                        $languages = [
                            'nl' => ['flag' => '🇳🇱', 'name' => 'Nederlands'],
                            'en' => ['flag' => '🇬🇧', 'name' => 'English'],
                            'de' => ['flag' => '🇩🇪', 'name' => 'Deutsch'],
                            'fr' => ['flag' => '🇫🇷', 'name' => 'Français'],
                            'es' => ['flag' => '🇪🇸', 'name' => 'Español'],
                        ];

                        $currentLanguage = $languages[app()->getLocale()] ?? $languages['nl'];
                    @endphp

                    <div class="flex w-full justify-center">
                        <flux:dropdown position="bottom" align="center">
                            <flux:button icon-trailing="chevron-down"
                                class="w-40 justify-start! border-theme-accent-3! bg-brown-4! text-zinc-900! hover:bg-brown-5! [&_[data-flux-icon]]:ml-auto!">
                                {{ $currentLanguage['flag'] }} {{ $currentLanguage['name'] }}
                            </flux:button>

                            <flux:menu class="min-w-48 border-theme-accent-3! bg-brown-6!">
                                @foreach ($languages as $locale => $language)
                                    <form method="POST" action="{{ route('language.switch') }}">
                                        @csrf

                                        <input type="hidden" name="locale" value="{{ $locale }}">

                                        <flux:menu.item as="button" type="submit"
                                            class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">
                                            {{ $language['flag'] }} {{ $language['name'] }}
                                        </flux:menu.item>
                                    </form>
                                @endforeach
                            </flux:menu>
                        </flux:dropdown>
                    </div>

                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>

</html>
