<?php

use App\Models\Server;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Servers')] class extends Component
{
    public function deleteServer(int $serverId): void
    {
        $server = Server::findOrFail($serverId);
        $this->authorize('delete', [Server::class, Auth::user()->currentTeam, $server]);
        $server->delete();
        Flux::toast(variant: 'success', text: __('Server deleted. You can now remove it from your VPS provider.'));
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

<div>
    @if($this->servers->isNotEmpty())
        <div class="mt-6">
            @foreach ($this->servers as $server)
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
                    <div class="ml-auto">
                        <flux:dropdown position="bottom" align="end">
                            <flux:button variant="ghost" size="sm" icon="ellipsis-horizontal" />
                            <flux:menu>
                                <flux:menu.item
                                    icon="trash"
                                    variant="danger"
                                    as="button"
                                    type="button"
                                    wire:click="deleteServer({{ $server->id }})"
                                    wire:confirm="{{ __('Are you sure you want to delete this server?') }}"
                                >
                                    {{ __('Delete server') }}
                                </flux:menu.item>
                            </flux:menu>
                        </flux:dropdown>
                    </div>
                </div>
            @endforeach
        </div>
        <flux:separator variant="subtle" class="mt-6" />
        <flux:button
            :href="route('servers.create', ['current_team' => Auth::user()->currentTeam->slug])"
            icon="plus"
            class="w-full md:w-auto"
            wire:navigate
        >
            {{ __('Connect server') }}
        </flux:button>
    @else
        <div class="space-y-8">
            <flux:heading size="lg">{{ __('Connect your first server') }}</flux:heading>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-8">
                    <div class="space-y-3">
                        <p class="text-sm">
                            {{ __('You\'ll need a fresh VPS running Ubuntu LTS. Once you connect it, we\'ll give you a one-line script to run that sets everything up for deploying Laravel apps.') }}
                        </p>
                        <ul class="list-disc list-inside text-sm space-y-1">
                            <li>{{ __('Fresh install of Ubuntu 24.04 LTS (or latest LTS)') }}</li>
                            <li>{{ __('Make sure you can SSH in as root') }}</li>
                            <li>{{ __('The script installs Caddy, PHP, and everything else you need') }}</li>
                        </ul>
                        <p class="text-sm">
                            {{ __('Once set up, you can deploy Laravel sites with a single command.') }}
                        </p>
                    </div>

                    <flux:button
                        :href="route('servers.create', ['current_team' => Auth::user()->currentTeam->slug])"
                        icon:trailing="arrow-right"
                        color="blue"
                        variant="primary"
                        wire:navigate
                        size="sm"
                    >
                        {{ __('Connect server') }}
                    </flux:button>
                </div>

                <div>
                    <flux:heading>{{ __('What gets installed') }}</flux:heading>
                    <div class="w-full rounded-lg ring-1 ring-zinc-950/5 shadow-xs dark:ring-white/10 px-3 border-l-4 border-zinc-800/15 dark:border-white/20 mt-4">
                        <flux:table class="whitespace-normal!">
                            <flux:table.rows>
                                <flux:table.row>
                                    <flux:table.cell variant="strong">Caddy 2</flux:table.cell>
                                    <flux:table.cell>{{ __('Web server with automatic HTTPS') }}</flux:table.cell>
                                </flux:table.row>
                                <flux:table.row>
                                    <flux:table.cell variant="strong">PHP 8.2–8.5</flux:table.cell>
                                    <flux:table.cell>{{ __('Production-ready runtime') }}</flux:table.cell>
                                </flux:table.row>
                                <flux:table.row>
                                    <flux:table.cell variant="strong">Node.js 22 LTS</flux:table.cell>
                                    <flux:table.cell>{{ __('Frontend build runtime') }}</flux:table.cell>
                                </flux:table.row>
                                <flux:table.row>
                                    <flux:table.cell variant="strong">Composer 2</flux:table.cell>
                                    <flux:table.cell>{{ __('PHP dependency manager') }}</flux:table.cell>
                                </flux:table.row>
                                <flux:table.row>
                                    <flux:table.cell variant="strong">UFW + fail2ban</flux:table.cell>
                                    <flux:table.cell>{{ __('Firewall and intrusion protection') }}</flux:table.cell>
                                </flux:table.row>
                                <flux:table.row>
                                    <flux:table.cell variant="strong">Unattended upgrades</flux:table.cell>
                                    <flux:table.cell>{{ __('Automatic security updates') }}</flux:table.cell>
                                </flux:table.row>
                                <flux:table.row>
                                    <flux:table.cell variant="strong">Supervisor</flux:table.cell>
                                    <flux:table.cell>{{ __('Process manager for queues') }}</flux:table.cell>
                                </flux:table.row>
                            </flux:table.rows>
                        </flux:table>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>


