@php
    $selectedValue = request()->query($fieldName, '');

    $selectedValue = is_scalar($selectedValue) ? (string) $selectedValue : '';

    $optionCountryIds = $optionCountryIds ?? [];
    $optionIssueYears = $optionIssueYears ?? [];

    $selectedCountryId = (string) request()->query('country_id', '');
    $selectedIssueYear = (string) request()->query('issue_year', '');

    if (
        $optionCountryIds !== [] &&
        $selectedCountryId !== '' &&
        $selectedValue !== '' &&
        ($optionCountryIds[$selectedValue] ?? null) !== $selectedCountryId
    ) {
        $selectedValue = '';
    }

    if (
        $optionIssueYears !== [] &&
        $selectedIssueYear !== '' &&
        $selectedValue !== '' &&
        ($optionIssueYears[$selectedValue] ?? null) !== $selectedIssueYear
    ) {
        $selectedValue = '';
    }

    $selectedLabel = $options[$selectedValue] ?? $placeholder;
@endphp

<flux:field class="min-w-0 flex-1">

    <flux:label>
        {{ $label }}
    </flux:label>

    <div class="min-w-0" x-data="{
        selectedValue: @js($selectedValue),
        selectedLabel: @js($selectedLabel),
        selectedCountryId: @js($selectedCountryId),
        selectedIssueYear: @js($selectedIssueYear),
        optionCountryIds: @js($optionCountryIds),
        optionIssueYears: @js($optionIssueYears),
        placeholder: @js($placeholder),
    
        isOptionVisible(value) {
            const matchesCountry =
                Object.keys(this.optionCountryIds).length === 0 ||
                this.selectedCountryId === '' ||
                this.optionCountryIds[value] === this.selectedCountryId;
    
            const matchesYear =
                Object.keys(this.optionIssueYears).length === 0 ||
                this.selectedIssueYear === '' ||
                this.optionIssueYears[value] === this.selectedIssueYear;
    
            return matchesCountry && matchesYear;
        },
    
        validateSelection() {
            if (this.selectedValue !== '' && !this.isOptionVisible(this.selectedValue)) {
                this.selectedValue = '';
                this.selectedLabel = this.placeholder;
            }
        },
    
        updateCountry(countryId) {
            this.selectedCountryId = countryId;
            this.validateSelection();
        },
    
        updateYear(issueYear) {
            this.selectedIssueYear = issueYear;
            this.validateSelection();
        }
    }"
        x-on:stamp-filter-country-changed.window="updateCountry($event.detail.countryId)"
        x-on:stamp-filter-year-changed.window="updateYear($event.detail.issueYear)">

        <input type="hidden" name="{{ $fieldName }}" x-model="selectedValue">

        <flux:dropdown position="bottom" align="start" class="block w-full min-w-0 max-w-full">

            <flux:button type="button" icon-trailing="chevron-down" icon-trailing:class="shrink-0!"
                class="w-full! min-w-0! max-w-full! overflow-hidden justify-between! [&>span]:min-w-0 [&>span]:flex-1 border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

                <span x-text="selectedLabel" x-bind:title="selectedLabel" class="block w-full truncate text-left">
                </span>

            </flux:button>

            <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6!">

                <flux:menu.item as="button" type="button"
                    x-on:click="selectedValue = ''; selectedLabel = @js($placeholder)"
                    class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                    {{ $placeholder }}

                </flux:menu.item>

                @foreach ($options as $value => $optionLabel)
                    <flux:menu.item as="button" type="button" x-show="isOptionVisible(@js((string) $value))"
                        x-on:click="selectedValue = @js((string) $value); selectedLabel = @js($optionLabel)"
                        class="w-full cursor-pointer text-zinc-900! hover:bg-brown-5!">

                        {{ $optionLabel }}

                    </flux:menu.item>
                @endforeach

            </flux:menu>

        </flux:dropdown>

    </div>

</flux:field>
