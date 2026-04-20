<?php

use App\Models\Server;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Servers')] class extends Component
{
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

<div>
    <div class="max-w-xl mx-auto">
        <flux:button
            :href="route('servers.create', ['current_team' => Auth::user()->currentTeam->slug])"
            icon="plus"
            class="mt-5 w-full"
            data-test="servers-add-button"
            wire:navigate
        >
            {{ __('Add server') }}
        </flux:button>

        <div class="mt-6">
            @forelse ($this->servers as $server)
                @if (!$loop->first)
                    <flux:separator variant="subtle" />
                @endif
                <div class="flex items-center gap-2 py-3" data-test="server-row">
                    <flux:text>
                        <flux:link :href="route('servers.show', ['current_team' => Auth::user()->currentTeam->slug, 'server' => $server->id])" wire:navigate>
                            {{ $server->ip_address }}
                        </flux:link>
                    </flux:text>
                    <flux:badge :color="$server->status->color()" size="sm">{{ $server->status->label() }}</flux:badge>
                </div>
            @empty
                <flux:text class="py-8 text-center text-zinc-500 dark:text-zinc-400">
                    {{ __('No servers yet.') }}
                </flux:text>
            @endforelse
        </div>
    </div>
</div>


