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

<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Sites') }}</flux:heading>

    <div class="mb-6">
        <flux:button
            :href="route('servers.show', [$this->team->slug, $this->server])"
            variant="ghost"
            size="sm"
            icon="arrow-left"
            wire:navigate
        >
            {{ __('Back to server') }}
        </flux:button>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <flux:heading>{{ __('Sites') }}</flux:heading>
            <flux:subheading>{{ $server->name }}</flux:subheading>
        </div>
        <flux:button
            :href="route('sites.create', [$this->team->slug, $this->server])"
            variant="primary"
            wire:navigate
        >
            {{ __('Add site') }}
        </flux:button>
    </div>

    @if($server->sites->isEmpty())
        <div class="rounded-lg border border-zinc-200 bg-white p-12 text-center dark:border-zinc-700 dark:bg-zinc-900">
            <flux:subheading>{{ __('No sites found') }}</flux:subheading>
            <flux:text class="text-zinc-500 dark:text-zinc-400 mt-2">{{ __('Add your first site to get started.') }}</flux:text>
        </div>
    @else
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            @foreach($server->sites as $site)
                <div class="flex items-center justify-between p-4 @if(!$loop->last) border-b border-zinc-200 dark:border-zinc-700 @endif">
                    <div>
                        <flux:text class="font-medium">{{ $site->domain }}</flux:text>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $site->repository }}</flux:text>
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:badge :color="$site->status->color()">{{ $site->status->label() }}</flux:badge>
                        <flux:button
                            :href="route('sites.show', [$this->team->slug, $this->server, $site])"
                            variant="ghost"
                            size="sm"
                            wire:navigate
                        >
                            {{ __('View') }}
                        </flux:button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
