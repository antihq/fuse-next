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
            || $this->server->status === ServerStatus::Provisioning;
    }

    public function markProvisioned(): void
    {
        $this->authorize('update', [$this->team, $this->server]);

        $this->server->status = ServerStatus::Provisioned;
        $this->server->save();
    }
}; ?>

<div class="max-w-xl mx-auto" @if($this->shouldPoll) wire:poll.5s="refreshServer" @endif>
    <div class="flex items-center gap-2 py-3">
        <flux:heading>{{ $server->ip_address }}</flux:heading>
        <flux:badge :color="$server->status->color()" size="sm">{{ $server->status->label() }}</flux:badge>
    </div>

    @if($server->status === ServerStatus::Pending)
        <flux:separator variant="subtle" />

        <div class="py-3 space-y-3">
            <flux:heading>{{ __('Provision Server') }}</flux:heading>
            <flux:subheading>{{ __('SSH to your server as root and run this command') }}</flux:subheading>

            @if(! $this->userHasSshKeys)
                <flux:callout color="amber">
                    <flux:callout.heading>{{ __('No SSH keys configured') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Add an SSH key so you can access this server after provisioning.') }}
                        <flux:link :href="route('ssh-keys.index')" wire:navigate>{{ __('Add SSH Key') }}</flux:link>
                    </flux:callout.text>
                </flux:callout>
            @endif

            @if($this->teamSshKeys->isNotEmpty())
                <flux:callout color="blue">
                    <flux:callout.heading>{{ __('SSH keys that will be authorized') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ $this->teamSshKeys->map(fn ($key) => $key->name)->join(', ') }}
                    </flux:callout.text>
                </flux:callout>
            @endif

            <flux:input
                :value="$this->provisioningCommand"
                readonly
                copyable
                class="font-mono text-sm"
            />

            <flux:button wire:click="markProvisioned" variant="outline" class="w-full">
                {{ __('Mark as Provisioned') }}
            </flux:button>
        </div>
    @endif

    @if($server->status === ServerStatus::Provisioning)
        <flux:separator variant="subtle" />

        <div class="py-3 space-y-3">
            <flux:heading>{{ __('Provisioning in Progress') }}</flux:heading>
            <flux:subheading>{{ __('This may take a few minutes.') }}</flux:subheading>

            <flux:text wire:loading>
                {{ __('Installing software...') }}
            </flux:text>

            <flux:button wire:click="markProvisioned" variant="outline" class="w-full">
                {{ __('Mark as Provisioned') }}
            </flux:button>
        </div>
    @endif

    @if($server->status === ServerStatus::Provisioned)
        <flux:separator variant="subtle" />

        <flux:navbar class="-mb-px">
            <flux:navbar.item :href="route('servers.show', [$this->team->slug, $this->server])" current wire:navigate>
                {{ __('Sites') }}
            </flux:navbar.item>
        </flux:navbar>

        <flux:separator variant="subtle" />

        <div class="py-3 space-y-2">
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
