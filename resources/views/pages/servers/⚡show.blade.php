<?php

use App\Enums\ServerStatus;
use App\Models\Server;
use App\Models\SshKey;
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
        $memberIds = $this->team->members()->pluck('users.id');

        return SshKey::whereIn('user_id', $memberIds)
            ->with('user:id,name')
            ->get();
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

    public function deleteServer(): void
    {
        $server = Server::findOrFail($this->serverId);
        $this->authorize('delete', [Server::class, $this->team, $server]);
        $server->delete();
        Flux::toast(variant: 'success', text: __('Server deleted. You can now remove it from your VPS provider.'));
        $this->redirect(route('servers.index', ['current_team' => $this->team->slug]), navigate: true);
    }
}; ?>

<div @if($this->shouldPoll) wire:poll.5s="refreshServer" @endif class="space-y-8">
    <div>
        <div class="flex items-center gap-3">
            <flux:heading>{{ $server->ip_address }}</flux:heading>
            <flux:separator />
        </div>
        <flux:badge :color="$server->status->color()" size="sm" class="uppercase tracking-widest mt-2 font-mono">{{ $server->status->label() }}</flux:badge>
    </div>

    @if($server->status === ServerStatus::Pending)
        <div>
            <div class="flex items-center gap-3">
                <flux:heading class="text-nowrap">{{ __('Set up your server') }}</flux:heading>
                <flux:separator />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-8 mt-4">
                <div class="text-sm space-y-3">
                    <p>{{ __('SSH into your server as root and run the command below. It will install Caddy, PHP, Composer, Node.js, and everything else needed to deploy Laravel apps.') }}</p>
                    <p>{{ __('When setup finishes, your server will be marked as ready automatically.') }}</p>
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
                        <div>
                            <flux:heading>{{ __('Keys to be authorized') }}</flux:heading>
                            <div class="w-full rounded-lg ring-1 ring-zinc-800/15 shadow-xs dark:ring-white/20 px-3 mt-4">
                                <flux:table class="whitespace-normal!">
                                    <flux:table.rows>
                                        @foreach($this->teamSshKeys as $key)
                                            <flux:table.row wire:key="ssh-key-{{ $key->id }}">
                                                <flux:table.cell variant="strong">{{ $key->name }}</flux:table.cell>
                                                <flux:table.cell>{{ $key->user->name }}</flux:table.cell>
                                            </flux:table.row>
                                        @endforeach
                                    </flux:table.rows>
                                </flux:table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="space-y-8">
            <div class="space-y-3">
                <flux:input
                    size="sm"
                    :value="$this->provisioningCommand"
                    readonly
                    copyable
                    class="font-mono"
                />

                <p class="text-sm">
                    {{ __("The script reports back when it's done. If it doesn't, you can mark the server as ready manually.") }}
                </p>
            </div>

            <flux:button size="sm" wire:click="markProvisioned" icon:trailing="arrow-right" variant="primary" color="emerald">
                {{ __('Mark as ready') }}
            </flux:button>
        </div>
    @endif

    @if($server->status === ServerStatus::Provisioning)
        <flux:separator />

        <div class="py-3 space-y-3">
            <flux:heading>{{ __('Setting up your server') }}</flux:heading>
            <flux:subheading>{{ __('This may take a few minutes.') }}</flux:subheading>

            <flux:text wire:loading>
                {{ __('Installing Caddy, PHP 8.2–8.5, Composer, Node.js, Supervisor, and more...') }}
            </flux:text>

            <flux:text class="text-xs">
                {{ __("The script reports back when it's done. If it doesn't, you can mark the server as ready manually.") }}
            </flux:text>

            <flux:button wire:click="markProvisioned" size="sm">
                {{ __('Mark as ready') }}
            </flux:button>
        </div>
    @endif

    @if($server->status === ServerStatus::Failed)
        <flux:separator />

        <div class="py-3 space-y-3">
            <flux:heading>{{ __('Setup failed') }}</flux:heading>

            <flux:callout color="red">
                <flux:callout.text>
                    {{ __('The setup script encountered an error. You can try running the command again or mark the server as ready manually.') }}
                </flux:callout.text>
            </flux:callout>

            <flux:input
                size="sm"
                :value="$this->provisioningCommand"
                readonly
                copyable
                class="font-mono"
            />

            <flux:text class="text-xs">
                {{ __("The script reports back when it's done. If it doesn't, you can mark the server as ready manually.") }}
            </flux:text>

            <flux:button wire:click="markProvisioned" variant="ghost" size="sm">
                {{ __('Mark as ready') }}
            </flux:button>
        </div>
    @endif
    @if($server->status === ServerStatus::Provisioned)
        <div>
            <div class="flex items-center gap-3">
                <flux:heading class="text-nowrap">{{ __('Connect to your server') }}</flux:heading>
                <flux:separator />
            </div>

            <div class="mt-4 text-sm space-y-3">
                <p class="max-w-prose">{!! __('SSH into this server as <strong>fuse</strong> to manage your Laravel sites, or as <strong>root</strong> for full system access.') !!}</p>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3">
                    <flux:input size="sm" :value="'ssh fuse@' . $server->ip_address" readonly copyable class="font-mono" />
                    <flux:input size="sm" :value="'ssh root@' . $server->ip_address" readonly copyable class="font-mono" />
                </div>
            </div>
        </div>

        @if($server->public_key)
            <div>
                <div class="flex items-center gap-3">
                    <flux:heading class="text-nowrap">{{ __('Server SSH key') }}</flux:heading>
                    <flux:separator />
                </div>

                <div class="mt-4 text-sm space-y-4">
                    <flux:input size="sm" :value="$server->public_key" readonly copyable rows="2" class="font-mono text-xs" />

                    <flux:heading size="sm">{{ __('Grant repository access') }}</flux:heading>

                    <div class="space-y-3">
                        <div>
                            <flux:text>
                                <strong>{{ __('Deploy key') }}</strong>
                                <flux:badge color="blue" size="sm" class="ml-1">{{ __('Recommended') }}</flux:badge>
                            </flux:text>
                            <flux:text class="max-w-prose">{{ __('Per repository — GitHub → Repo → Settings → Deploy keys. Read-only by default, scoped to a single repo.') }}</flux:text>
                        </div>

                        <flux:separator />

                        <div>
                            <flux:text><strong>{{ __('Account SSH key') }}</strong></flux:text>
                            <flux:text class="max-w-prose">{{ __('Per account — GitHub → Settings → SSH and GPG keys. Grants access to all your repositories, but the key cannot be reused as a deploy key.') }}</flux:text>
                        </div>

                        <flux:separator />

                        <div>
                            <flux:text><strong>{{ __('Machine user') }}</strong></flux:text>
                            <flux:text class="max-w-prose">{{ __('Create a dedicated GitHub account, add this key, then grant that account access to specific repos. Best for teams — centralized and least privilege.') }}</flux:text>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="flex items-center gap-3">
            <flux:heading class="text-nowrap">{{ __('Sites') }}</flux:heading>
            <flux:separator />
        </div>

        <div class="py-3 space-y-2">
            @if($server->sites->isEmpty())
                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('This server is ready. Add a site to start deploying your Laravel application.') }}
                </p>
            @endif

            <flux:button
                size="sm"
                :href="route('sites.create', [$this->team->slug, $this->server])"
                variant="outline"
                class="w-full"
                wire:navigate
            >
                {{ __('Add site') }}
            </flux:button>

            @foreach($server->sites as $site)
                @if (!$loop->first)
                    <flux:separator />
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

    <flux:separator />

    <div class="space-y-3">
        <flux:heading>{{ __('Danger Zone') }}</flux:heading>
        <div class="flex items-center justify-between">
            <flux:text>{{ __('Delete this server and remove it from your team.') }}</flux:text>
            <flux:button
                wire:click="deleteServer"
                wire:confirm="{{ __('Are you sure you want to delete this server?') }}"
                variant="ghost"
                color="red"
                size="sm"
            >
                {{ __('Delete server') }}
            </flux:button>
        </div>
    </div>
</div>
