<x-layouts::auth :title="__('Forgot password')">
    <div class="flex flex-col gap-6">
        <div class="grid grid-rows-[minmax(4.5rem,auto)_minmax(4rem,auto)]">
            <div class="flex items-center justify-center text-center">
                <flux:heading size="xl">
                    {{ __('Forgot password') }}
                </flux:heading>
            </div>

            <div class="flex items-center justify-center text-center">
                <flux:text>
                    {{ __('Enter your email to receive a password reset link') }}
                </flux:text>
            </div>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input name="email" :label="__('Email address')" type="email" required autofocus
                placeholder="email@example.com"
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
                data-test="email-password-reset-link-button">
                {{ __('Email password reset link') }}
            </flux:button>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-900">
            <span>{{ __('Or, return to') }}</span>

            <flux:link :href="route('login')" wire:navigate
                class="text-zinc-900! hover:text-theme-accent-3! focus:text-theme-accent-3!">
                {{ __('log in') }}
            </flux:link>
        </div>
    </div>
</x-layouts::auth>
