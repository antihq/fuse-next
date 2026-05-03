<?php

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Profile settings')] class extends Component {
    use ProfileValidationRules;

    public string $name = '';
    public string $email = '';

    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('A new verification link has been sent to your email address.'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }
}; ?>

<div class="space-y-8">
    <div class="flex items-center gap-3">
        <flux:heading class="whitespace-nowrap">{{ __('Profile') }}</flux:heading>
        <flux:separator />
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
            <form wire:submit="updateProfileInformation" class="space-y-8">
                <flux:input size="sm" wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

                <div>
                    <flux:input size="sm" wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

                    @if ($this->hasUnverifiedEmail)
                        <div>
                            <flux:text class="mt-4">
                                {{ __('Your email address is unverified.') }}

                                <flux:link class="text-sm cursor-pointer" wire:click.prevent="resendVerificationNotification">
                                    {{ __('Click here to re-send the verification email.') }}
                                </flux:link>
                            </flux:text>
                        </div>
                    @endif
                </div>

                <flux:button size="sm" variant="primary" type="submit" data-test="update-profile-button">
                    {{ __('Save') }}
                </flux:button>
            </form>
        </div>

        <div class="text-sm/6 space-y-3">
            <p>Update your name and email address. If you change your email, you'll need to verify the new one.</p>
        </div>
    </div>

    <div>
        <div class="flex items-center">
            <flux:heading class="text-nowrap">{{ __('Danger Zone') }}</flux:heading>
            <flux:separator class="ml-3" />
            <flux:button
                size="sm"
                :href="route('profile.delete')"
                variant="danger"
                icon:trailing="arrow-right"
                class="rounded-full!"
                wire:navigate
                data-test="delete-user-button"
            >
                {{ __('Delete account') }}
            </flux:button>
        </div>
        <p class="text-sm/6 mt-1 max-w-prose">{{ __('Permanently delete your account and all associated data.') }}</p>
    </div>
</div>
