<x-layouts::auth :title="__('Two-factor authentication')">
    <div class="flex flex-col gap-6">
        <div class="relative w-full h-auto" x-cloak x-data="{
            showRecoveryInput: @js($errors->has('recovery_code')),
            code: '',
            recovery_code: '',
            focusOtp() {
                this.$nextTick(() => this.$refs.otp?.querySelector('input')?.focus());
            },
            init() {
                if (!this.showRecoveryInput) {
                    this.focusOtp();
                }
            },
            toggleInput() {
                this.showRecoveryInput = !this.showRecoveryInput;

                this.code = '';
                this.recovery_code = '';

                $nextTick(() => {
                    this.showRecoveryInput ?
                        this.$refs.recovery_code?.focus() :
                        this.focusOtp();
                });
            },
        }">
            <div x-show="!showRecoveryInput">
                <div class="mt-6 text-center">
                    <flux:heading size="xl">
                        {{ __('Authentication code') }}
                    </flux:heading>

                    <flux:text class="mt-2 text-zinc-900!">
                        {{ __('Enter the authentication code provided by your authenticator application.') }}
                    </flux:text>
                </div>
            </div>

            <div x-show="showRecoveryInput">
                <div class="mt-6 text-center">
                    <flux:heading size="xl">
                        {{ __('Recovery code') }}
                    </flux:heading>

                    <flux:text class="mt-2 text-zinc-900!">
                        {{ __('Please confirm access to your account by entering one of your emergency recovery codes.') }}
                    </flux:text>
                </div>
            </div>

            <form method="POST" action="{{ route('two-factor.login.store') }}">
                @csrf

                <div class="space-y-5 text-center">
                    <div x-show="!showRecoveryInput">
                        <div class="flex items-center justify-center my-5" x-ref="otp">
                            <flux:otp x-model="code" length="6" name="code" label="OTP Code" label:sr-only
                                class="mx-auto [&_input]:bg-brown-5! [&_input]:border-theme-accent-3! [&_input]:text-zinc-900! [&_input]:focus:ring-offset-0! [&_input]:focus:ring-1!" />
                        </div>
                    </div>

                    <div x-show="showRecoveryInput">
                        <div class="my-5">
                            <flux:input type="text" name="recovery_code" x-ref="recovery_code"
                                x-bind:required="showRecoveryInput" autocomplete="one-time-code" x-model="recovery_code"
                                class="[&_input]:bg-brown-5! [&_input]:border-theme-accent-3! [&_input]:text-zinc-900! [&_input]:focus:border-theme-accent-3! [&_input]:focus:ring-1! [&_input]:focus:ring-theme-accent-3! [&_input]:focus:ring-offset-0!" />

                            @error('recovery_code')
                                <flux:text color="red">
                                    {{ $message }}
                                </flux:text>
                            @enderror
                        </div>

                        <flux:button variant="primary" type="submit"
                            class="w-full bg-theme-accent-3! border-theme-accent-3! text-white! hover:bg-theme-accent-1! hover:border-theme-accent-1!">
                            {{ __('Continue') }}
                        </flux:button>
                    </div>

                    <div class="mt-5 space-x-0.5 text-sm leading-5 text-center">
                        <span class="text-zinc-600">
                            {{ __('or you can') }}
                        </span>

                        <div
                            class="inline font-medium underline cursor-pointer text-theme-accent-3! hover:text-theme-accent-1!">
                            <span x-show="!showRecoveryInput" @click="toggleInput()">
                                {{ __('login using a recovery code') }}
                            </span>

                            <span x-show="showRecoveryInput" @click="toggleInput()">
                                {{ __('login using an authentication code') }}
                            </span>
                        </div>
                    </div>
            </form>
        </div>
    </div>
</x-layouts::auth>
