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
            <div>
                @foreach($this->sshKeys as $key)
                    @if(!$loop->first)
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
                @endforeach
            </div>
            <flux:separator variant="subtle" class="mt-6" />
            <flux:button
                :href="route('ssh-keys.create')"
                icon="plus"
                class="w-full md:w-auto"
                wire:navigate
            >
                {{ __('Add SSH Key') }}
            </flux:button>
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
