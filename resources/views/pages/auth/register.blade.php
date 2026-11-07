<x-layouts::auth :title="__('Register')">
    <div class="flex flex-col gap-6">
        <div class="grid grid-rows-[minmax(4.5rem,auto)_minmax(4rem,auto)]">
            <div class="flex items-center justify-center text-center">
                <flux:heading size="xl">
                    {{ __('Create an account') }}
                </flux:heading>
            </div>

            <div class="flex items-center justify-center text-center">
                <flux:text>
                    {{ __('Enter your details below to create your account') }}
                </flux:text>
            </div>
        </div>

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-6">
            @csrf
            <!-- Name -->
            <flux:input name="name" :label="__('Name')" :value="old('name')" type="text" required autofocus
                autocomplete="name" :placeholder="__('Full name')"
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

            <!-- Email Address -->
            <flux:input name="email" :label="__('Email address')" :value="old('email')" type="email" required
                autocomplete="email" placeholder="email@example.com"
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
            <flux:input name="password" :label="__('Password')" type="password" required autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
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

            <!-- Confirm Password -->
            <flux:input name="password_confirmation" :label="__('Confirm password')" type="password" required
                autocomplete="new-password" :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
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

            <div class="flex items-center justify-end">
                <flux:button type="submit" variant="primary"
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
                    data-test="register-user-button">
                    {{ __('Create account') }}
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-zinc-900">
            <span>{{ __('Already have an account?') }}</span>

            <flux:link :href="route('login')" wire:navigate
                class="text-zinc-900! hover:text-theme-accent-3! focus:text-theme-accent-3!">
                {{ __('Log in') }}
            </flux:link>
        </div>
    </div>
</x-layouts::auth>
