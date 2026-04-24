<?php

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('SSH Keys')] class extends Component
{
    #[Computed]
    public function sshKeys()
    {
        return Auth::user()->sshKeys()->latest()->get();
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
        <flux:button
            :href="route('ssh-keys.create')"
            icon="plus"
            class="w-full"
            wire:navigate
        >
            {{ __('Add SSH Key') }}
        </flux:button>

        <div class="mt-6">
            @forelse ($this->sshKeys as $key)
                @if (! $loop->first)
                    <flux:separator variant="subtle" />
                @endif
                <div class="flex items-center justify-between gap-4 py-3" wire:key="{{ $key->id }}">
                    <div>
                        <flux:text class="font-medium">{{ $key->name }}</flux:text>
                        <flux:text class="text-xs" variant="subtle">{{ $key->fingerprint }}</flux:text>
                    </div>
                    <flux:dropdown position="bottom" align="end">
                        <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                        <flux:menu>
                            <flux:menu.item
                                icon="trash"
                                variant="danger"
                                as="button"
                                type="button"
                                wire:click="deleteKey({{ $key->id }})"
                                wire:confirm="{{ __('Are you sure you want to delete this SSH key?') }}"
                            >
                                {{ __('Delete key') }}
                            </flux:menu.item>
                        </flux:menu>
                    </flux:dropdown>
                </div>
            @empty
                <flux:text class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                    {{ __('No SSH keys yet.') }}
                </flux:text>
            @endforelse
        </div>
    </x-pages::settings.layout>
</section>
