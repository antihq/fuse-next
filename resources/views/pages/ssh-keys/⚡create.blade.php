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

<section class="w-full">
    <x-pages::settings.layout :heading="''">
        <div class="space-y-8">
            <div>
                <flux:heading size="lg">{{ __('Add a new SSH key') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ __('Paste your public key below. It will be added to every server you set up.') }}
                </flux:text>
            </div>

            <form wire:submit="create" class="space-y-6">
                <flux:input wire:model="name" :label="__('Name')" :placeholder="__('MacBook Pro')" required autofocus />

                <flux:textarea
                    wire:model="public_key"
                    :label="__('Public Key')"
                    :placeholder="__('ssh-ed25519 AAAAC3Nza...')"
                    required
                    rows="3"
                    class="font-mono text-sm"
                />

                <div class="flex gap-3">
                    <flux:spacer />
                    <flux:button :href="route('ssh-keys.index')" variant="ghost" wire:navigate>
                        {{ __('Cancel') }}
                    </flux:button>
                    <flux:button type="submit" variant="primary" color="blue" icon:trailing="arrow-right">
                        {{ __('Add key') }}
                    </flux:button>
                </div>
            </form>
        </div>
    </x-pages::settings.layout>
</section>
