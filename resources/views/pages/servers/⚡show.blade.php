<?php

use App\Enums\ServerStatus;
use App\Jobs\TestServerConnectivity;
use App\Models\Server;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
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

    public function getProvisioningCommandProperty(): string
    {
        $url = URL::signedRoute('servers.provision-script', ['server' => $this->server]);

        return "wget --no-verbose -O - {$url} | bash";
    }

    public function getFullProvisioningCommandProperty(): string
    {
        $url = URL::signedRoute('servers.full-provision-script', ['server' => $this->server]);

        return "wget --no-verbose -O - {$url} | bash";
    }

    public function getTeamProperty()
    {
        return Auth::user()->currentTeam;
    }

    public function testConnection(): void
    {
        $this->server->status = ServerStatus::Provisioning;
        $this->server->save();

        TestServerConnectivity::dispatch($this->server);
    }

    public function getShouldPollProperty(): bool
    {
        return $this->server->status === ServerStatus::Provisioning;
    }

    public function markProvisioned(): void
    {
        $this->authorize('update', [$this->team, $this->server]);

        $this->server->status = ServerStatus::Provisioned;
        $this->server->save();
    }
}; ?>

<section class="w-full" @if($this->shouldPoll) wire:poll.5s="refreshServer" @endif>
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Server Details') }}</flux:heading>

    <div class="mb-6">
        <flux:button
            :href="route('servers.index', [$this->team->slug])"
            variant="ghost"
            size="sm"
            icon="arrow-left"
            wire:navigate
        >
            {{ __('Back to servers') }}
        </flux:button>
    </div>

    <flux:heading>{{ $server->name }}</flux:heading>
    <flux:subheading>{{ $server->ip_address }}</flux:subheading>

    <div class="mt-8 space-y-6">
        <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading size="lg" class="mb-4">{{ __('Server Information') }}</flux:heading>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Name') }}</flux:text>
                    <flux:text class="mt-1">{{ $server->name }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('IP Address') }}</flux:text>
                    <flux:text class="mt-1">{{ $server->ip_address }}</flux:text>
                </div>

                <div>
                    <flux:text class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</flux:text>
                    <div class="mt-1">
                        <flux:badge :color="$server->status->color()">{{ $server->status->label() }}</flux:badge>
                    </div>
                </div>
            </div>
        </div>

        @if($server->status === ServerStatus::Pending)
            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg" class="mb-4">{{ __('Step 1: Authorize SSH Key') }}</flux:heading>
                <flux:subheading class="mb-4">{{ __('SSH to your server as root and run this command to authorize our SSH key') }}</flux:subheading>

                <flux:input
                    :value="$this->provisioningCommand"
                    readonly
                    copyable
                    class="font-mono text-sm mb-4"
                />

                <flux:button wire:click="testConnection" class="w-full">
                    <span wire:loading.remove>{{ __('Test Connection') }}</span>
                    <span wire:loading>{{ __('Testing...') }}</span>
                </flux:button>
            </div>
        @endif

        @if($server->status === ServerStatus::Connected)
            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg" class="mb-4">{{ __('Step 2: Provision Server') }}</flux:heading>
                <flux:subheading class="mb-4">{{ __('SSH to your server as root and run this command to install Caddy, MySQL, Valkey, PHP, Composer, and Node.js') }}</flux:subheading>

                <flux:input
                    :value="$this->fullProvisioningCommand"
                    readonly
                    copyable
                    class="font-mono text-sm mb-4"
                />

                <flux:button wire:click="markProvisioned" variant="outline" class="w-full">
                    {{ __('Provisioning completed? Mark as Provisioned') }}
                </flux:button>
            </div>
        @endif

        @if($server->status === ServerStatus::Provisioning)
            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:heading size="lg" class="mb-4">{{ __('Provisioning in Progress') }}</flux:heading>
                <flux:subheading class="mb-4">{{ __('Your server is being provisioned. This may take a few minutes.') }}</flux:subheading>

                <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                    <svg class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3.2.647z"></path>
                    </svg>
                    {{ __('Installing software...') }}
                </div>

                <flux:button wire:click="markProvisioned" variant="outline" class="w-full mt-4">
                    {{ __('Provisioning completed? Mark as Provisioned') }}
                </flux:button>
            </div>
        @endif

        @if($server->status === ServerStatus::Provisioned)
            <div class="rounded-lg border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between mb-4">
                    <flux:heading size="lg">{{ __('Sites') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $server->sites->count() }} {{ __('site(s)') }}</flux:text>
                </div>

                @if($server->sites->isEmpty())
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">{{ __('No sites configured yet.') }}</p>
                @else
                    <div class="space-y-2 mb-4">
                        @foreach($server->sites as $site)
                            <div class="flex items-center justify-between p-3 rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <div class="flex items-center gap-3">
                                    <div>
                                        <flux:text class="font-medium">{{ $site->domain }}</flux:text>
                                    </div>
                                    <flux:badge :color="$site->status->color()">{{ $site->status->label() }}</flux:badge>
                                </div>
                                <flux:button
                                    :href="route('sites.show', [$this->team->slug, $this->server, $site])"
                                    variant="ghost"
                                    size="sm"
                                    wire:navigate
                                >
                                    {{ __('View') }}
                                </flux:button>
                            </div>
                        @endforeach
                    </div>
                @endif

                <flux:button
                    :href="route('sites.index', [$this->team->slug, $this->server])"
                    variant="outline"
                    class="w-full"
                    wire:navigate
                >
                    {{ __('Manage sites') }}
                </flux:button>
            </div>
        @endif
    </div>
</section>
