<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <div class="grid grid-rows-[minmax(4.5rem,auto)_minmax(4rem,auto)]">
            <div class="flex items-center justify-center text-center">
                <flux:heading size="xl">
                    {{ __('Log in to your account') }}
                </flux:heading>
            </div>

            <div class="flex items-center justify-center text-center">
                <flux:text>
                    {{ __('Enter your email and password below to log in') }}
                </flux:text>
            </div>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Email Address -->
            <flux:input name="email" :label="__('Email address')" :value="old('email')" type="email" required
                autofocus autocomplete="email" placeholder="email@example.com"
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
            <!-- Password -->
            <div class="relative">
                <flux:input name="password" :label="__('Password')" type="password" required
                    autocomplete="current-password" :placeholder="__('Password')" viewable
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
                @if (Route::has('password.request'))
                    <flux:link
                        class="absolute top-0 text-sm end-0 text-zinc-900! hover:text-theme-accent-3! focus:text-theme-accent-3!"
                        :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')"
                class="
        [&_[data-flux-checkbox-indicator]]:border-theme-accent-3!
        [&_[data-flux-checkbox-indicator]]:bg-brown-5!
        [&_[data-flux-checkbox-indicator]]:text-zinc-900!
    " />

            <div class="flex items-center justify-end">
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
                    data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" wire:navigate
                class="text-zinc-900! hover:text-theme-accent-3! focus:text-theme-accent-3!">
                {{ __('Sign up') }}
            </flux:link>
        </div>
    </div>
</x-layouts::auth>
