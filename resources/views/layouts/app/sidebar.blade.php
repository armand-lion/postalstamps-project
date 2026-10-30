<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

    <head>
        @include('partials.head')
    </head>

    <body class="min-h-screen bg-theme-accent-2 ">
        <flux:sidebar sticky collapsible="mobile"
            class="border-e border-theme-accent-1 bg-theme-accent-3">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Platform')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')"
                        :current="request()->routeIs('dashboard')" class="hover:bg-brown-5!" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                <flux:sidebar.group :heading="__('Catalogusbeheer')" class="grid">

                    <flux:sidebar.item icon="globe-alt" :href="route('backend.countries.index')"
                        :current="request()->routeIs('backend.countries.*')"
                        class="hover:bg-brown-5! data-current:bg-brown-4! border-transparent!" wire:navigate>
                        {{ __('Landen') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="book-open" :href="route('backend.catalogs.index')"
                        :current="request()->routeIs('backend.catalogs.*')"
                        class="hover:bg-brown-5! data-current:bg-brown-4! border-transparent!" wire:navigate>
                        {{ __('Catalogi') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="rectangle-stack" :href="route('backend.stamp-series.index')"
                        :current="request()->routeIs('backend.stamp-series.*')"
                        class="hover:bg-brown-5! data-current:bg-brown-4! border-transparent!" wire:navigate>
                        {{ __('Postzegelseries') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="square-3-stack-3d" :href="route('backend.stamps.index')"
                        :current="request()->routeIs('backend.stamps.*')"
                        class="hover:bg-brown-5! data-current:bg-brown-4! border-transparent!" wire:navigate>
                        {{ __('Postzegels') }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="tag" :href="route('backend.themes.index')"
                        :current="request()->routeIs('backend.themes.*')"
                        class="hover:bg-brown-5! data-current:bg-brown-4! border-transparent!" wire:navigate>
                        {{ __("Thema's") }}
                    </flux:sidebar.item>

                    <flux:sidebar.item icon="archive-box" :href="route('backend.collectibles.index')"
                        :current="request()->routeIs('backend.collectibles.*')"
                        class="hover:bg-brown-5! data-current:bg-brown-4! border-transparent!" wire:navigate>
                        {{ __('Verzamelobjecten') }}
                    </flux:sidebar.item>

                </flux:sidebar.group>
            </flux:sidebar.nav>

            <div class="px-4 py-3">
                <label class="mb-2 block text-sm font-medium text-white">
                    {{ __('Taal') }}
                </label>

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

                <flux:dropdown position="bottom" align="start">
                    <flux:button icon-trailing="chevron-down"
                        class="w-full justify-between border-theme-accent-3! bg-brown-4! text-zinc-900! hover:bg-brown-5!">

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

            <flux:spacer />


            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down"
                    class="**:data-flux-avatar:bg-brown-4! **:data-flux-avatar:text-white!" />

                <flux:menu class="bg-brown-6! border-theme-accent-3!">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()"
                                    class="bg-brown-4! text-white!" />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate
                            class="data-active:bg-brown-5! [&[data-active]:hover]:bg-brown-4!">
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer data-active:bg-brown-5!"
                            data-test="logout-button">
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>

</html>
