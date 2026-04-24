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
    <x-pages::settings.layout :heading="__('Add SSH Key')" :subheading="__('Add a new public SSH key for server access')">
        <form wire:submit="create" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('MacBook Pro')" required autofocus />

            <flux:textarea
                wire:model="public_key"
                :label="__('Public Key')"
                :placeholder="__('ssh-ed25519 AAAAC3Nza...')"
                required
                rows="3"
                class="font-mono text-sm"
            />

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Add Key') }}</flux:button>
            </div>
        </form>
    </x-pages::settings.layout>
</section>
