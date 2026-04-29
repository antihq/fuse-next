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
    <x-pages::settings.layout :heading="''">
        @if($this->sshKeys->isNotEmpty())
            <div class="space-y-6">
                <div class="flex items-center">
                    <flux:heading class="whitespace-nowrap">SSH Keys</flux:heading>
                    <flux:separator class="ml-3" />
                    <flux:button size="sm" :href="route('ssh-keys.create')" icon:trailing="plus" class="rounded-full!" wire:navigate>{{ __('Add SSH Key') }}</flux:button>
                </div>
                <div class="space-y-4">
                    <p class="text-sm max-w-prose">{{ __('These keys are added to every server you provision, giving you passwordless SSH access.') }}</p>
                    <div class="w-full rounded-lg ring-1 ring-zinc-800/15 shadow-xs dark:ring-white/20 px-3">
                        <flux:table class="whitespace-normal!">
                            <flux:table.columns>
                                <flux:table.column class="w-full">Name</flux:table.column>
                                <flux:table.column>Fingerprint</flux:table.column>
                                <flux:table.column>Actions</flux:table.column>
                            </flux:table.columns>
                            <flux:table.rows>
                                @foreach ($this->sshKeys as $key)
                                    <flux:table.row :key="$key->id">
                                        <flux:table.cell variant="strong">{{ $key->name }}</flux:table.cell>
                                        <flux:table.cell><span class="font-mono truncate max-w-[180px] block">{{ $key->fingerprint }}</span></flux:table.cell>
                                        <flux:table.cell>
                                            <flux:button
                                                icon="trash"
                                                variant="danger"
                                                size="sm"
                                                wire:click="deleteKey({{ $key->id }})"
                                                wire:confirm="{{ __('Are you sure you want to delete this SSH key?') }}"
                                            >
                                                {{ __('Delete') }}
                                            </flux:button>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                </div>
            </div>
        @else
            <div class="space-y-6">
                <div>
                    <flux:heading>{{ __('Add your first SSH key') }}</flux:heading>
                    <p class="text-sm mt-2">
                        {{ __('Your public key is added to every server you set up, giving you secure shell access without a password.') }}
                    </p>
                </div>
                <ul class="text-sm space-y-2 list-disc list-inside">
                    <li>{{ __('Ed25519 and RSA key types supported') }}</li>
                    <li>{{ __('Automatically authorized during server setup') }}</li>
                    <li>{{ __('Each team member can add their own keys') }}</li>
                </ul>
                <flux:button
                    :href="route('ssh-keys.create')"
                    icon:trailing="arrow-right"
                    variant="primary"
                    color="blue"
                    size="sm"
                    wire:navigate
                >
                    {{ __('Add SSH Key') }}
                </flux:button>
            </div>
        @endif
    </x-pages::settings.layout>
</section>
