@php
    $selectedOperation = old($fieldName, $defaultOperation ?? 'unchanged');

    if (!is_string($selectedOperation) || !array_key_exists($selectedOperation, $options)) {
        $selectedOperation = 'unchanged';
    }
@endphp

<div class="w-full min-w-0 max-w-full" x-data="{
    selectedOperation: @js($selectedOperation),
    selectedName: @js($options[$selectedOperation])
}">

    <input type="hidden" name="{{ $fieldName }}" x-model="selectedOperation">

    <flux:dropdown position="bottom" align="start" class="block w-full min-w-0 max-w-full">

        <flux:button type="button" icon-trailing="chevron-down" icon-trailing:class="shrink-0!"
            class="w-full! min-w-0! max-w-full! overflow-hidden justify-between! [&>span]:min-w-0 [&>span]:flex-1 border-theme-accent-3! bg-brown-5! text-zinc-900! hover:bg-brown-5!">

            <span x-text="selectedName" x-bind:title="selectedName" class="block w-full truncate text-left"></span>

        </flux:button>

        <flux:menu class="max-h-80 min-w-64 overflow-y-auto border-theme-accent-3! bg-brown-6! ">

            @foreach ($options as $value => $label)
                <flux:menu.item class="text-zinc-900! hover:bg-brown-5! data-focus:bg-brown-5!"
                    x-on:click="
                        selectedOperation = @js($value);
                        selectedName = @js($label);
                        $dispatch('bulk-operation-changed', {
                            field: @js($fieldName),
                            operation: selectedOperation
                        });
                    ">

                    {{ $label }}

                </flux:menu.item>
            @endforeach

        </flux:menu>

    </flux:dropdown>

    <flux:error :name="$fieldName" />

</div>
