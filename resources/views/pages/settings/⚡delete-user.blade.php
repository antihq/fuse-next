<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Delete account')] class extends Component {
    public string $password = '';

    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => 'required|current_password',
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center gap-3">
        <flux:heading class="whitespace-nowrap">{{ __('Delete account') }}</flux:heading>
        <flux:separator />
    </div>

    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-red-700 dark:border-red-200/10 dark:bg-red-900/20 dark:text-red-100 mb-8">
                <p class="font-medium">{{ __('Warning') }}</p>
                <p class="text-sm mt-1">{{ __('Once your account is deleted, all of its resources and data will be permanently deleted.') }}</p>
            </div>

            <form wire:submit="deleteUser" class="space-y-8">
                <flux:input size="sm" wire:model="password" :label="__('Password')" type="password" required viewable data-test="delete-account-password" />

                <div class="flex items-center gap-4">
                    <flux:button size="sm" variant="danger" type="submit" data-test="confirm-delete-user-button">
                        {{ __('Delete account') }}
                    </flux:button>
                    <flux:link size="sm" :href="route('profile.edit')" wire:navigate>{{ __('Cancel') }}</flux:link>
                </div>
            </form>
        </div>

        <div class="text-sm/6 space-y-3">
            <p>This action cannot be undone. Your profile, settings, and all associated data will be permanently removed.</p>
        </div>
    </div>
</div>
