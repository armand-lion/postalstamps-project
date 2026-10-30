@props(['name', 'label' => null, 'value' => null])

@php
    $selectedDate = old($name, $value);
@endphp

<div x-data="{
    open: false,
    selectedDate: @js($selectedDate),
    currentMonth: new Date().getMonth(),
    currentYear: new Date().getFullYear(),

    init() {
        if (this.selectedDate) {
            const parts = this.selectedDate.split('-');

            if (parts.length === 3) {
                const year = Number(parts[0]);
                const month = Number(parts[1]);

                if (
                    Number.isInteger(year) &&
                    Number.isInteger(month) &&
                    month >= 1 &&
                    month <= 12
                ) {
                    this.currentYear = year;
                    this.currentMonth = month - 1;
                }
            }
        }
    },

    // Weekdagen, beginnend op maandag
    get weekdays() {
        const locale = @js(str_replace('_', '-', app()->getLocale()));

        const monday = new Date(2024, 0, 1);

        return Array.from({ length: 7 }, (_, index) => {
            const date = new Date(
                monday.getFullYear(),
                monday.getMonth(),
                monday.getDate() + index
            );

            return new Intl.DateTimeFormat(locale, {
                weekday: 'short'
            }).format(date);
        });
    },

    // Lege vakjes vóór de eerste dag van de maand
    get firstDayOffset() {
        const firstDay = new Date(
            this.currentYear,
            this.currentMonth,
            1
        ).getDay();

        return (firstDay + 6) % 7;
    },

    // Aantal dagen in de geselecteerde maand
    get daysInMonth() {
        return new Date(
            this.currentYear,
            this.currentMonth + 1,
            0
        ).getDate();
    },

    // Alle vakjes voor het kalenderraster
    get calendarDays() {
        const days = [];

        for (let i = 0; i < this.firstDayOffset; i++) {
            days.push(null);
        }

        for (let day = 1; day <= this.daysInMonth; day++) {
            days.push(day);
        }

        return days;
    },

    // Datum selecteren
    selectDate(day) {
        const year = this.currentYear;

        const month = String(this.currentMonth + 1).padStart(2, '0');

        const selectedDay = String(day).padStart(2, '0');

        this.selectedDate = `${year}-${month}-${selectedDay}`;

        this.open = false;
    },

    // Controleren of een dag geselecteerd is
    isSelected(day) {
        if (!this.selectedDate || day === null) {
            return false;
        }

        const year = this.currentYear;

        const month = String(this.currentMonth + 1).padStart(2, '0');

        const selectedDay = String(day).padStart(2, '0');

        return this.selectedDate === `${year}-${month}-${selectedDay}`;
    }
}" class="relative flex flex-col gap-2">

    {{-- Label --}}
    @if ($label)
        <label for="{{ $name }}" class="text-sm font-medium text-zinc-900">
            {{ $label }}
        </label>
    @endif

    {{-- Datumveld --}}
    <div class="relative">

        {{-- Zichtbaar datumveld --}}
        <input type="text" id="{{ $name }}" x-model="selectedDate" x-on:click="open = !open"
            x-on:keydown.enter.prevent="open = !open" x-on:keydown.escape="open = false" :aria-expanded="open.toString()"
            aria-haspopup="dialog" readonly placeholder="{{ __('Selecteer een datum') }}"
            class="block h-10 w-full cursor-pointer rounded-lg border border-theme-accent-3 bg-brown-5 px-3 pr-12 text-sm text-zinc-900 shadow-xs outline-none focus:ring-2 focus:ring-theme-accent-3/30" />

        {{-- Kalenderknop --}}
        <button type="button" x-on:click="open = !open" :aria-expanded="open.toString()"
            aria-label="{{ __('Kalender openen') }}"
            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-zinc-900 hover:text-brown-2">
            <flux:icon.calendar-days class="size-5" />
        </button>

        {{-- Verborgen veld voor Laravel --}}
        <input type="hidden" name="{{ $name }}" x-model="selectedDate" />

    </div>

    {{-- Kalenderpopup --}}
    <div x-show="open" x-cloak x-on:click.outside="open = false" x-on:keydown.escape.window="open = false"
        role="dialog" aria-label="{{ __('Datum selecteren') }}"
        class="absolute top-18 left-0 z-50 w-[300px] max-w-[calc(100vw-2rem)] rounded-xl border border-theme-accent-1 bg-brown-6 p-4 shadow-xl"">

        {{-- Kalenderkop --}}
        <div class="flex items-center justify-between gap-3 rounded-lg bg-brown-2 p-3">

            {{-- Vorige maand --}}
            <button type="button"
                x-on:click="
                currentMonth--;

                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
            "
                aria-label="{{ __('Vorige maand') }}"
                class="flex h-9 w-9 items-center justify-center rounded-lg bg-brown-3 text-white hover:bg-brown-4">
                <flux:icon.chevron-left class="size-5" />
            </button>

            {{-- Maand en jaar --}}
            <div class="text-center text-sm font-semibold text-white">

                <span
                    x-text="new Intl.DateTimeFormat(
                    @js(str_replace('_', '-', app()->getLocale())),
                    { month: 'long' }
                ).format(new Date(currentYear, currentMonth, 1))"></span>

                <span x-text="currentYear"></span>

            </div>

            {{-- Volgende maand --}}
            <button type="button"
                x-on:click="
                currentMonth++;

                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
            "
                aria-label="{{ __('Volgende maand') }}"
                class="flex h-9 w-9 items-center justify-center rounded-lg bg-brown-3 text-white hover:bg-brown-4">
                <flux:icon.chevron-right class="size-5" />
            </button>

        </div>

        {{-- Weekdagen --}}
        <div class="mx-auto mt-4 grid w-fit grid-cols-7 gap-1">

            <template x-for="(weekday, index) in weekdays" :key="index">

                <div x-text="weekday"
                    class="flex h-9 w-9 items-center justify-center text-center text-xs font-semibold text-zinc-700">
                </div>

            </template>

        </div>

        {{-- Kalenderdagen --}}
        <div class="mx-auto mt-1 grid w-fit grid-cols-7 gap-1">

            <template x-for="(day, index) in calendarDays" :key="index">

                <div class="flex h-9 items-center justify-center">

                    {{-- Leeg vakje --}}
                    <template x-if="day === null">
                        <span class="block h-9 w-9"></span>
                    </template>

                    {{-- Kalenderdag --}}
                    <template x-if="day !== null">

                        <button type="button" x-text="day" x-on:click="selectDate(day)"
                            :aria-pressed="isSelected(day).toString()"
                            :class="isSelected(day) ?
                                'bg-brown-3 text-white font-semibold' :
                                'text-zinc-900 hover:bg-brown-5'"
                            class="flex h-9 w-9 items-center justify-center rounded-lg text-sm transition-colors"></button>

                    </template>

                </div>

            </template>

        </div>

        {{-- Kalenderacties --}}
        <div class="mt-4 flex justify-end border-t border-theme-accent-1 pt-3">

            <button type="button" x-on:click="open = false"
                class="rounded-lg bg-brown-5 px-4 py-2 text-sm font-medium text-zinc-900 hover:bg-brown-4">
                {{ __('Sluiten') }}
            </button>

        </div>

    </div>

    {{-- Validatiefouten --}}
    <div class="min-h-5">
        <flux:error :name="$name" />
    </div>

</div>
