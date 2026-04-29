<?php

use App\Models\Server;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sites')] class extends Component
{
    #[Locked]
    public int $serverId;

    public Server $server;

    public function mount(Server $server): void
    {
        $this->serverId = $server->id;
        $this->server = $server;

        $team = Auth::user()->currentTeam;

        $this->authorize('viewAny', [$team, $this->server]);
    }

    public function getTeamProperty()
    {
        return Auth::user()->currentTeam;
    }
}; ?>

<div class="max-w-xl mx-auto">
    <flux:button
        size="sm"
        :href="route('sites.create', [$this->team->slug, $this->server])"
        icon="plus"
        class="mt-5 w-full"
        wire:navigate
    >
        {{ __('Add site') }}
    </flux:button>

    <div class="mt-6">
        @forelse($server->sites as $site)
            @if(!$loop->first)
                <flux:separator variant="subtle" />
            @endif
            <div class="py-3">
            <div class="flex items-center gap-2">
                <flux:text>
                    <flux:link :href="route('sites.show', [$this->team->slug, $this->server, $site])" wire:navigate>
                        {{ $site->domain }}
                    </flux:link>
                </flux:text>
                    <flux:badge :color="$site->status->color()" size="sm">{{ $site->status->label() }}</flux:badge>
                </div>
                <flux:text class="mt-1">{{ $site->repository }}</flux:text>
            </div>
        @empty
            <flux:text class="py-8 text-center">{{ __('No sites yet.') }}</flux:text>
        @endforelse
    </div>
</div>
