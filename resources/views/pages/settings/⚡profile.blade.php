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

<div>
    <div class="flex items-center gap-3">
        <flux:heading class="whitespace-nowrap">{{ __('Profile') }}</flux:heading>
        <flux:separator />
    </div>

    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
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

                <div class="flex items-center gap-4">
                    <flux:button size="sm" variant="primary" type="submit" data-test="update-profile-button">
                        {{ __('Save') }}
                    </flux:button>
                </div>
            </form>

            @if (Auth::user() instanceof MustVerifyEmail ? Auth::user()->hasVerifiedEmail() : true)
                <div class="mt-8">
                    <flux:link size="sm" :href="route('profile.delete')" variant="danger" wire:navigate data-test="delete-user-button">
                        {{ __('Delete account') }}
                    </flux:link>
                </div>
            @endif
        </div>

        <div class="text-sm/6 space-y-3">
            <p>Update your name and email address. If you change your email, you'll need to verify the new one.</p>
        </div>
    </div>
</div>
