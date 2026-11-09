<x-layouts::app :title="__('Verzamelobjecttype bewerken')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginatitel --}}
        <div class="rounded-lg border border-theme-accent-1 bg-brown-6 p-5">

            <flux:heading size="xl">
                {{ __('Verzamelobjecttype bewerken') }}
            </flux:heading>

            <flux:text class="mt-2">
                {{ __('Wijzig de gegevens en vertalingen van dit verzamelobjecttype.') }}
            </flux:text>

        </div>

        {{-- Formulier --}}
        <div class="rounded-xl border border-theme-accent-1 bg-brown-6 p-6">

            <form method="POST" action="{{ route('backend.collectible-types.update', $collectibleType) }}"
                class="flex flex-col gap-6">
                @csrf
                @method('PUT')

                {{-- Basisgegevens --}}
                <div class="flex flex-col gap-4">

                    <flux:heading size="lg">
                        {{ __('Basisgegevens') }}
                    </flux:heading>

                    {{-- Slug --}}
                    <flux:field>
                        <flux:label>
                            <span class="flex items-center gap-1">
                                {{ __('Slug') }}
                                <span class="text-red-600">*</span>
                            </span>
                        </flux:label>

                        <input type="text" name="slug" value="{{ old('slug', $collectibleType->slug) }}"
                            placeholder="stamp_folder" maxlength="100" required
                            class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5" />

                        <flux:error name="slug" />
                    </flux:field>

                    {{-- Sorteervolgorde --}}
                    <flux:field>
                        <flux:label>
                            {{ __('Volgorde') }}
                        </flux:label>

                        <div x-data="{
                            increase() {
                                    let input = $refs.sortOrder;
                                    input.value = Math.min(9999, Number(input.value || 0) + 1);
                                    input.dispatchEvent(new Event('input', { bubbles: true }));
                                },
                                decrease() {
                                    let input = $refs.sortOrder;
                                    input.value = Math.max(0, Number(input.value || 0) - 1);
                                    input.dispatchEvent(new Event('input', { bubbles: true }));
                                }
                        }" class="relative">
                            <input x-ref="sortOrder" type="number" name="sort_order" min="0" max="9999"
                                value="{{ old('sort_order', $collectibleType->sort_order) }}"
                                class="block h-10 w-full appearance-none rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-10 text-sm text-zinc-900 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5 [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none [-moz-appearance:textfield]" />

                            {{-- Aangepaste pijltjes --}}
                            <div class="absolute inset-y-0 right-1 flex flex-col justify-center">

                                <button type="button" x-on:click="increase()"
                                    aria-label="{{ __('Volgorde verhogen') }}"
                                    class="flex h-4 w-7 items-center justify-center rounded-t border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                    <flux:icon.chevron-up class="size-3" />
                                </button>

                                <button type="button" x-on:click="decrease()"
                                    aria-label="{{ __('Volgorde verlagen') }}"
                                    class="flex h-4 w-7 items-center justify-center rounded-b border border-theme-accent-3 bg-brown-6 text-zinc-900 hover:bg-brown-5">
                                    <flux:icon.chevron-down class="size-3" />
                                </button>

                            </div>
                        </div>
                        <flux:error name="sort_order" />
                    </flux:field>

                    {{-- Actief --}}
                    <flux:field>
                        <label class="flex items-center gap-3">
                            <flux:checkbox name="is_active" value="1"
                                :checked="old('is_active', $collectibleType->is_active)"
                                class="[&_[data-flux-checkbox-indicator]]:bg-brown-5! [&_[data-flux-checkbox-indicator]]:border-theme-accent-3!" />

                            <span class="text-sm font-medium text-zinc-900">
                                {{ __('Actief') }}
                            </span>
                        </label>
                    </flux:field>

                </div>

                {{-- Vertalingen --}}
                <div class="flex flex-col gap-4">

                    <flux:heading size="lg">
                        {{ __('Vertalingen') }}
                    </flux:heading>

                    @foreach ([
        'nl' => 'Nederlands',
        'en' => 'Engels',
        'de' => 'Duits',
        'fr' => 'Frans',
        'es' => 'Spaans',
    ] as $locale => $language)
                        <flux:field>
                            <flux:label>
                                <span class="flex items-center gap-1">
                                    {{ __($language) }}

                                    @if ($locale === app()->getLocale())
                                        <span class="text-red-600">*</span>
                                    @endif
                                </span>
                            </flux:label>

                            <input type="text" name="translations[{{ $locale }}]"
                                value="{{ old('translations.' . $locale, $collectibleType->translations->firstWhere('locale', $locale)?->name ?? '') }}"
                                maxlength="255" @required($locale === app()->getLocale())
                                class="block h-10 w-full rounded-lg border border-theme-accent-3 bg-brown-5 px-3 text-sm text-zinc-900 placeholder:text-zinc-500 focus:border-theme-accent-3 focus:outline-none focus:ring-2 focus:ring-theme-accent-3 focus:ring-offset-2 focus:ring-offset-brown-5" />

                            <flux:error name="translations.{{ $locale }}" />
                        </flux:field>
                    @endforeach

                </div>

                {{-- Acties --}}
                <div class="flex justify-end gap-3">

                    <flux:button :href="route('backend.collectible-types.index')" variant="ghost" wire:navigate>
                        {{ __('Annuleren') }}
                    </flux:button>

                    <flux:button type="submit" variant="primary" class="bg-brown-2!">
                        {{ __('Wijzigingen opslaan') }}
                    </flux:button>

                </div>

            </form>

        </div>

    </div>

</x-layouts::app>
