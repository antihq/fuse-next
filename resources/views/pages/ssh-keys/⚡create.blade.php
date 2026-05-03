<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Add SSH Key')] class extends Component
{
    public string $name = '';

    public string $public_key = '';

    public function create(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'public_key' => 'required|string|starts_with:ssh-',
        ]);

        Auth::user()->sshKeys()->create($validated);

        Flux::toast(variant: 'success', text: __('SSH key added.'));

        $this->redirectRoute('ssh-keys.index', navigate: true);
    }
}; ?>

<div>
    <div class="flex items-center gap-3">
        <flux:heading class="whitespace-nowrap">{{ __('Add SSH Key') }}</flux:heading>
        <flux:separator />
    </div>

    <div class="mt-8 grid grid-cols-1 md:grid-cols-2 gap-8">
        <div>
            <form wire:submit="create" class="space-y-8">
                <flux:input size="sm" wire:model="name" :label="__('Name')" :placeholder="__('MacBook Pro')" required autofocus />

                <flux:textarea
                    size="sm"
                    wire:model="public_key"
                    :label="__('Public Key')"
                    :placeholder="__('ssh-ed25519 AAAAC3Nza...')"
                    required
                    rows="3"
                    class="font-mono text-sm"
                />

                <flux:button size="sm" type="submit" variant="primary" color="blue" icon:trailing="arrow-right">
                    {{ __('Add key') }}
                </flux:button>
            </form>
        </div>

        <div class="text-sm/6 space-y-3">
            <p>Paste your public key below. It will be added to every server you set up.</p>
            <p>Supported key types: Ed25519 and RSA. Generate a new key with <x-code>ssh-keygen -t ed25519</x-code>.</p>
        </div>
    </div>
</div>
