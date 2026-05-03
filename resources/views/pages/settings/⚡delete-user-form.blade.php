<?php

use Livewire\Component;

new class extends Component {}; ?>

<div class="mt-8 space-y-6">
    <div class="flex items-center gap-3">
        <flux:heading class="whitespace-nowrap">{{ __('Delete account') }}</flux:heading>
        <flux:separator />
    </div>

    <div class="mt-8">
        <p class="text-sm/6 mb-4">Once your account is deleted, all of its resources and data will be permanently deleted.</p>

        <flux:modal.trigger name="confirm-user-deletion">
            <flux:button size="sm" variant="danger" data-test="delete-user-button">
                {{ __('Delete account') }}
            </flux:button>
        </flux:modal.trigger>

        <livewire:pages::settings.delete-user-modal />
    </div>
</div>
