<x-layouts::app :title="__('Two-factor authentication')">
    <div>
        <div class="flex items-center gap-3">
            <flux:heading class="whitespace-nowrap">{{ __('Two-factor authentication') }}</flux:heading>
            <flux:separator />
        </div>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <div
                    class="relative w-full h-auto"
                    x-cloak
                    x-data="{
                        showRecoveryInput: @js($errors->has('recovery_code')),
                        code: '',
                        recovery_code: '',
                        toggleInput() {
                            this.showRecoveryInput = !this.showRecoveryInput;

                            this.code = '';
                            this.recovery_code = '';

                            $dispatch('clear-2fa-auth-code');

                            $nextTick(() => {
                                this.showRecoveryInput
                                    ? this.$refs.recovery_code?.focus()
                                    : $dispatch('focus-2fa-auth-code');
                            });
                        },
                    }"
                >
                    <form method="POST" action="{{ route('two-factor.login.store') }}">
                        @csrf

                        <div class="space-y-4">
                            <div x-show="!showRecoveryInput">
                                <flux:heading size="sm">{{ __('Authentication code') }}</flux:heading>
                                <p class="text-sm/6 mt-1">{{ __('Enter the code from your authenticator app.') }}</p>

                                <div class="flex items-center my-5">
                                    <flux:otp
                                        x-model="code"
                                        length="6"
                                        name="code"
                                        label="OTP Code"
                                        label:sr-only
                                        class="mx-auto md:mx-0"
                                     />
                                </div>
                            </div>

                            <div x-show="showRecoveryInput">
                                <flux:heading size="sm">{{ __('Recovery code') }}</flux:heading>
                                <p class="text-sm/6 mt-1">{{ __('Enter one of your emergency recovery codes.') }}</p>

                                <div class="mt-4">
                                    <flux:input
                                        size="sm"
                                        type="text"
                                        name="recovery_code"
                                        x-ref="recovery_code"
                                        x-bind:required="showRecoveryInput"
                                        autocomplete="one-time-code"
                                        x-model="recovery_code"
                                    />
                                </div>

                                @error('recovery_code')
                                    <flux:text color="red">
                                        {{ $message }}
                                    </flux:text>
                                @enderror
                            </div>

                            <flux:button
                                size="sm"
                                variant="primary"
                                type="submit"
                            >
                                {{ __('Continue') }}
                            </flux:button>
                        </div>

                        <div class="mt-4 space-x-0.5 text-sm/6">
                            <span class="text-zinc-600 dark:text-zinc-400">{{ __('or you can') }}</span>
                            <span class="font-medium underline cursor-pointer" x-show="!showRecoveryInput" @click="toggleInput()">{{ __('login using a recovery code') }}</span>
                            <span class="font-medium underline cursor-pointer" x-show="showRecoveryInput" @click="toggleInput()">{{ __('login using an authentication code') }}</span>
                        </div>
                    </form>
                </div>
            </div>

            <div class="text-sm/6 space-y-3">
                <p>Two-factor authentication adds a second layer of security to your account. Enter the code from your authenticator application.</p>
                <p>If you've lost access to your authenticator, use one of the recovery codes provided during setup.</p>
            </div>
        </div>
    </div>
</x-layouts::app>
