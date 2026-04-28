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
    @if($this->servers->isNotEmpty())
        <div class="space-y-6">
            <div class="flex items-center">
                <flux:heading>Servers</flux:heading>
                <flux:separator class="ml-3" />
                <flux:button :href="route('servers.create', ['current_team' => Auth::user()->currentTeam->slug])" icon:trailing="arrow-right" class="rounded-full!" wire:navigate>Connect server</flux:button>
            </div>
            <div class="space-y-4">
                <p class="text-sm max-w-prose">{{ __('This is where all your team\'s servers live. Once connected, you can deploy sites, check provisioning status, and manage each server\'s configuration.') }}</p>
                <div class="w-full rounded-lg ring-1 ring-zinc-800/15 shadow-xs dark:ring-white/20 px-3">
                    <flux:table class="whitespace-normal!">
                        <flux:table.columns>
                            <flux:table.column class="w-full">IP Address</flux:table.column>
                            <flux:table.column>Status</flux:table.column>
                            <flux:table.column>Manage</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($this->servers as $server)
                                <flux:table.row :key="$server->id">
                                    <flux:table.cell variant="strong">{{ $server->ip_address }}</flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge :color="$server->status->color()" size="sm" class="uppercase tracking-widest font-mono">{{ $server->status->label() }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:button
                                            :href="route('servers.show', ['current_team' => Auth::user()->currentTeam->slug, 'server' => $server->id])"
                                            variant="primary"
                                            size="sm"
                                            icon:trailing="arrow-right"
                                            wire:navigate
                                            color="emerald"
                                        >
                                            @if($server->status->value === 'pending')
                                                {{ __('Set Up') }}
                                            @elseif($server->status->value === 'provisioning')
                                                {{ __('View Progress') }}
                                            @elseif($server->status->value === 'provisioned')
                                                {{ __('Manage') }}
                                            @else
                                                {{ __('View Details') }}
                                            @endif
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


