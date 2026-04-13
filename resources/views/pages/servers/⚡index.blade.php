<?php

use App\Actions\Servers\CreateServer;
use App\Models\Server;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Servers')] class extends Component
{
    public string $ipAddress = '';

    public function createServer(CreateServer $createServer): void
    {
        $team = Auth::user()->currentTeam;

        $this->authorize('create', [Server::class, $team]);

        $validated = $this->validate([
            'ipAddress' => ['required', 'ip'],
        ]);

        $server = $createServer->handle($team, $validated['ipAddress']);

        $this->dispatch('close-modal', name: 'add-server');

        $this->reset('ipAddress');

        Flux::toast(variant: 'success', text: __('Server added.'));

        $this->redirectRoute('servers.show', ['current_team' => $team->slug, 'server' => $server->id], navigate: true);
    }

    /**
     * @return Collection<int, Server>
     */
    public function getServersProperty()
    {
        $team = Auth::user()->currentTeam;

        $this->authorize('viewAny', [Server::class, $team]);

        return $team->servers()->latest()->get();
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Servers') }}</flux:heading>

<x-pages::settings.layout :heading="__('Servers')" :subheading="__('Manage your VPS servers')">
        <div class="flex items-center justify-end">
            <flux:modal.trigger name="add-server">
                <flux:button variant="primary" icon="plus" x-data="" x-on:click.prevent="$dispatch('open-modal', 'add-server')" data-test="servers-add-button">
                    {{ __('Add server') }}
                </flux:button>
            </flux:modal.trigger>
        </div>

        <div class="mt-6 space-y-3">
            @forelse ($this->servers as $server)
                <div class="flex items-center justify-between rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" data-test="server-row">
                    <div class="flex items-center gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium">{{ $server->name }}</span>
                                <flux:badge :color="$server->status->color()">{{ $server->status->label() }}</flux:badge>
                            </div>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $server->ip_address }}</flux:text>
                        </div>
                    </div>

                    <div class="flex items-center gap-1">
                        <flux:tooltip :content="__('View server')">
                            <flux:button
                                variant="ghost"
                                size="sm"
                                icon="eye"
                                :href="route('servers.show', ['current_team' => Auth::user()->currentTeam->slug, 'server' => $server->id])"
                                wire:navigate
                                data-test="server-view-button"
                            />
                        </flux:tooltip>
                    </div>
                </div>
            @empty
                <flux:text class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                    {{ __('No servers yet.') }}
                </flux:text>
            @endforelse
        </div>
    </x-pages::settings.layout>

    <flux:modal name="add-server" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form wire:submit="createServer" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add a new server') }}</flux:heading>
                <flux:subheading>{{ __('Enter IP address of your VPS server.') }}</flux:subheading>
            </div>

            <flux:input wire:model="ipAddress" :label="__('IP address')" type="text" required autofocus data-test="add-server-ip" placeholder="192.168.1.1" />

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="primary" type="submit" data-test="add-server-submit">
                    {{ __('Add server') }}
                </flux:button>
            </div>
    </form>
    </flux:modal>
</section>


