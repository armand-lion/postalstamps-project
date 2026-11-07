<x-layouts::auth :title="__('Email verification')">
    <div class="flex flex-col gap-6">

        <div class="grid grid-rows-[minmax(4.5rem,auto)_minmax(4rem,auto)]">
            <div class="flex items-center justify-center text-center">
                <flux:heading size="xl">
                    {{ __('Email verification') }}
                </flux:heading>
            </div>

            <div class="flex items-center justify-center text-center">
                <flux:text>
                    {{ __('Please verify your email address by clicking on the link we just emailed to you.') }}
                </flux:text>
            </div>
        </div>

        <div class="flex min-h-24 items-center justify-center">
            @if (session('status') == 'verification-link-sent')
                <flux:text class="text-center font-medium text-green-600! dark:text-green-400!">
                    {{ __('A new verification link has been sent to the email address you provided during registration.') }}
                </flux:text>
            @endif
        </div>

        <div class="flex flex-col items-center justify-between space-y-3">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
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
    ">
                    {{ __('Resend verification email') }}
                </flux:button>
            </form>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <flux:button variant="ghost" type="submit"
                    class="
        cursor-pointer
        text-sm
        text-zinc-900!
        hover:bg-brown-5!
        hover:text-theme-accent-3!
        focus:ring-2!
        focus:ring-theme-accent-3!
        focus:ring-offset-2!
        focus:ring-offset-brown-6!
    "
                    data-test="logout-button">
                    {{ __('Log out') }}
                </flux:button>
            </form>
        </div>
    </div>
</x-layouts::auth>
