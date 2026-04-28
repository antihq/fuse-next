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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="space-y-8">
                <flux:heading size="lg">{{ __('Connect your first server') }}</flux:heading>

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
                >
                    {{ __('Connect server') }}
                </flux:button>
            </div>

            <div class="flex items-start">
                <div class="w-full rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-800 dark:bg-zinc-950">
                    <flux:heading size="md" class="mb-4">{{ __('What gets installed') }}</flux:heading>
                    <div class="space-y-3 text-sm">
                        <div class="flex items-start gap-3">
                            <flux:icon name="server" class="mt-0.5 size-4 text-zinc-500" />
                            <div>
                                <div class="font-medium">Caddy 2</div>
                                <div class="text-zinc-500">{{ __('Web server with automatic HTTPS') }}</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <flux:icon name="code-bracket" class="mt-0.5 size-4 text-zinc-500" />
                            <div>
                                <div class="font-medium">PHP 8.2–8.5</div>
                                <div class="text-zinc-500">{{ __('Production-ready runtime') }}</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <flux:icon name="cube" class="mt-0.5 size-4 text-zinc-500" />
                            <div>
                                <div class="font-medium">Node.js 22 LTS</div>
                                <div class="text-zinc-500">{{ __('Frontend build runtime') }}</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <flux:icon name="document-text" class="mt-0.5 size-4 text-zinc-500" />
                            <div>
                                <div class="font-medium">Composer 2</div>
                                <div class="text-zinc-500">{{ __('PHP dependency manager') }}</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <flux:icon name="shield-check" class="mt-0.5 size-4 text-zinc-500" />
                            <div>
                                <div class="font-medium">UFW + fail2ban</div>
                                <div class="text-zinc-500">{{ __('Firewall and intrusion protection') }}</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <flux:icon name="arrow-path" class="mt-0.5 size-4 text-zinc-500" />
                            <div>
                                <div class="font-medium">Unattended upgrades</div>
                                <div class="text-zinc-500">{{ __('Automatic security updates') }}</div>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <flux:icon name="computer-desktop" class="mt-0.5 size-4 text-zinc-500" />
                            <div>
                                <div class="font-medium">Supervisor</div>
                                <div class="text-zinc-500">{{ __('Process manager for queues') }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>


