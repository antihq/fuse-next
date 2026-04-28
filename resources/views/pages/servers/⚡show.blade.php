<?php

use App\Enums\ServerStatus;
use App\Models\Server;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Server Details')] class extends Component
{
    #[Locked]
    public int $serverId;

    public Server $server;

    public function mount(Server $server): void
    {
        $this->serverId = $server->id;
        $this->server = $server;

        $team = Auth::user()->currentTeam;

        $this->authorize('view', [$team, $this->server]);
    }

    public function refreshServer(): void
    {
        $this->server->refresh();
    }

    #[Computed]
    public function provisioningCommand(): string
    {
        $url = URL::signedRoute('servers.full-provision-script', ['server' => $this->server]);

        return "wget --no-verbose -O - {$url} | bash";
    }

    #[Computed]
    public function team()
    {
        return Auth::user()->currentTeam;
    }

    #[Computed]
    public function teamSshKeys()
    {
        return $this->team->members()
            ->with('sshKeys')
            ->get()
            ->pluck('sshKeys')
            ->flatten();
    }

    #[Computed]
    public function userHasSshKeys(): bool
    {
        return Auth::user()->sshKeys()->exists();
    }

    #[Computed]
    public function shouldPoll(): bool
    {
        return $this->server->status === ServerStatus::Pending
            || $this->server->status === ServerStatus::Provisioning
            || $this->server->status === ServerStatus::Failed;
    }

    public function markProvisioned(): void
    {
        $this->authorize('update', [$this->team, $this->server]);

        $this->server->status = ServerStatus::Provisioned;
        $this->server->save();
    }
}; ?>

<div @if($this->shouldPoll) wire:poll.5s="refreshServer" @endif class="space-y-8">
    <div>
        <flux:breadcrumbs>
            <flux:breadcrumbs.item :href="route('servers.index', ['current_team' => $this->team->slug])" wire:navigate>
                {{ __('Servers') }}
            </flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $server->ip_address }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <div class="mt-4">
            <flux:heading size="lg">{{ $server->ip_address }}</flux:heading>
            <flux:badge :color="$server->status->color()" size="sm" class="uppercase tracking-widest mt-2">{{ $server->status->label() }}</flux:badge>
        </div>
    </div>

    @if($server->status === ServerStatus::Pending)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-8">
            <div>
                <flux:heading>{{ __('Set up your server') }}</flux:heading>
                <p class="mt-2 text-sm">{{ __('SSH into your server as root and run the command below. It will install Caddy, PHP, Composer, Node.js, and everything else needed to deploy Laravel apps.') }}</p>
            </div>

            <div class="space-y-8">
                @if(! $this->userHasSshKeys)
                    <flux:callout color="yellow" class="border-0!">
                        <flux:callout.heading>{{ __('No SSH keys configured') }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('Add an SSH key so you can access this server once setup is complete.') }}
                        </flux:callout.text>
                        <x-slot name="actions">
                            <flux:button :href="route('ssh-keys.index')" variant="primary" color="yellow" size="sm" icon:trailing="arrow-right" wire:navigate>{{ __('Add SSH Key') }}</flux:button>
                        </x-slot>
                    </flux:callout>
                @endif

                @if($this->teamSshKeys->isNotEmpty())
                    <flux:text variant="subtle" size="sm">
                        {{ __('Keys to be authorized:') }} {{ $this->teamSshKeys->map(fn ($key) => $key->name)->join(', ') }}
                    </flux:text>
                @endif
            </div>
        </div>

        <div class="space-y-8">
            <flux:input
                :value="$this->provisioningCommand"
                readonly
                copyable
                class="font-mono"
            />

            <div class="flex">
                <flux:spacer />
                <flux:button wire:click="markProvisioned" icon:trailing="arrow-right" variant="primary" color="emerald">
                    {{ __('Skip — mark as ready') }}
                </flux:button>
            </div>
        </div>
    @endif

    @if($server->status === ServerStatus::Provisioning)
        <flux:separator variant="subtle" />

        <div class="py-3 space-y-3">
            <flux:heading>{{ __('Setting up your server') }}</flux:heading>
            <flux:subheading>{{ __('This may take a few minutes.') }}</flux:subheading>

            <flux:text wire:loading>
                {{ __('Installing Caddy, PHP 8.2–8.5, Composer, Node.js, Supervisor, and more...') }}
            </flux:text>

            <flux:button wire:click="markProvisioned" variant="ghost" size="sm">
                {{ __('Skip — mark as ready') }}
            </flux:button>
        </div>
    @endif

    @if($server->status === ServerStatus::Failed)
        <flux:separator variant="subtle" />

        <div class="py-3 space-y-3">
            <flux:heading>{{ __('Setup failed') }}</flux:heading>

            <flux:callout color="red">
                <flux:callout.text>
                    {{ __('The setup script encountered an error. You can try running the command again or mark the server as ready manually.') }}
                </flux:callout.text>
            </flux:callout>

            <flux:input
                :value="$this->provisioningCommand"
                readonly
                copyable
                class="font-mono"
            />

            <flux:button wire:click="markProvisioned" variant="ghost" size="sm">
                {{ __('Skip — mark as ready') }}
            </flux:button>
        </div>
    @endif
    @if($server->status === ServerStatus::Provisioned)
        <flux:separator variant="subtle" />

        <flux:heading size="md">{{ __('Sites') }}</flux:heading>

        <flux:separator variant="subtle" />

        <div class="py-3 space-y-2">
            @if($server->sites->isEmpty())
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('This server is ready. Add a site to start deploying your Laravel application.') }}
                </p>
            @endif

            <flux:button
                :href="route('sites.create', [$this->team->slug, $this->server])"
                variant="outline"
                class="w-full"
                wire:navigate
            >
                {{ __('Add site') }}
            </flux:button>

            @foreach($server->sites as $site)
                @if (!$loop->first)
                    <flux:separator variant="subtle" />
                @endif
                <div class="flex items-center gap-2 py-2" wire:key="{{ $site->id }}">
                    <flux:text>
                        <flux:link :href="route('sites.show', [$this->team->slug, $this->server, $site])" wire:navigate>
                            {{ $site->domain }}
                        </flux:link>
                    </flux:text>
                    <flux:badge :color="$site->status->color()" size="sm">{{ $site->status->label() }}</flux:badge>
                </div>
            @endforeach
        </div>
    @endif
</div>
