<?php

use App\Models\SshKey;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('SSH Keys')] class extends Component
{
    public string $name = '';

    public string $public_key = '';

    #[Computed]
    public function sshKeys()
    {
        return Auth::user()->sshKeys()->latest()->get();
    }

    public function addKey(): void
    {
        $validated = $this->validate([
            'name' => 'required|string|max:255',
            'public_key' => 'required|string|starts_with:ssh-',
        ]);

        Auth::user()->sshKeys()->create($validated);

        $this->reset('name', 'public_key');
        unset($this->sshKeys);

        Flux::toast(variant: 'success', text: __('SSH key added.'));
    }

    public function deleteKey(int $keyId): void
    {
        $key = Auth::user()->sshKeys()->find($keyId);

        if ($key === null) {
            abort(403);
        }

        $key->delete();

        unset($this->sshKeys);

        Flux::toast(variant: 'success', text: __('SSH key removed.'));
    }
}; ?>

<section class="w-full">
    <x-pages::settings.layout :heading="__('SSH Keys')" :subheading="__('Manage your public SSH keys for server access')">
        <form wire:submit="addKey" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('MacBook Pro')" required />

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

        @if($this->sshKeys->isNotEmpty())
            <flux:separator variant="subtle" />

            <div class="mt-6 space-y-4">
                @foreach($this->sshKeys as $key)
                    <div class="flex items-center justify-between gap-4" wire:key="{{ $key->id }}">
                        <div>
                            <flux:text class="font-medium">{{ $key->name }}</flux:text>
                            <flux:text class="text-xs" variant="subtle">{{ $key->fingerprint }}</flux:text>
                        </div>
                        <flux:button
                            wire:click="deleteKey({{ $key->id }})"
                            variant="ghost"
                            size="sm"
                            icon="trash"
                            class="text-red-500"
                        />
                    </div>
                @endforeach
            </div>
        @endif
    </x-pages::settings.layout>
</section>
