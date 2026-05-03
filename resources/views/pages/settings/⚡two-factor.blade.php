<?php

use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

new #[Title('Two-factor authentication')] class extends Component {
    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showVerificationStep = false;

    public bool $setupComplete = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    public bool $requiresConfirmation;

    public function mount(): void
    {
        $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
    }

    public function startTwoFactorSetup(): void
    {
        $enableTwoFactorAuthentication = app(EnableTwoFactorAuthentication::class);
        $enableTwoFactorAuthentication(auth()->user());

        $this->loadSetupData();
    }

    private function loadSetupData(): void
    {
        $user = auth()->user()?->fresh();

        try {
            if (! $user || ! $user->two_factor_secret) {
                throw new Exception('Two-factor setup secret is not available.');
            }

            $this->qrCodeSvg = $user->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', 'Failed to fetch setup data.');

            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    public function showVerificationIfNecessary(): void
    {
        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;

            $this->resetErrorBag();

            return;
        }

        $this->completeSetup();
    }

    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        $this->validate();

        $confirmTwoFactorAuthentication(auth()->user(), $this->code);

        $this->setupComplete = true;
    }

    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');

        $this->resetErrorBag();
    }

    public function completeSetup(): void
    {
        $this->setupComplete = true;
    }

    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        $disableTwoFactorAuthentication(auth()->user());

        Flux::toast(variant: 'success', text: __('Two-factor authentication disabled.'));

        $this->redirectRoute('security.edit', navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center gap-3">
        <flux:heading class="whitespace-nowrap">{{ __('Two-factor authentication') }}</flux:heading>
        <flux:separator />
    </div>

    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="space-y-8">
            @if ($setupComplete)
                <div class="space-y-6">
                    <div class="space-y-2">
                        <flux:heading>{{ __('Two-factor authentication enabled') }}</flux:heading>
                        <flux:text>{{ __('Two-factor authentication is now enabled. Scan the QR code or enter the setup key in your authenticator app.') }}</flux:text>
                    </div>

                    <div class="flex justify-center">
                        <div class="relative w-64 overflow-hidden border rounded-lg border-stone-200 dark:border-stone-700 aspect-square">
                            @if($qrCodeSvg)
                                <div x-data class="flex items-center justify-center h-full p-4">
                                    <div
                                        class="bg-white p-3 rounded"
                                        :style="($flux.appearance === 'dark' || ($flux.appearance === 'system' && $flux.dark)) ? 'filter: invert(1) brightness(1.5)' : ''"
                                    >
                                        {!! $qrCodeSvg !!}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="relative flex items-center justify-center w-full">
                            <div class="absolute inset-0 w-full h-px top-1/2 bg-stone-200 dark:bg-stone-600"></div>
                            <span class="relative px-2 text-sm bg-white dark:bg-zinc-900 text-stone-600 dark:text-stone-400">
                                {{ __('or, enter the code manually') }}
                            </span>
                        </div>

                        @if($manualSetupKey)
                            <div
                                class="flex items-center space-x-2"
                                x-data="{
                                    copied: false,
                                    async copy() {
                                        try {
                                            await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                            this.copied = true;
                                            setTimeout(() => this.copied = false, 1500);
                                        } catch (e) {
                                            console.warn('Could not copy to clipboard');
                                        }
                                    }
                                }"
                            >
                                <div class="flex items-stretch w-full border rounded-xl dark:border-stone-700">
                                    <input
                                        type="text"
                                        readonly
                                        value="{{ $manualSetupKey }}"
                                        class="w-full p-3 bg-transparent outline-none text-stone-900 dark:text-stone-100"
                                    />
                                    <button
                                        @click="copy()"
                                        class="px-3 transition-colors border-l cursor-pointer border-stone-200 dark:border-stone-600"
                                    >
                                        <flux:icon.document-duplicate x-show="!copied" variant="outline"></flux:icon.document-duplicate>
                                        <flux:icon.check x-show="copied" variant="solid" class="text-green-500"></flux:icon>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>

                </div>
            @elseif ($showVerificationStep)
                <div class="space-y-8">
                    <div class="space-y-2">
                        <flux:heading>{{ __('Verify authentication code') }}</flux:heading>
                        <flux:text>{{ __('Enter the 6-digit code from your authenticator app.') }}</flux:text>
                    </div>

                    <div class="flex items-center justify-center">
                        <flux:otp
                            name="code"
                            wire:model="code"
                            length="6"
                            label="OTP Code"
                            label:sr-only
                        />
                    </div>

                    <div class="flex items-center gap-4">
                        <flux:button
                            size="sm"
                            variant="outline"
                            wire:click="resetVerification"
                        >
                            {{ __('Back') }}
                        </flux:button>

                        <flux:button
                            size="sm"
                            variant="primary"
                            wire:click="confirmTwoFactor"
                            x-bind:disabled="$wire.code.length < 6"
                        >
                            {{ __('Confirm') }}
                        </flux:button>
                    </div>
                </div>
            @else
                <div class="space-y-8">
                    <div class="space-y-2">
                        <flux:heading>{{ __('Enable two-factor authentication') }}</flux:heading>
                        <flux:text>{{ __('To finish enabling two-factor authentication, scan the QR code or enter the setup key in your authenticator app.') }}</flux:text>
                    </div>

                    @error('setupData')
                        <flux:callout variant="danger" icon="x-circle" heading="{{ $message }}"/>
                    @enderror

                    <div class="flex justify-center">
                        <div class="relative w-64 overflow-hidden border rounded-lg border-stone-200 dark:border-stone-700 aspect-square">
                            @empty($qrCodeSvg)
                                <div class="absolute inset-0 flex items-center justify-center bg-white dark:bg-stone-700 animate-pulse">
                                    <flux:icon.loading/>
                                </div>
                            @else
                                <div x-data class="flex items-center justify-center h-full p-4">
                                    <div
                                        class="bg-white p-3 rounded"
                                        :style="($flux.appearance === 'dark' || ($flux.appearance === 'system' && $flux.dark)) ? 'filter: invert(1) brightness(1.5)' : ''"
                                    >
                                        {!! $qrCodeSvg !!}
                                    </div>
                                </div>
                            @endempty
                        </div>
                    </div>

                    <div class="space-y-4">
                        <div class="relative flex items-center justify-center w-full">
                            <div class="absolute inset-0 w-full h-px top-1/2 bg-stone-200 dark:bg-stone-600"></div>
                            <span class="relative px-2 text-sm bg-white dark:bg-zinc-900 text-stone-600 dark:text-stone-400">
                                {{ __('or, enter the code manually') }}
                            </span>
                        </div>

                        <div
                            class="flex items-center space-x-2"
                            x-data="{
                                copied: false,
                                async copy() {
                                    try {
                                        await navigator.clipboard.writeText('{{ $manualSetupKey }}');
                                        this.copied = true;
                                        setTimeout(() => this.copied = false, 1500);
                                    } catch (e) {
                                        console.warn('Could not copy to clipboard');
                                    }
                                }
                            }"
                        >
                            <div class="flex items-stretch w-full border rounded-xl dark:border-stone-700">
                                @empty($manualSetupKey)
                                    <div class="flex items-center justify-center w-full p-3 bg-stone-100 dark:bg-stone-700">
                                        <flux:icon.loading variant="mini"/>
                                    </div>
                                @else
                                    <input
                                        type="text"
                                        readonly
                                        value="{{ $manualSetupKey }}"
                                        class="w-full p-3 bg-transparent outline-none text-stone-900 dark:text-stone-100"
                                    />
                                    <button
                                        @click="copy()"
                                        class="px-3 transition-colors border-l cursor-pointer border-stone-200 dark:border-stone-600"
                                    >
                                        <flux:icon.document-duplicate x-show="!copied" variant="outline"></flux:icon.document-duplicate>
                                        <flux:icon.check x-show="copied" variant="solid" class="text-green-500"></flux:icon.document-duplicate>
                                    </button>
                                @endempty
                            </div>
                        </div>
                    </div>

                        <flux:button
                            size="sm"
                            variant="primary"
                            :disabled="$errors->has('setupData')"
                            wire:click="showVerificationIfNecessary"
                        >
                            {{ __('Continue') }}
                        </flux:button>
                </div>
            @endif
        </div>

        <div class="text-sm/6 space-y-3">
            <p>Two-factor authentication adds a second layer of security. You'll need an authenticator app on your phone (Google Authenticator, Authy, 1Password, etc.).</p>
            <p>Scan the QR code with your authenticator app, then enter the 6-digit code to confirm setup.</p>
            <p>If you lose your device, recovery codes let you regain access. Store them in a secure password manager.</p>
        </div>
    </div>

    <div class="flex items-center mt-8">
        <flux:button size="sm" :href="route('security.edit')" wire:navigate icon="arrow-left" class="rounded-full!">
            {{ __('Return to Security') }}
        </flux:button>
        <flux:separator class="ml-3" />
    </div>
</div>
