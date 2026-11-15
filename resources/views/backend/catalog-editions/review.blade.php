<x-layouts::app :title="__('Cataloguswaarden controleren')">

    <div class="flex flex-col gap-6 p-4 md:p-6">

        {{-- Paginakop --}}
        <div
            class="flex flex-col justify-between gap-4 rounded-xl border border-theme-accent-3 bg-brown-6 p-5 sm:flex-row sm:items-center">

            <div>
                <flux:heading size="xl" class="text-zinc-900!">
                    {{ __('Cataloguswaarden controleren') }}
                </flux:heading>

                <flux:text class="mt-2 text-zinc-600!">
                    {{ __('Overzicht van cataloguswaarden die nog gecontroleerd moeten worden.') }}
                </flux:text>

                <flux:text class="mt-1 font-medium text-zinc-900!">
                    {{ $catalog->name }} — {{ $edition->edition_name }}
                </flux:text>
            </div>

            {{-- Navigatie --}}
            <div class="flex flex-wrap justify-end gap-3">

                <flux:button :href="route('backend.catalogs.editions.show', [$catalog, $edition])" variant="ghost"
                    icon="arrow-left" class="text-zinc-900!">

                    {{ __('Terug naar cataloguseditie') }}

                </flux:button>

            </div>

        </div>

        {{-- Controleoverzicht --}}
        <section class="overflow-hidden rounded-xl border border-theme-accent-3 bg-brown-5">

            <div class="border-b border-theme-accent-3 bg-brown-6 px-4 py-3">

                <flux:heading size="lg" class="text-zinc-900!">
                    {{ __('Nog te controleren cataloguswaarden') }}
                </flux:heading>

                <flux:text class="mt-1 text-zinc-900!">
                    {{ __('Controleer de overgenomen cataloguswaarden van deze editie.') }}
                </flux:text>

            </div>

            <div class="p-4">

                <flux:text class="mb-4 text-zinc-900!">
                    {{ __('Aantal nog te controleren cataloguswaarden:') }}

                    <span class="font-semibold">
                        {{ $valuesNeedingReview->total() }}
                    </span>
                </flux:text>

                @if ($valuesNeedingReview->isEmpty())

                    <div class="rounded-lg border border-green-300 bg-green-50 p-4">

                        <flux:text class="text-green-900!">
                            {{ __('Er zijn geen cataloguswaarden die nog gecontroleerd moeten worden.') }}
                        </flux:text>

                    </div>
                @else
                    <div class="overflow-x-auto">

                        <table class="w-full border-collapse text-left text-sm text-zinc-900">

                            <thead class="bg-brown-6">

                                <tr class="border-b border-theme-accent-3">

                                    <th class="px-4 py-3 font-semibold">
                                        {{ __('Postzegel') }}
                                    </th>

                                    <th class="px-4 py-3 font-semibold">
                                        {{ __('Catalogusnummer') }}
                                    </th>

                                    <th class="px-4 py-3 font-semibold">
                                        {{ __('Waarderingscategorie') }}
                                    </th>

                                    <th class="px-4 py-3 font-semibold">
                                        {{ __('Cataloguswaarde') }}
                                    </th>

                                    <th class="px-4 py-3 text-right font-semibold">
                                        {{ __('Acties') }}
                                    </th>

                                </tr>

                            </thead>

                            <tbody>

                                @foreach ($valuesNeedingReview as $value)
                                    <tr class="border-b border-theme-accent-3 last:border-b-0">

                                        {{-- Postzegel --}}
                                        <td class="px-4 py-3">

                                            {{ $value->catalogEntry->stamp->name ?? '—' }}

                                        </td>

                                        {{-- Catalogusnummer --}}
                                        <td class="px-4 py-3">

                                            {{ $value->catalogEntry->catalog_number ?? '—' }}

                                        </td>

                                        {{-- Waarderingscategorie --}}
                                        <td class="px-4 py-3">

                                            {{ $value->condition?->slug ?? '—' }}

                                        </td>

                                        {{-- Cataloguswaarde --}}
                                        <td class="px-4 py-3">

                                            {{ number_format((float) $value->catalog_value, 2, ',', '.') }}

                                            {{ $value->currency }}

                                        </td>

                                        {{-- Acties --}}
                                        <td class="px-4 py-3 text-right">

                                            <div class="flex flex-wrap items-center justify-end gap-2">

                                                {{-- Postzegel bewerken --}}
                                                <flux:button
                                                    :href="route('backend.stamps.edit', $value->catalogEntry->stamp_id)"
                                                    size="sm" icon="pencil"
                                                    class="border border-theme-accent-3! bg-brown-6! text-zinc-900! hover:bg-brown-5!">

                                                    {{ __('Bewerken') }}

                                                </flux:button>

                                                {{-- Cataloguswaarde als gecontroleerd markeren --}}
                                                <form method="POST"
                                                    action="{{ route('backend.stamp-catalog-values.review', $value) }}">

                                                    @csrf
                                                    @method('PATCH')

                                                    <flux:button type="submit" size="sm" icon="check"
                                                        class="border border-green-400! bg-green-50! text-green-900! hover:bg-green-100!">

                                                        {{ __('Als gecontroleerd markeren') }}

                                                    </flux:button>

                                                </form>

                                            </div>

                                        </td>

                                    </tr>
                                @endforeach

                            </tbody>

                        </table>

                    </div>

                    {{-- Paginering --}}
                    <div class="mt-4">

                        {{ $valuesNeedingReview->links() }}

                    </div>

                @endif

            </div>

        </section>

    </div>

</x-layouts::app>
