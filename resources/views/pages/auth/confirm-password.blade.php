<x-layouts::auth :title="__('Confirm password')">
    <div class="flex flex-col gap-6">
        <div class="grid grid-rows-[minmax(4.5rem,auto)_minmax(4rem,auto)]">
            <div class="flex items-center justify-center text-center">
                <flux:heading size="xl">
                    {{ __('Confirm password') }}
                </flux:heading>
            </div>

            <div class="flex items-center justify-center text-center">
                <flux:text>
                    {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
                </flux:text>
            </div>
        </div>

        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify options-route="passkey.confirm-options" submit-route="passkey.confirm" :label="__('Confirm with passkey')"
            :loading-label="__('Confirming...')" :separator="__('Or confirm with password')" />

        <form method="POST" action="{{ route('password.confirm.store') }}" class="flex flex-col gap-6">
            @csrf

            <flux:input name="password" :label="__('Password')" type="password" required autocomplete="current-password"
                :placeholder="__('Password')" viewable
                class="
        [&_input]:bg-brown-5!
        [&_input]:border-theme-accent-3!
        [&_input]:text-zinc-900!
        [&_input]:placeholder:text-zinc-500!
        [&_input:focus]:border-theme-accent-3!
        [&_input:focus]:outline-none!
        [&_input:focus]:ring-2!
        [&_input:focus]:ring-theme-accent-3!
        [&_input:focus]:ring-offset-2!
        [&_input:focus]:ring-offset-brown-5!
        [&_input:autofill]:[-webkit-text-fill-color:#18181b]!
        [&_input:autofill]:[-webkit-box-shadow:0_0_0_1000px_var(--color-brown-5)_inset]!
        [&_input:autofill:focus]:[-webkit-box-shadow:0_0_0_1000px_var(--color-brown-5)_inset,0_0_0_2px_var(--color-brown-5),0_0_0_4px_var(--color-theme-accent-3)]!
    " />

            <flux:button variant="primary" type="submit"
                class="
        w-full
        bg-theme-accent-3!
        border-theme-accent-3!
        text-white!
        hover:bg-theme-accent-1!
        hover:border-theme-accent-1!
        focus:ring-2!
        focus:ring-theme-accent-3!
        focus:ring-offset-2!
        focus:ring-offset-brown-6!
    "
                data-test="confirm-password-button">
                {{ __('Confirm') }}
            </flux:button>
        </form>
    </div>
</x-layouts::auth>
